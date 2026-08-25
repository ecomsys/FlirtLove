<?php

use App\Models\Chat;
use App\Models\ChatParticipant;
use App\Models\Message;
use App\Models\AdminLog;
use App\Models\SupportTemplate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

        /** @var string Фильтр списка тикетов (active, unread, archived) */
    public string $chatFilter = 'unread';
    
    /** @var int|null ID активного чата для просмотра переписки */
    #[Url(as: 'chat', except: '')]
    public ?int $activeChatId = null;
    
    /** @var string Текст сообщения для отправки */
    public string $messageBody = '';
    
    /** @var string Поиск по имени или ID чата */
    #[Url(as: 'q', except: '')]
    public string $search = '';
    
    /** @var bool Видимость модалки создания нового тикета */
    public bool $showNewTicketModal = false;

    /** @var string URL для кнопки "Назад" */
    public string $backUrl = '';

    /**
     * Инициализация компонента.
     * Запоминаем URL "Назад" и обрабатываем умный поиск по ID или прямой старт с юзером.
     */
    public function mount($user_id = null): void
    {
        // ФИКС: Запоминаем URL "Назад" только при первой загрузке
        $previousUrl = url()->previous();
        $this->backUrl = ($previousUrl && $previousUrl !== url()->current()) 
            ? $previousUrl 
            : route('admin.dashboard');

        $adminId = auth()->id();
        if ($user_id) {
            $this->startChatWithUser($user_id);
            return;
        }

               // Умный поиск: если пришли по ссылке ?q=123, автоматически открываем чат
        if (!empty($this->search) && is_numeric($this->search)) {
            $chat = Chat::where('type', 'support')
                ->whereHas('participants', fn($q) => $q->where('user_id', auth()->id()))
                ->with(['participants' => fn($q) => $q->select('chat_id', 'user_id', 'is_hidden')->where('user_id', auth()->id())])
                ->find((int) $this->search);
                
            if ($chat) {
                $participant = $chat->participants->first();
                if ($participant) {
                    // ФИКС: Автоматически переключаем вкладку, если чат в архиве
                    $this->chatFilter = $participant->is_hidden ? 'archived' : 'active';
                }
                $this->selectChat($chat->id);
                return;
            }
        }

        $unreadChat = Chat::where('type', 'support')
            ->whereHas('participants', fn($q) => $q->where('user_id', $adminId)->where('unread_count', '>', 0)->where('is_hidden', false))
            ->latest('last_message_at')
            ->first();

        if ($unreadChat) {
            $this->selectChat($unreadChat->id);
        } else {
            $latestChat = Chat::where('type', 'support')
                ->whereHas('participants', fn($q) => $q->where('user_id', $adminId)->where('is_hidden', false))
                ->latest('last_message_at')
                ->first();
            if ($latestChat) {
                $this->selectChat($latestChat->id);
            }
        }
    }

    /**
     * Хук Livewire: сброс пагинации и умная подсветка при поиске.
     */
     public function updatedSearch(): void
    {
        $this->resetPage();

        // Если ввели число, проверяем: это ID тикета?
        if (is_numeric($this->search) && !empty($this->search)) {
            $chat = Chat::where('type', 'support')
                ->whereHas('participants', fn($q) => $q->where('user_id', auth()->id()))
                ->with(['participants' => fn($q) => $q->select('chat_id', 'user_id', 'is_hidden')->where('user_id', auth()->id())])
                ->find((int) $this->search);
                
            if ($chat) {
                $participant = $chat->participants->first();
                if ($participant) {
                    // ФИКС: Автоматически переключаем вкладку, если чат в архиве
                    $this->chatFilter = $participant->is_hidden ? 'archived' : 'active';
                }
                $this->selectChat($chat->id);
                return;
            }
        }
        
        // Если не нашли как ID тикета — оставляем как обычный поиск (по имени)
        $this->activeChatId = null;
    }
    /**
     * Очистка строки поиска.
     */
    public function clearSearch(): void
    {
        $this->search = '';
        $this->activeChatId = null;
        $this->resetPage();
    }

    #[Computed]
    public function flatTemplates(): array
    {
        return SupportTemplate::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(function ($t, $index) {
                return [
                    'category' => $t->category,
                    'title' => $t->title,
                    'body' => $t->body,
                    'index' => $index,
                ];
            })
            ->toArray();
    }

    public function setTemplate(int $index): void
    {
        $this->messageBody = $this->flatTemplates[$index]['body'] ?? '';
        $this->dispatch('focus-message-input');
    }

    #[On('user-selected')]
    public function startChatWithUser(int $id): void
    {
        $admin = auth()->user();
         $user = \App\Models\User::withTrashed()->find($id);

        if ($user) {
            if ($this->chatFilter !== 'active') {
                $this->chatFilter = 'active';
                $this->resetPage();
            }

            $chat = Chat::getOrCreateSupportChat($admin->id, $user->id);
            $this->selectChat($chat->id);
        }

        $this->showNewTicketModal = false;
        unset($this->chats);
    }    

    public function selectChat(int $chatId): void
    {
        $this->activeChatId = $chatId;
        $this->messageBody = '';

        ChatParticipant::where('chat_id', $chatId)
            ->where('user_id', auth()->id())
            ->update(['unread_count' => 0]);

        unset($this->stats); 
        unset($this->chats);
        
        $this->dispatch('scroll-to-active-chat');
        $this->dispatch('focus-message-input');
        $this->dispatch('scroll-chat-bottom');
    }

    public function sendMessage(): void
    {
        $this->validate(['messageBody' => 'required|string|max:2000']);
        $chat = Chat::find($this->activeChatId);
        if (!$chat) return;
        $sender = auth()->user();

        \DB::transaction(function () use ($chat, $sender) {
            Message::create([
                'chat_id' => $chat->id,
                'sender_id' => $sender->id,
                'type' => 'text',
                'body' => $this->messageBody,
            ]);
            $chat->update(['last_message_at' => now()]);
            ChatParticipant::where('chat_id', $chat->id)->where('user_id', '!=', $sender->id)->increment('unread_count');
        });

        AdminLog::record('support.message_sent', $chat, auth()->user());
        $this->messageBody = '';
        
        unset($this->chats);
        $this->dispatch('scroll-chat-bottom');
    }

    public function archiveChat(int $chatId): void
    {
        ChatParticipant::where('chat_id', $chatId)
            ->where('user_id', auth()->id())
            ->update(['is_hidden' => true]);
        AdminLog::record('support.archive', Chat::find($chatId), auth()->user());

        $this->activeChatId = null;
        unset($this->chats);
        unset($this->stats); 
        $this->dispatch('show-toast', type: 'info', message: 'Тикет архивирован.');
    }

    public function unarchiveChat(int $chatId): void
    {
        ChatParticipant::where('chat_id', $chatId)
            ->where('user_id', auth()->id())
            ->update(['is_hidden' => false]);
        AdminLog::record('support.unarchive', Chat::find($chatId), auth()->user());

        $this->activeChatId = null;
        unset($this->chats);
        unset($this->stats); 
        $this->dispatch('show-toast', type: 'success', message: 'Тикет возвращен из архива.');
    }

    public function setFilter(string $filter): void
    {
        $this->chatFilter = $filter;
        $this->activeChatId = null;
        $this->resetPage();
        unset($this->chats);
    }

    #[Computed]
    public function chats()
    {
        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
        $avatarQuery = fn($q) => $q
            ->select(['id', 'user_id', 'is_primary', 'status', 'path_thumb', 'path_medium', 'path_large', 'path_original'])
            ->orderByDesc('is_primary')
            ->limit(1);

        return Chat::where('type', 'support')
            ->whereHas('participants', fn($q) => $q->where('user_id', auth()->id())->where('is_hidden', $this->chatFilter === 'archived'))
            ->when($this->chatFilter === 'unread', function ($q) {
                $q->whereHas('participants', fn($sub) => $sub->where('user_id', auth()->id())->where('unread_count', '>', 0));
            })
            ->when($this->search, function ($query) use ($operator) {
                $query->where(function ($q) use ($operator) {
                    // Ищем строго по имени участника
                    $q->whereHas('participants.user', fn($sub) => $sub->where('name', $operator, "%{$this->search}%"));
                    
                    // ИЛИ если ввели цифры — ищем по ID самого тикета (чата)
                    if (is_numeric($this->search)) {
                        $q->orWhere('id', (int) $this->search);
                    }
                });
            })
            ->with(['participants.user' => fn($q) => $q->withTrashed()->with(['photos' => $avatarQuery]), 'messages' => fn($q) => $q->latest()->limit(1)])
            ->orderByDesc('last_message_at')
            ->paginate(15);
    }

    #[Computed]
    public function stats(): array
    {
        $stats = ChatParticipant::where('user_id', auth()->id())
            ->selectRaw("
                SUM(CASE WHEN is_hidden = false THEN 1 ELSE 0 END) as active_count,
                SUM(CASE WHEN is_hidden = true THEN 1 ELSE 0 END) as archived_count,
                SUM(CASE WHEN is_hidden = false AND unread_count > 0 THEN 1 ELSE 0 END) as unread_count
            ")->first();

        return [
            'active' => (int) ($stats->active_count ?? 0),
            'unread' => (int) ($stats->unread_count ?? 0),
            'archived' => (int) ($stats->archived_count ?? 0),
        ];
    }

    public function with(): array
    {
        $avatarQuery = fn($q) => $q->select(['id', 'user_id', 'is_primary', 'status', 'path_thumb', 'path_medium', 'path_large', 'path_original'])->orderByDesc('is_primary')->limit(1);
        
        $partner = null;
        $activeMessages = collect();

        if ($this->activeChatId) {
            // Оптимизация: Берем чат из уже загруженной коллекции, чтобы не делать лишних запросов к БД
            $activeChat = $this->chats->firstWhere('id', $this->activeChatId);
            
            if (!$activeChat) {
                $activeChat = Chat::with(['participants.user' => fn($q) => $q->withTrashed()->with(['photos' => $avatarQuery])])->find($this->activeChatId);
            }

            $partner = $activeChat?->participants->firstWhere('user_id', '!=', auth()->id())?->user;

            $activeMessages = Message::where('chat_id', $this->activeChatId)
                ->latest()
                ->limit(100)
                ->with('sender.photos', $avatarQuery)
                ->get()
                ->sortBy('created_at');
        }

        return [
            'chats' => $this->chats,
            'stats' => $this->stats,
            'partner' => $partner,
            'activeMessages' => $activeMessages,
        ];
    }
};?>

