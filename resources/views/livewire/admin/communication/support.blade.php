<?php

use App\Models\Chat;
use App\Models\ChatParticipant;
use App\Models\Message;
use App\Actions\Admin\ManageSupportChatsAction;
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

    public string $chatFilter = 'unread';
    
    #[Url(as: 'chat', except: '')]
    public ?int $activeChatId = null;
    
    public string $messageBody = '';
    
    #[Url(as: 'q', except: '')]
    public string $search = '';
    
    public bool $showNewTicketModal = false;
    public string $backUrl = '';

    public function mount($user_id = null): void
    {
        $previousUrl = url()->previous();
        $this->backUrl = ($previousUrl && $previousUrl !== url()->current()) 
            ? $previousUrl 
            : route('admin.dashboard');

        $adminId = auth()->id();
        if ($user_id) {
            $this->startChatWithUser($user_id);
            return;
        }

        // ФИКС: Читаем напрямую из Request
        $qParam = request()->query('q', '');
        $chatParam = request()->query('chat', '');

        // 1. Если пришли по прямой ссылке ?chat=678
        if (!empty($chatParam)) {
            $chat = Chat::where('type', 'support')->find((int) $chatParam);
            if ($chat) {
                $this->activeChatId = $chat->id;
                $this->search = (string) $chat->id; // Подставляем в поле поиска
                
                $participant = $chat->participants->firstWhere('user_id', $adminId);
                if ($participant) {
                    $this->chatFilter = $participant->is_hidden ? 'archived' : ($participant->unread_count > 0 ? 'unread' : 'active');
                } else {
                    $this->chatFilter = 'active'; // Если админ не участник, открываем как активный
                }
                return;
            } else {
                $this->activeChatId = null;
            }
        }

        // 2. Если пришли по ссылке с поиском ?q=123
        if (!empty($qParam) && is_numeric($qParam)) {
            $chat = Chat::where('type', 'support')->find((int) $qParam);
            if ($chat) {
                $this->activeChatId = $chat->id;
                $this->search = (string) $qParam; // Подставляем в поле поиска
                
                $participant = $chat->participants->firstWhere('user_id', $adminId);
                if ($participant) {
                    $this->chatFilter = $participant->is_hidden ? 'archived' : ($participant->unread_count > 0 ? 'unread' : 'active');
                } else {
                    $this->chatFilter = 'active'; // Если админ не участник, открываем как активный
                }
                return;
            }
        }

        // 3. Дефолтная логика, если нет параметров
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

    public function updatedSearch(): void
    {
        $this->resetPage();
        $adminId = auth()->id(); // ФИКС: Объявили переменную!

        if (!empty($this->search) && is_numeric($this->search)) {
            $chat = Chat::where('type', 'support')->find((int) $this->search);
            if ($chat) {
                $participant = $chat->participants->firstWhere('user_id', $adminId);
                if ($participant) {
                    $this->chatFilter = $participant->is_hidden ? 'archived' : ($participant->unread_count > 0 ? 'unread' : 'active');
                } else {
                    $this->chatFilter = 'active';
                }
                $this->selectChat($chat->id);
                return;
            }
        }
        
        $this->activeChatId = null;
    }

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
        unset($this->stats); 
        unset($this->chats);
        $this->dispatch('scroll-to-active-chat');
        $this->dispatch('focus-message-input');
        $this->dispatch('scroll-chat-bottom');
    }

    public function markAsRead(int $chatId, ManageSupportChatsAction $action): void
    {
        $chat = Chat::find($chatId);
        if (!$chat) return;

        $action->markAsRead($chat, auth()->user());

        unset($this->stats); 
        unset($this->chats);
        $this->dispatch('show-toast', type: 'success', message: 'Тикет отмечен как прочитанный.');
    }

    public function archiveChat(int $chatId, ManageSupportChatsAction $action): void
    {
        $chat = Chat::find($chatId);
        if (!$chat) return;

        $action->archiveChat($chat, auth()->user());

        $this->activeChatId = null;
        unset($this->chats);
        unset($this->stats); 
        $this->dispatch('show-toast', type: 'info', message: 'Тикет архивирован.');
    }

    public function unarchiveChat(int $chatId, ManageSupportChatsAction $action): void
    {
        $chat = Chat::find($chatId);
        if (!$chat) return;

        $action->unarchiveChat($chat, auth()->user());

        $this->activeChatId = null;
        unset($this->chats);
        unset($this->stats); 
        $this->dispatch('show-toast', type: 'success', message: 'Тикет возвращен из архива.');
    }

    public function setFilter(string $filter): void
    {
        $this->chatFilter = $filter;
        $this->search = ''; 
        $this->activeChatId = null;
        $this->resetPage();
        unset($this->chats);
    }

    public function sendMessage(ManageSupportChatsAction $action): void
    {
        $this->validate(['messageBody' => 'required|string|max:2000']);
        
        $chat = Chat::find($this->activeChatId);
        if (!$chat) return;

        $action->sendMessage($chat, auth()->user(), $this->messageBody);

        $this->messageBody = '';
        
        unset($this->chats);
        unset($this->stats);
        $this->dispatch('scroll-chat-bottom');
    }

    #[Computed]
    public function stats(): array
    {
        $stats = ChatParticipant::where('user_id', auth()->id())
            ->selectRaw("
                SUM(CASE WHEN is_hidden = false AND unread_count = 0 THEN 1 ELSE 0 END) as active_count,
                SUM(CASE WHEN is_hidden = true THEN 1 ELSE 0 END) as archived_count,
                SUM(CASE WHEN is_hidden = false AND unread_count > 0 THEN 1 ELSE 0 END) as unread_count
            ")->first();

        return [
            'active' => (int) ($stats->active_count ?? 0),
            'unread' => (int) ($stats->unread_count ?? 0),
            'archived' => (int) ($stats->archived_count ?? 0),
        ];
    }

    #[Computed]
    public function chats()
    {
        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
        $avatarQuery = fn($q) => $q->select(['id', 'user_id', 'is_primary', 'status', 'path_thumb', 'path_medium', 'path_large', 'path_original'])->orderByDesc('is_primary')->limit(1);

        // ФИКС: Если мы ищем по ID, не ограничиваем список "только моими чатами", чтобы чужой чат появился в списке
        $isIdSearch = is_numeric($this->search) && !empty($this->search);

        $query = Chat::where('type', 'support');

        if (!$isIdSearch) {
            $query->whereHas('participants', fn($q) => $q->where('user_id', auth()->id()))
                ->when($this->chatFilter === 'archived', fn($q) => $q->whereHas('participants', fn($sub) => $sub->where('user_id', auth()->id())->where('is_hidden', true)))
                ->when($this->chatFilter === 'active', fn($q) => $q->whereHas('participants', fn($sub) => $sub->where('user_id', auth()->id())->where('is_hidden', false)->where('unread_count', 0)))
                ->when($this->chatFilter === 'unread', fn($q) => $q->whereHas('participants', fn($sub) => $sub->where('user_id', auth()->id())->where('is_hidden', false)->where('unread_count', '>', 0)));
        }

        return $query->when($this->search, function ($q) use ($operator, $isIdSearch) {
                $q->where(function ($sub) use ($operator, $isIdSearch) {
                    if ($isIdSearch) {
                        $sub->where('id', (int) $this->search);
                    } else {
                        $sub->whereHas('participants.user', fn($uq) => $uq->withTrashed()->where('name', $operator, "%{$this->search}%"));
                    }
                });
            })
            ->with(['participants.user' => fn($q) => $q->withTrashed()->with(['photos' => $avatarQuery]), 'messages' => fn($q) => $q->latest()->limit(1)])
            ->orderByDesc('last_message_at')
            ->paginate(15);
    }

    public function with(): array
    {
        $avatarQuery = fn($q) => $q->select(['id', 'user_id', 'is_primary', 'status', 'path_thumb', 'path_medium', 'path_large', 'path_original'])->orderByDesc('is_primary')->limit(1);
        
        $partner = null;
        $activeMessages = collect();
        $activeUnreadCount = 0;

        if ($this->activeChatId) {
            $activeChat = $this->chats->firstWhere('id', $this->activeChatId);
            
            if (!$activeChat) {
                $activeChat = Chat::with(['participants.user' => fn($q) => $q->withTrashed()->with(['photos' => $avatarQuery])])->find($this->activeChatId);
            }

            if ($activeChat) {
                $partner = $activeChat->participants->firstWhere('user_id', '!=', auth()->id())?->user;
                $myParticipant = $activeChat->participants->firstWhere('user_id', auth()->id());
                $activeUnreadCount = $myParticipant?->unread_count ?? 0;

                $activeMessages = Message::where('chat_id', $this->activeChatId)
                    ->latest()
                    ->limit(100)
                    ->with(['sender' => fn($q) => $q->withTrashed()->with(['photos' => $avatarQuery])])
                    ->get()
                    ->sortBy('created_at');
            }
        }

        return [
            'chats' => $this->chats,
            'stats' => $this->stats,
            'partner' => $partner,
            'activeMessages' => $activeMessages,
            'activeUnreadCount' => $activeUnreadCount,
        ];
    }
}; 
?>

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
                <x-ui.button title="Нерочитаннные чаты" wire:click="setFilter('unread')" variant="{{ $chatFilter === 'unread' ? 'default' : 'secondary' }}" size="sm" class="flex-1" wire:key="btn-filter-unread">
                    <span class="flex items-center justify-center gap-1">
                        Непр.
                        <x-ui.badge variant="{{ $this->stats['unread'] > 0 ? 'destructive' : 'secondary' }}" size="xs" class="{{ $this->stats['unread'] > 0 ? '' : 'bg-muted-foreground/10' }}">{{ $this->stats['unread'] }}</x-ui.badge>
                    </span>
                </x-ui.button>
                
                <x-ui.button title="Активные чаты" wire:click="setFilter('active')" variant="{{ $chatFilter === 'active' ? 'default' : 'secondary' }}" size="sm" class="flex-1" wire:key="btn-filter-active">
                    Акт. <x-ui.badge size="xs" class="ml-1 {{ $chatFilter === 'active' ? 'bg-primary-foreground/20' : 'bg-muted-foreground/10' }}">{{ $this->stats['active'] }}</x-ui.badge>
                </x-ui.button>
                
                <x-ui.button title="Чаты отправленные в архив" wire:click="setFilter('archived')" variant="{{ $chatFilter === 'archived' ? 'default' : 'secondary' }}" size="sm" class="flex-1" wire:key="btn-filter-archived">
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
                        class="p-3 rounded-lg cursor-pointer transition-colors relative overflow-hidden {{ $this->activeChatId === $chat->id ? 'chat-active bg-primary/10 border border-primary/30' : ($unreadCount > 0 ? 'bg-red-500/5 border border-red-500/30 hover:bg-red-500/10' : 'bg-muted/30 hover:bg-muted border border-transparent') }}"
                        wire:key="sup-chat-{{ $chat->id }}"
                        {{-- НОВОЕ: Авто-скролл к активному чату --}}
                        x-data="{ isHi: {{ $this->activeChatId === $chat->id ? 'true' : 'false' }} }"
                        x-init="isHi && setTimeout(() => { $el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 200)"
                    >
                        @if ($unreadCount > 0)
                            <div class="absolute left-0 top-0 bottom-0 w-0.5 bg-red-500"></div>
                        @endif

                        <div class="flex items-center gap-2">                            
                            <x-avatar src="{{ $chatPartner?->avatar_url }}" name="{{ $chatPartner?->name }}" size="sm" userId="{{ $chatPartner?->id }}" showStatus="true" :isOnline="$chatPartner?->is_online" />                                              
                            
                            <div class="flex-1 min-w-0 flex justify-between items-center gap-0">
                                {{-- ФИКС: Добавлен min-w-0, чтобы этот блок сжимался первым --}}
                                <div class="flex flex-1 flex-col min-w-0">
                                    <div class="flex items-center gap-1 min-w-0">
                                        <x-user-status-sign :user="$chatPartner" />
                                        <a href="{{ route('admin.users.show', $chatPartner?->id) }}" wire:navigate class="text-sm truncate hover:text-primary {{ $unreadCount > 0 ? 'font-bold text-red-600 dark:text-red-400' : 'font-medium' }}" @if(!$chatPartner) style="pointer-events: none;" @endif>{{ $chatPartner?->name ?? 'Удален' }}</a>
                                        @if ($chatPartner?->has_active_premium) <x-lucide-crown class="w-3 h-3 text-yellow-500 shrink-0" /> @endif                                        
                                    </div>
                                    <p class="text-xs truncate mt-1 {{ $unreadCount > 0 ? 'text-foreground font-medium' : 'text-muted-foreground' }}">
                                        @if ($lastMsg) {{ Str::limit($lastMsg->body, 30) }} @else <span class="italic">Нет сообщений</span> @endif
                                    </p>
                                </div>

                                <div class="flex flex-col items-end shrink-0">
                                    @if ($chat->last_message_at)
                                        {{-- ФИКС: Добавлен whitespace-nowrap, чтобы дата не переносилась --}}
                                        <span class="text-[10px] whitespace-nowrap {{ $unreadCount > 0 ? 'text-red-500 font-medium' : 'text-muted-foreground' }}">{{ $chat->last_message_at->diffForHumans() }}</span>
                                    @endif
                                    <span class="text-[10px] text-muted-foreground bg-muted px-1 py-0.5 rounded-xs whitespace-nowrap">#{{ $chat->id }}</span>
                                    @if ($unreadCount > 0)
                                        <span class="bg-red-500 text-white text-[10px] font-bold rounded-full h-5 min-w-[20px] flex items-center justify-center px-0.5 whitespace-nowrap">{{ $unreadCount }}</span>
                                    @endif
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
                       
                       
                        @if ($activeUnreadCount > 0)
                            <x-ui.button wire:click="markAsRead({{ $this->activeChatId }})" variant="default" size="sm" wire:target="markAsRead({{ $this->activeChatId }})" wire:loading.attr="disabled" class="h-8 px-3 text-xs">
                                <span wire:loading.remove wire:target="markAsRead({{ $this->activeChatId }})" class="flex items-center gap-1.5">
                                    <x-lucide-check-check class="w-4 h-4" /> Отметить как прочитано
                                </span>
                                <span wire:loading wire:target="markAsRead({{ $this->activeChatId }})" class="flex items-center gap-1.5">
                                    <x-lucide-loader-2 class="w-4 h-4 animate-spin" />
                                </span>
                            </x-ui.button>
                        @endif

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
                    @php
                        $isSystem = $msg->type === 'system';
                        // Проверяем, является ли отправчик сотрудником команды
                        $isStaff = !$isSystem && $msg->sender && $msg->sender->isStaff();
                    @endphp

                    @if ($isSystem)
                        {{-- Системное сообщение (Антифрод, алерты) --}}
                        <div class="flex justify-center my-2" wire:key="sys-msg-{{ $msg->id }}">
                            <div class="text-xs text-center text-amber-700 dark:text-amber-400 bg-amber-500/10 border border-amber-500/30 rounded-lg px-4 py-1.5 max-w-[90%] shadow-sm flex items-center gap-2">
                                <x-lucide-shield-alert class="w-3.5 h-3.5 shrink-0" />
                                <span class="whitespace-pre-line">{{ trim($msg->body) }}</span>
                            </div>
                        </div>
                    @else
                        {{-- Обычное сообщение --}}
                        <div class="flex items-end gap-2 {{ $isStaff ? 'justify-end' : 'justify-start' }}" wire:key="msg-{{ $msg->id }}">
                            @if (!$isStaff)
                                <x-avatar src="{{ $msg->sender?->avatar_url }}" name="{{ $msg->sender?->name }}" size="xs" userId="{{ $msg->sender?->id }}" showStatus="true" :isOnline="$msg->sender?->is_online" />
                            @endif
                            <div class="max-w-[80%]">
                                {{-- Если пишет другой админ (не я), показываем его имя --}}
                                @if ($isStaff && $msg->sender_id !== auth()->id())
                                    <div class="text-[10px] font-bold mb-0.5 text-primary-foreground/80 uppercase tracking-wide">
                                        {{ $msg->sender?->name }}
                                    </div>
                                @endif
                                <div class="text-left whitespace-pre-line break-words {{ $isStaff ? 'bg-primary text-primary-foreground' : 'bg-muted text-foreground' }} rounded-2xl px-4 py-2 text-sm">{{ trim($msg->body) }}</div>
                                <div class="text-[0.65rem] text-muted-foreground mt-1 {{ $isStaff ? 'text-right' : 'text-left' }}">{{ $msg->created_at->format('d.m H:i') }}</div>
                            </div>
                        </div>
                    @endif
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