<div class="space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-4">
            <a href="{{ $backUrl }}" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-semibold flex items-center gap-2 flex-wrap">
                    <x-lucide-life-buoy class="w-6 h-6" />
                    Чат поддержки                       
                    @if ($this->stats['active'] > 0)
                        <x-ui.badge variant="default" size="sm" wire:key="badge-active">{{ $this->stats['active'] }} активных</x-ui.badge>
                    @endif
                    @if ($this->stats['unread'] > 0)
                        <x-ui.badge variant="destructive" size="sm" wire:key="badge-unread">{{ $this->stats['unread'] }} требуют ответа</x-ui.badge>
                    @endif
                </h1>
                <p class="text-sm text-muted-foreground">Служебный чат администрации</p>
            </div>
        </div>

        <x-ui.button wire:click="$set('showNewTicketModal', true)" variant="default" size="sm" wire:key="btn-new-ticket">
            <x-lucide-plus class="w-4 h-4" /> Написать пользователю
        </x-ui.button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 bg-card border border-border rounded-lg p-4 min-h-[calc(100vh-16rem)]">

        <!-- Левая панель: Тикеты -->
        <div wire:poll.15s class="lg:col-span-1 border-r border-border pr-4 flex flex-col h-[calc(100vh-16rem)]">

            <div class="flex gap-1.5 mb-3 shrink-0" wire:key="filter-buttons-container">
                <x-ui.button wire:click="setFilter('unread')" variant="{{ $chatFilter === 'unread' ? 'default' : 'secondary' }}" size="sm" class="flex-1" wire:key="btn-filter-unread">
                    <span class="flex items-center justify-center gap-1">
                        Непроч.
                        <x-ui.badge variant="{{ $this->stats['unread'] > 0 ? 'destructive' : 'secondary' }}" size="xs" class="{{ $this->stats['unread'] > 0 ? '' : 'bg-muted-foreground/10' }}">{{ $this->stats['unread'] }}</x-ui.badge>
                    </span>
                </x-ui.button>
                
                <x-ui.button wire:click="setFilter('active')" variant="{{ $chatFilter === 'active' ? 'default' : 'secondary' }}" size="sm" class="flex-1" wire:key="btn-filter-active">
                    Актив. <x-ui.badge size="xs" class="ml-1 {{ $chatFilter === 'active' ? 'bg-primary-foreground/20' : 'bg-muted-foreground/10' }}">{{ $this->stats['active'] }}</x-ui.badge>
                </x-ui.button>
                
                <x-ui.button wire:click="setFilter('archived')" variant="{{ $chatFilter === 'archived' ? 'default' : 'secondary' }}" size="sm" class="flex-1" wire:key="btn-filter-archived">
                    Архив <x-ui.badge size="xs" class="ml-1 {{ $chatFilter === 'archived' ? 'bg-primary-foreground/20' : 'bg-muted-foreground/10' }}">{{ $this->stats['archived'] }}</x-ui.badge>
                </x-ui.button>
            </div>

             <div class="relative mb-3 shrink-0" wire:key="search-wrapper">
                <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground z-10" />
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск по имени юзера или id тикета..." class="pl-9 pr-8" />
                @if (!empty($search))
                    <button wire:click="clearSearch" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground z-10">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                @endif
            </div>

            <div x-data="{ scrollActive() { let el = this.$el.querySelector('.chat-active'); if(el) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); } }"
                 @scroll-to-active-chat.window="$nextTick(() => scrollActive())"
                 class="flex-1 min-h-0 overflow-y-auto space-y-2 pr-1 little-scroll"
                 wire:key="chat-list-scroll-container">
                
                @forelse ($this->chats as $chat)
                    @php
                        $myParticipant = $chat->participants->firstWhere('user_id', auth()->id());
                        $unreadCount = $myParticipant?->unread_count ?? 0;
                        $chatPartner = $chat->participants->firstWhere('user_id', '!=', auth()->id())?->user;
                        $lastMsg = $chat->messages->first();
                    @endphp

                    <div wire:click="selectChat({{ $chat->id }})"
                        class="p-3 rounded-lg cursor-pointer transition-colors relative overflow-hidden {{ $this->activeChatId === $chat->id ? 'chat-active bg-primary/10 border border-primary/30' : ($unreadCount > 0 ? 'bg-blue-500/5 border border-blue-500/30 hover:bg-blue-500/10' : 'bg-muted/30 hover:bg-muted border border-transparent') }}"
                        wire:key="sup-chat-{{ $chat->id }}">

                        @if ($unreadCount > 0)
                            <div class="absolute left-0 top-0 bottom-0 w-1 bg-blue-500"></div>
                        @endif

                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <x-avatar src="{{ $chatPartner?->avatar_url }}" name="{{ $chatPartner?->name }}" size="sm" userId="{{ $chatPartner?->id }}" showStatus="true" :isOnline="$chatPartner?->is_online" />
                                @if ($unreadCount > 0)
                                    <span class="absolute bottom-0 right-0 block h-3 w-3 rounded-full bg-blue-500 border-2 border-card"></span>
                                @endif
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-center gap-2">
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-1 min-w-0">
                                            <x-user-status-sign :user="$chatPartner" />
                                            <a href="{{ route('admin.users.show', $chatPartner?->id) }}" wire:navigate class="text-sm truncate hover:text-primary {{ $unreadCount > 0 ? 'font-bold text-blue-600 dark:text-blue-400' : 'font-medium' }}" @if(!$chatPartner) style="pointer-events: none;" @endif>{{ $chatPartner?->name ?? 'Удален' }}</a>
                                            @if ($chatPartner?->has_active_premium) <x-lucide-crown class="w-3 h-3 text-yellow-500" /> @endif                                        
                                        </div>
                                        <p class="text-xs truncate mt-1 {{ $unreadCount > 0 ? 'text-foreground font-medium' : 'text-muted-foreground' }}">
                                            @if ($lastMsg) {{ Str::limit($lastMsg->body, 30) }} @else <span class="italic">Нет сообщений</span> @endif
                                        </p>
                                    </div>

                                    <div class="flex flex-col items-end gap-1 shrink-0">
                                        @if ($chat->last_message_at)
                                            <span class="text-[10px] {{ $unreadCount > 0 ? 'text-blue-500 font-medium' : 'text-muted-foreground' }}">{{ $chat->last_message_at->diffForHumans() }}</span>
                                        @endif
                                        <span class="text-[10px] text-muted-foreground bg-muted px-1 py-0.5 rounded-xs whitespace-nowrap">#{{ $chat->id }}</span>
                                        @if ($unreadCount > 0)
                                            <span class="bg-blue-500 text-white text-[10px] font-bold rounded-full h-5 min-w-[20px] flex items-center justify-center px-1">{{ $unreadCount }}</span>
                                        @endif
                                    </div>
                                </div>                              
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12 text-muted-foreground text-sm" wire:key="empty-state-{{ $chatFilter }}">
                        @if ($chatFilter == 'unread')
                            <p>Непрочитанных тикетов нет 🎉</p>
                        @elseif($chatFilter == 'archived')
                            <p>Архив пуст</p>
                        @else
                            <p>Тикетов нет</p>
                        @endif
                    </div>
                @endforelse
            </div>
            
            <div class="shrink-0 pt-4 mt-2 border-t border-border" wire:key="pagination-wrapper">
                {{ $this->chats->links('partials.pagination') }}
            </div>
        </div>

        <!-- Правая панель: Переписка -->
        <div wire:poll.10s="$refresh" class="lg:col-span-2 flex flex-col bg-muted/10 rounded-lg px-4 pt-4 h-[calc(100vh-16rem)] overflow-hidden" wire:key="chat-window-{{ $this->activeChatId ?? 'empty' }}">
            @if ($this->activeChatId)
                <!-- Шапка чата -->
                <div class="shrink-0 flex items-center justify-between border-b border-border pb-3 mb-4" wire:key="header-{{ $this->activeChatId }}">
                    <div class="flex items-center gap-3">
                        <x-avatar src="{{ $partner?->avatar_url }}" name="{{ $partner?->name }}" size="sm" userId="{{ $partner?->id }}" showStatus="true" :isOnline="$partner?->is_online" />
                        <div class="flex flex-col">
                            <div class="flex items-center gap-1">
                                <x-user-status-sign :user="$partner" />
                                <a href="{{ route('admin.users.show', $partner?->id) }}" wire:navigate class="hover:text-primary font-medium text-sm">{{ $partner?->name ?? 'Удален' }}</a>
                                @if ($partner?->has_active_premium) <x-lucide-crown class="w-3 h-3 text-yellow-500" /> @endif                                
                            </div>
                            <div class="text-xs text-muted-foreground">{{ $partner?->email ?? '-' }}</div>
                        </div>
                    </div>

                    <div class="flex gap-2 items-center">
                        <span class="text-[0.85rem] text-muted-foreground bg-muted px-1.5 py-0.5 rounded font-mono">#{{ $this->activeChatId }}</span>
                        @if ($chatFilter === 'archived')
                            <x-ui.button wire:click="unarchiveChat({{ $this->activeChatId }})" wire:confirm="Вернуть тикет из архива?" variant="ghost" size="icon-sm" wire:target="unarchiveChat({{ $this->activeChatId }})" title="Вернуть из архива" wire:key="btn-unarchive-{{ $this->activeChatId }}">
                                <x-lucide-archive-restore class="w-4 h-4 text-success" wire:loading.remove wire:target="unarchiveChat({{ $this->activeChatId }})" />
                                <x-lucide-loader-2 class="w-4 h-4 animate-spin hidden" wire:loading wire:target="unarchiveChat({{ $this->activeChatId }})" />
                            </x-ui.button>
                        @else
                            <x-ui.button wire:click="archiveChat({{ $this->activeChatId }})" wire:confirm="Архивировать тикет?" variant="ghost" size="icon-sm" wire:target="archiveChat({{ $this->activeChatId }})" title="Архивировать" wire:key="btn-archive-{{ $this->activeChatId }}">
                                <x-lucide-archive class="w-4 h-4 text-muted-foreground" wire:loading.remove wire:target="archiveChat({{ $this->activeChatId }})" />
                                <x-lucide-loader-2 class="w-4 h-4 animate-spin hidden" wire:loading wire:target="archiveChat({{ $this->activeChatId }})" />
                            </x-ui.button>
                        @endif
                    </div>
                </div>

                <!-- Лента сообщений -->
                <div x-data="{ autoScroll: true }"
                    x-init="setTimeout(() => { $el.scrollTop = $el.scrollHeight; }, 50);"
                    @scroll="autoScroll = ($el.scrollHeight - $el.scrollTop - $el.clientHeight < 100)"
                    @scroll-chat-bottom.window="$nextTick(() => { if(autoScroll) $el.scrollTop = $el.scrollHeight; })"
                    class="flex-1 min-h-0 overflow-y-auto gap-4 pr-2 little-scroll flex flex-col"
                    wire:key="msg-list-{{ $this->activeChatId }}">
                    
                    @forelse ($activeMessages as $msg)
                        @php $isMe = $msg->sender_id === auth()->id(); @endphp
                        <div class="flex items-end gap-2 {{ $isMe ? 'justify-end' : 'justify-start' }}" wire:key="msg-{{ $msg->id }}">
                            @if (!$isMe)
                                <x-avatar src="{{ $partner?->avatar_url }}" name="{{ $partner?->name }}" size="xs" />
                            @endif
                            <div class="max-w-[80%]">
                                <div class="text-left whitespace-pre-line break-words {{ $isMe ? 'bg-primary text-primary-foreground' : 'bg-muted text-foreground' }} rounded-2xl px-4 py-2 text-sm">{{ trim($msg->body) }}</div>
                                <div class="text-[0.65rem] text-muted-foreground mt-1 {{ $isMe ? 'text-right' : 'text-left' }}">{{ $msg->created_at->format('d.m H:i') }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-sm text-muted-foreground py-8" wire:key="empty-msg-{{ $this->activeChatId }}">Начните диалог.</div>
                    @endforelse
                </div>

                <!-- Блок отправки -->
                <div class="shrink-0 mt-4 space-y-1" wire:key="send-block-{{ $this->activeChatId }}">
                    <div class="relative flex-1" @focus-message-input.window="$nextTick(() => { let ta = $el.querySelector('textarea'); if(ta) ta.focus(); })">
                        <x-ui.context-menu class="w-full">
                            <x-ui.context-menu-trigger class="block w-full">
                                <x-ui.textarea wire:model="messageBody"
                                    @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.sendMessage(); }"
                                    placeholder="Введите сообщение... (Enter - отправить, Shift+Enter - новая строка)"
                                    rows="3"
                                    class="bg-card w-full resize-none max-h-64 pr-12 pb-3 border border-border rounded-lg focus:outline-none" />
                            </x-ui.context-menu-trigger>

                            <x-ui.context-menu-content class="w-80" wire:ignore.self>
                                <div x-data="templateSearch({{ \Illuminate\Support\Js::from($this->flatTemplates) }})" wire:ignore x-init="initSearch()">
                                    <div class="px-4 py-2" @click.stop @mousedown.stop>
                                        <input type="text" x-ref="searchInput" 
                                            x-model="search" 
                                            @input="findMatch()" 
                                            @keydown.stop="handleKeydown($event)"
                                            @keyup.stop
                                            placeholder="Поиск шаблона..." 
                                            class="w-full px-2 py-1.5 text-sm border border-border rounded bg-card focus:outline-none focus:ring-1 focus:ring-primary" 
                                            autocomplete="off"
                                        >
                                    </div>
                                    <x-ui.context-menu-separator />
                                    <div class="max-h-60 overflow-y-auto p-3 little-scroll tpl-list">
                                        <template x-for="(template, index) in templates" :key="index">
                                            <div :data-idx="index">
                                                <div x-show="index === 0 || templates[index-1].category !== template.category" class="px-2 py-1.5 mt-1 text-[10px] font-bold uppercase text-muted-foreground/70">
                                                    <span x-text="template.category"></span>
                                                </div>
                                                <x-ui.context-menu-item
                                                    @click="$wire.setTemplate(template.index); document.body.click();"
                                                    x-bind:class="{
                                                        'bg-primary/10': highlightedIndex === index,
                                                        'text-primary font-medium': matchIndex === index && highlightedIndex !== index
                                                    }"
                                                >
                                                    <span x-text="template.title"></span>
                                                </x-ui.context-menu-item>
                                            </div>
                                        </template>
                                        <div x-show="templates.length === 0" class="px-2 py-4 text-center text-xs text-muted-foreground">
                                            Шаблонов пока нет
                                        </div>
                                    </div>
                                </div>
                            </x-ui.context-menu-content>
                        </x-ui.context-menu>

                        <x-ui.button wire:click="sendMessage" wire:target="sendMessage" wire:loading.attr="disabled" size="icon" class="absolute bottom-3.5 right-3.5 shadow-md rounded-lg">
                            <x-lucide-send class="w-4 h-4" wire:loading.remove wire:target="sendMessage" />
                            <x-lucide-loader-2 class="w-4 h-4 animate-spin hidden" wire:loading wire:target="sendMessage" />
                        </x-ui.button>
                    </div>
                    <p class="text-xs text-muted-foreground flex items-center gap-1">
                        <x-lucide-mouse-pointer-click class="w-3 h-3" />
                        Правый клик по полю ввода для вставки шаблона.
                    </p>
                </div>
            @else
                <div class="flex-1 flex flex-col items-center justify-center text-muted-foreground" wire:key="empty-chat-window">
                    <x-lucide-inbox class="w-16 h-16 mb-4 opacity-20" />
                    <h3 class="text-lg font-medium">Выберите тикет</h3>
                </div>
            @endif
        </div>
    </div>

    <!-- Модалка создания нового тикета -->
    <div x-data="{ show: @entangle('showNewTicketModal') }" 
         x-show="show" x-cloak 
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/30 backdrop-blur-sm"
         @click.self="$wire.set('showNewTicketModal', false)"
         wire:key="new-ticket-modal">
        
        <div x-show="show" 
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="bg-card border border-border rounded-lg shadow-2xl max-w-md w-full p-6 space-y-4">
             
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Найти пользователя</h2>
                <button wire:click="$set('showNewTicketModal', false)" class="text-muted-foreground hover:text-foreground transition-colors">
                    <x-lucide-x class="w-5 h-5" />
                </button>
            </div>
            <livewire:user-search />
            <p class="text-xs text-muted-foreground">Найдите пользователя, чтобы начать чат поддержки.</p>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('templateSearch', (templatesData) => ({
                search: '',
                highlightedIndex: -1, // Управляет фоном (стрелки)
                matchIndex: -1,       // Управляет цветом текста (поиск)
                templates: templatesData,
                
                initSearch() {
                    this.$nextTick(() => {
                        this.$refs.searchInput?.focus();
                    });
                },

                handleKeydown(e) {
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        this.moveDown();
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        this.moveUp();
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        this.selectHighlighted();
                    }
                },

                isMatch(t) {
                    if (!this.search || this.search.trim() === '') return false;
                    const val = this.search.toLowerCase().trim();
                    return t.title.toLowerCase().includes(val) || t.category.toLowerCase().includes(val);
                },

                findMatch() {
                    // Если инпут пустой — СБРАСЫВАЕМ АБСОЛЮТНО ВСЕ ПОДСВЕТКИ!
                    if (!this.search || this.search.trim() === '') {
                        this.matchIndex = -1;
                        this.highlightedIndex = -1;
                        return;
                    }
                    
                    const idx = this.templates.findIndex(t => this.isMatch(t));
                    this.matchIndex = idx; // Красим текст в синий
                    
                    // Если нашли совпадение — перемещаем туда же фоновое выделение стрелок
                    if (idx !== -1) {
                        this.highlightedIndex = idx;
                        this.scrollToIndex(idx);
                    }
                },
                
                moveDown() {
                    // Если ничего не выделено — стрелки не работают
                    if (this.highlightedIndex === -1) return;
                    
                    this.highlightedIndex = Math.min(this.highlightedIndex + 1, this.templates.length - 1);
                    this.scrollToIndex(this.highlightedIndex);
                },
                
                moveUp() {
                    if (this.highlightedIndex === -1) return;
                    
                    this.highlightedIndex = Math.max(this.highlightedIndex - 1, 0);
                    this.scrollToIndex(this.highlightedIndex);
                },
                
                selectHighlighted() {
                    // Enter выбирает то, что сейчас выделено фоном
                    if (this.highlightedIndex >= 0) {
                        this.$wire.setTemplate(this.templates[this.highlightedIndex].index);
                        // Закрываем меню
                        document.body.click();
                    }
                },
                
                scrollToIndex(idx) {
                    if (idx < 0) return;
                    this.$nextTick(() => {
                        const container = this.$el?.querySelector('.tpl-list');
                        if (!container) return;
                        const item = container.querySelector('[data-idx="' + idx + '"]');
                        if (item) {
                            item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                    });
                }
            }));
        });
    </script>
</div>