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

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    public string $chatFilter = 'active';

    public ?int $activeChatId = null;
    public string $messageBody = '';
    public string $search = '';
    public bool $showNewTicketModal = false;
    public $activeMessages = null;

    // ФИКС: Переключатель архива
    // public bool $showArchived = false;

    #[Computed]
    public function flatTemplates(): array
    {
        // ФИКС: Берем шаблоны из БД
        return SupportTemplate::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(function ($t, $index) {
                return [
                    'category' => $t->category,
                    'title' => $t->title,
                    'body' => $t->body,
                    'index' => $index, // Индекс нужен для Alpine.js
                ];
            })
            ->toArray();
    }

    // ФИКС: Ищем шаблон по индексу в коллекции выше
    public function setTemplate(int $index): void
    {
        $this->messageBody = $this->flatTemplates[$index]['body'] ?? '';
        $this->dispatch('focus-message-input');
    }

    #[On('user-selected')]
    public function startChatWithUser(int $id): void
    {
        $admin = auth()->user();
        $user = \App\Models\User::find($id);

        if ($user) {
            // ФИКС: Если мы были в архиве — переключаемся на активные
            if ($this->chatFilter !== 'active') {
                $this->chatFilter = 'active';
                $this->resetPage();
            }

            $chat = Chat::getOrCreateSupportChat($admin->id, $user->id);
            $this->selectChat($chat->id);
        }

        $this->showNewTicketModal = false;

        // ФИКС: Сбрасываем кэш списка, чтобы новый чат мгновенно появился слева
        unset($this->chats);
    }

    public function mount($user_id = null): void
    {
        $adminId = auth()->id();
        if ($user_id) {
            $this->startChatWithUser($user_id);
        } else {
            $unreadChat = Chat::where('type', 'support')->whereHas('participants', fn($q) => $q->where('user_id', $adminId)->where('unread_count', '>', 0)->where('is_hidden', false))->latest('last_message_at')->first();

            if ($unreadChat) {
                $this->selectChat($unreadChat->id);
            } else {
                $latestChat = Chat::where('type', 'support')->whereHas('participants', fn($q) => $q->where('user_id', $adminId)->where('is_hidden', false))->latest('last_message_at')->first();
                if ($latestChat) {
                    $this->selectChat($latestChat->id);
                }
            }
        }
    }

    public function selectChat(int $chatId): void
    {
        $this->activeChatId = $chatId;
        $this->messageBody = '';

        ChatParticipant::where('chat_id', $chatId)
            ->where('user_id', auth()->id())
            ->update(['unread_count' => 0]);

        $this->loadActiveMessages();
        // ФИКС: Сбрасываем счетчики, так как непрочитанные обнулились
        unset($this->stats); 
    }

    public function loadActiveMessages(): void
    {
        if (!$this->activeChatId) {
            $this->activeMessages = collect();
            return;
        }

        $avatarQuery = fn($q) => $q
            ->select(['id', 'user_id', 'is_primary', 'status', 'path_thumb', 'path_medium', 'path_large', 'path_original'])
            ->orderByDesc('is_primary')
            ->limit(1);

        $this->activeMessages = Message::where('chat_id', $this->activeChatId)->latest()->limit(100)->with('sender.photos', $avatarQuery)->get()->sortBy('created_at');
    }

    public function sendMessage(): void
    {
        $this->validate(['messageBody' => 'required|string|max:2000']);
        $chat = Chat::find($this->activeChatId);
        if (!$chat) {
            return;
        }
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
        $this->loadActiveMessages();
    }

    public function archiveChat(int $chatId): void
    {
        ChatParticipant::where('chat_id', $chatId)
            ->where('user_id', auth()->id())
            ->update(['is_hidden' => true]);
        AdminLog::record('support.archive', Chat::find($chatId), auth()->user());

        $this->activeChatId = null;
        $this->activeMessages = null;

        // ФИКС: Сбрасываем кэш списка чатов, чтобы он мгновенно исчез из списка
        unset($this->chats);
        // ФИКС: Сбрасываем счетчики, так как непрочитанные обнулились
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
        $this->activeMessages = null;

        // ФИКС: Сбрасываем кэш списка чатов, чтобы он мгновенно исчез из архива
        unset($this->chats);
        // ФИКС: Сбрасываем счетчики, так как непрочитанные обнулились
        unset($this->stats); 

        $this->dispatch('show-toast', type: 'success', message: 'Тикет возвращен из архива.');
    }

    // ФИКС: Универсальный переключатель фильтров
    public function setFilter(string $filter): void
    {
        $this->chatFilter = $filter;
        $this->activeChatId = null;
        $this->activeMessages = null;
        $this->resetPage();

        // Сбрасываем кэш списка при переключении
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
            // ФИКС: Фильтруем архив/активные
            ->whereHas('participants', fn($q) => $q->where('user_id', auth()->id())->where('is_hidden', $this->chatFilter === 'archived'))
            // ФИКС: Дополнительный фильтр для непрочитанных
            ->when($this->chatFilter === 'unread', function ($q) {
                $q->whereHas('participants', fn($sub) => $sub->where('user_id', auth()->id())->where('unread_count', '>', 0));
            })
            ->when($this->search, function ($query) use ($operator) {
                $query->where(function ($q) use ($operator) {
                    $q->whereHas('participants.user', fn($sub) => $sub->where('name', $operator, "%{$this->search}%"));
                    if (is_numeric($this->search)) {
                        $q->orWhere('id', (int) $this->search);
                    }
                });
            })
            ->with(['participants.user.photos' => $avatarQuery, 'messages' => fn($q) => $q->latest()->limit(1)])
            ->orderByDesc('last_message_at')
            ->paginate(15);
    }

    #[Computed]
    public function stats(): array
    {
        // 1 запрос на все счетчики сразу! Никаких N+1.
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
        return [
            'chats' => $this->chats,
            'stats' => $this->stats,
        ];
    }
};?>

<div class="space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-4">
                @php
                    $previousUrl = url()->previous();
                    $backUrl =
                        $previousUrl && $previousUrl !== url()->current() ? $previousUrl : route('admin.dashboard');
                @endphp

                <a href="{{ $backUrl }}" wire:navigate
                    class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors">
                    <x-lucide-arrow-left class="w-5 h-5" />
                </a>
                <div>
                    <h1 class="text-2xl font-semibold flex items-center gap-2 flex-wrap">
                        <x-lucide-life-buoy class="w-6 h-6" />
                        Чат поддержки                       
                         @if ($this->stats['active'] > 0)
                            <x-ui.badge variant="default" size="sm">{{ $this->stats['active'] }} активных</x-ui.badge>
                        @endif
                        @if ($this->stats['unread'] > 0)
                            <x-ui.badge variant="destructive" size="sm">{{ $this->stats['unread'] }} требуют ответа</x-ui.badge>
                        @endif
                    </h1>
                    <p class="text-sm text-muted-foreground">Служебный чат администрации</p>
                </div>
            </div>

        <x-ui.button wire:click="$set('showNewTicketModal', true)" variant="default" size="sm">
            <x-lucide-plus class="w-4 h-4" /> Написать пользователю
        </x-ui.button>
    </div>

    <div
        class="grid grid-cols-1 lg:grid-cols-3 gap-6 bg-card border border-border rounded-lg p-4 min-h-[calc(100vh-16rem)]">

        <!-- Левая панель: Тикеты -->
       <div wire:poll.10s class="lg:col-span-1 border-r border-border pr-4 flex flex-col h-[calc(100vh-16rem)]">

                       <!-- ФИКС: 3 кнопки переключения со счетчиками -->
            <div class="flex gap-1.5 mb-3 shrink-0">
                <x-ui.button wire:click="setFilter('active')" variant="{{ $chatFilter === 'active' ? 'default' : 'secondary' }}" size="sm" class="flex-1">
                    Актив. <x-ui.badge size="xs" class="ml-1 {{ $chatFilter === 'active' ? 'bg-primary-foreground/20' : 'bg-muted-foreground/10' }}">{{ $this->stats['active'] }}</x-ui.badge>
                </x-ui.button>
                
                <x-ui.button wire:click="setFilter('unread')" variant="{{ $chatFilter === 'unread' ? 'default' : 'secondary' }}" size="sm" class="flex-1">
                    <span class="flex items-center justify-center gap-1">
                        Непроч.
                        @if($this->stats['unread'] > 0)
                            <x-ui.badge variant="destructive" size="xs">{{ $this->stats['unread'] }}</x-ui.badge>
                        @endif
                    </span>
                </x-ui.button>
                
                <x-ui.button wire:click="setFilter('archived')" variant="{{ $chatFilter === 'archived' ? 'default' : 'secondary' }}" size="sm" class="flex-1">
                    Архив <x-ui.badge size="xs" class="ml-1 {{ $chatFilter === 'archived' ? 'bg-primary-foreground/20' : 'bg-muted-foreground/10' }}">{{ $this->stats['archived'] }}</x-ui.badge>
                </x-ui.button>
            </div>

            <div class="relative mb-3 shrink-0">
                <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground z-10" />
                <x-ui.input wire:model.live.debounce.300ms="search" type="search"
                    placeholder="Поиск по имени или ID..." class="pl-9" />
            </div>

            <div class="flex-1 min-h-0 overflow-y-auto space-y-2 pr-1 little-scroll">
                @forelse ($this->chats as $chat)
                    @php
                        // Получаем запись участника (админа) для этого чата
                        $myParticipant = $chat->participants->firstWhere('user_id', auth()->id());
                        $unreadCount = $myParticipant?->unread_count ?? 0;

                        $partner = $chat->participants->firstWhere('user_id', '!=', auth()->id())?->user;
                        $lastMsg = $chat->messages->first();
                    @endphp

                    <div wire:click="selectChat({{ $chat->id }})"
                        class="p-3 rounded-lg cursor-pointer transition-colors relative overflow-hidden {{ $this->activeChatId === $chat->id ? 'bg-primary/10 border border-primary/30' : ($unreadCount > 0 ? 'bg-blue-500/5 border border-blue-500/30 hover:bg-blue-500/10' : 'bg-muted/30 hover:bg-muted border border-transparent') }}"
                        wire:key="sup-chat-{{ $chat->id }}">

                        <!-- Полоса слева, если есть непрочитанные -->
                        @if ($unreadCount > 0)
                            <div class="absolute left-0 top-0 bottom-0 w-1 bg-blue-500"></div>
                        @endif

                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <x-avatar src="{{ $partner?->avatar_url }}" name="{{ $partner?->name }}"
                                    size="sm" userId="{{ $partner?->id }}" showStatus="true" :isOnline="$partner?->is_online" />
                                @if ($unreadCount > 0)
                                    <!-- Точка на аватарке -->
                                    <span
                                        class="absolute bottom-0 right-0 block h-3 w-3 rounded-full bg-blue-500 border-2 border-card"></span>
                                @endif
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-center gap-2">
                                    <div class="flex items-center gap-1 min-w-0">
                                        <x-user-status-sign :user="$partner" />
                                        <!-- Имя жирным, если есть непрочитанные -->
                                        <span
                                            class="text-sm truncate {{ $unreadCount > 0 ? 'font-bold text-blue-600 dark:text-blue-400' : 'font-medium' }}">{{ $partner?->name ?? 'Удален' }}</span>
                                        @if ($partner?->has_active_premium)
                                            <x-lucide-crown class="w-3 h-3 text-yellow-500" />
                                        @endif
                                        @if ($partner?->status === 'banned')
                                            <x-ui.badge variant="destructive" size="xs">Бан</x-ui.badge>
                                        @endif
                                    </div>

                                    <div class="flex flex-col items-end gap-1 shrink-0">
                                        @if ($chat->last_message_at)
                                            <span
                                                class="text-[10px] {{ $unreadCount > 0 ? 'text-blue-500 font-medium' : 'text-muted-foreground' }}">{{ $chat->last_message_at->diffForHumans() }}</span>
                                        @endif
                                        <!-- Бейдж с количеством непрочитанных -->
                                        @if ($unreadCount > 0)
                                            <span
                                                class="bg-blue-500 text-white text-[10px] font-bold rounded-full h-5 min-w-[20px] flex items-center justify-center px-1">
                                                {{ $unreadCount }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <p
                                    class="text-xs truncate mt-1 {{ $unreadCount > 0 ? 'text-foreground font-medium' : 'text-muted-foreground' }}">
                                    @if ($lastMsg)
                                        {{ Str::limit($lastMsg->body, 30) }}
                                    @else
                                        <span class="italic">Нет сообщений</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12 text-muted-foreground text-sm">
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
            <div class="shrink-0 pt-4 mt-2 border-t border-border">
                {{ $this->chats->links('partials.pagination') }}
            </div>
        </div>

        <!-- Правая панель: Переписка -->
        <div class="lg:col-span-2 flex flex-col bg-muted/10 rounded-lg px-4 pt-4 h-[calc(100vh-16rem)] overflow-hidden">
            @if ($this->activeChatId)
                @php
                    $avatarQuery = fn($q) => $q
                        ->select([
                            'id',
                            'user_id',
                            'is_primary',
                            'status',
                            'path_thumb',
                            'path_medium',
                            'path_large',
                            'path_original',
                        ])
                        ->orderByDesc('is_primary')
                        ->limit(1);
                    $chat = Chat::with(['participants.user.photos' => $avatarQuery])->find($this->activeChatId);
                    $partner = $chat?->participants->firstWhere('user_id', '!=', auth()->id())?->user;
                @endphp

                <!-- Шапка чата -->
                <div class="shrink-0 flex items-center justify-between border-b border-border pb-3 mb-4">
                    <div class="flex items-center gap-3">
                        <x-avatar src="{{ $partner?->avatar_url }}" name="{{ $partner?->name }}" size="sm"
                            userId="{{ $partner?->id }}" showStatus="true" :isOnline="$partner?->is_online" />
                        <div class="flex flex-col">
                            <div class="flex items-center gap-1">
                                <x-user-status-sign :user="$partner" />
                                <a href="{{ route('admin.users.show', $partner?->id) }}" wire:navigate
                                    class="hover:text-primary font-medium text-sm">{{ $partner?->name ?? 'Удален' }}</a>
                                @if ($partner?->has_active_premium)
                                    <x-lucide-crown class="w-3 h-3 text-yellow-500" />
                                @endif
                                <span class="text-xs text-muted-foreground">(ID: {{ $partner?->id }})</span>
                            </div>
                            <div class="text-xs text-muted-foreground">{{ $partner?->email ?? '-' }}</div>
                        </div>
                    </div>

                    <!-- ФИКС: Кнопка меняется в зависимости от того, архив это или нет -->
                    @if ($chatFilter === 'archived')
                        <x-ui.button wire:click="unarchiveChat({{ $chat->id }})"
                            wire:confirm="Вернуть тикет из архива?" variant="ghost" size="icon-sm"
                            wire:target="unarchiveChat({{ $chat->id }})" title="Вернуть из архива">
                            <x-lucide-archive-restore class="w-4 h-4 text-success" wire:loading.remove
                                wire:target="unarchiveChat({{ $chat->id }})" />
                            <x-lucide-loader-2 class="w-4 h-4 animate-spin hidden" wire:loading
                                wire:target="unarchiveChat({{ $chat->id }})" />
                        </x-ui.button>
                    @else
                        <x-ui.button wire:click="archiveChat({{ $chat->id }})" wire:confirm="Архивировать тикет?"
                            variant="ghost" size="icon-sm" wire:target="archiveChat({{ $chat->id }})"
                            title="Архивировать">
                            <x-lucide-archive class="w-4 h-4 text-muted-foreground" wire:loading.remove
                                wire:target="archiveChat({{ $chat->id }})" />
                            <x-lucide-loader-2 class="w-4 h-4 animate-spin hidden" wire:loading
                                wire:target="archiveChat({{ $chat->id }})" />
                        </x-ui.button>
                    @endif
                </div>

                <!-- Лента сообщений -->
                <div wire:poll.10s="loadActiveMessages" x-data x-init="setTimeout(() => { $el.scrollTop = $el.scrollHeight; }, 50);
                $watch('$wire.activeChatId', () => { $nextTick(() => { setTimeout(() => { $el.scrollTop = $el.scrollHeight; }, 50); }); });"
                    class="flex-1 min-h-0 overflow-y-auto gap-4 pr-2 little-scroll flex flex-col">
                    @forelse ($activeMessages as $msg)
                        @php $isMe = $msg->sender_id === auth()->id(); @endphp
                        <div class="flex items-end gap-2 {{ $isMe ? 'justify-end' : 'justify-start' }}"
                            wire:key="msg-{{ $msg->id }}">
                            @if (!$isMe)
                                <x-avatar src="{{ $partner?->avatar_url }}" name="{{ $partner?->name }}"
                                    size="xs" />
                            @endif
                            <div class="max-w-[80%]">
                                <div
                                    class="text-left whitespace-pre-line break-words {{ $isMe ? 'bg-primary text-primary-foreground' : 'bg-muted text-foreground' }} rounded-2xl px-4 py-2 text-sm">
                                    {{ trim($msg->body) }}</div>
                                <div
                                    class="text-[0.65rem] text-muted-foreground mt-1 {{ $isMe ? 'text-right' : 'text-left' }}">
                                    {{ $msg->created_at->format('d.m H:i') }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-sm text-muted-foreground py-8">Начните диалог.</div>
                    @endforelse
                </div>

                <!-- Блок отправки -->
                <div class="shrink-0 mt-4 space-y-1">
                    <div class="relative flex-1">
                        <x-ui.context-menu class="w-full">
                            <x-ui.context-menu-trigger class="block w-full">
                                <x-ui.textarea x-data @focus-message-input.window="$el.focus()"
                                    wire:model="messageBody"
                                    @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.sendMessage(); }"
                                    placeholder="Введите сообщение... (Enter - отправить, Shift+Enter - новая строка)"
                                    rows="3"
                                    class="bg-card w-full resize-none max-h-64 pr-12 pb-3 border border-border rounded-lg focus:outline-none" />
                            </x-ui.context-menu-trigger>

                            <x-ui.context-menu-content class="w-80" x-data="{ search: '', templates: {{ json_encode($this->flatTemplates) }} }">
                                <div class="px-4 py-2" @click.stop @contextmenu.prevent>
                                    <input type="text" x-model="search" placeholder="Поиск шаблона..."
                                        class="w-full px-2 py-1.5 text-sm border border-border rounded bg-card focus:outline-none focus:ring-1 focus:ring-primary"
                                        autocomplete="off">
                                </div>
                                <x-ui.context-menu-separator />
                                <div class="max-h-60 overflow-y-auto p-3 little-scroll">
                                    <template x-for="(template, index) in templates" :key="index">
                                        <div>
                                            <div x-show="search === '' && (index === 0 || templates[index-1].category !== template.category)"
                                                class="px-2 py-1.5 mt-1 text-[10px] font-bold uppercase text-muted-foreground/70">
                                                <span x-text="template.category"></span>
                                            </div>
                                            <x-ui.context-menu-item
                                                x-show="template.title.toLowerCase().includes(search.toLowerCase()) || template.category.toLowerCase().includes(search.toLowerCase())"
                                                @click="$wire.setTemplate(template.index)">
                                                <span x-text="template.title"></span>
                                            </x-ui.context-menu-item>
                                        </div>
                                    </template>
                                    <div x-show="!templates.some(t => t.title.toLowerCase().includes(search.toLowerCase()) || t.category.toLowerCase().includes(search.toLowerCase()))"
                                        class="px-2 py-4 text-center text-xs text-muted-foreground">
                                        Ничего не найдено
                                    </div>
                                </div>
                            </x-ui.context-menu-content>
                        </x-ui.context-menu>

                        <x-ui.button wire:click="sendMessage" wire:target="sendMessage" wire:loading.attr="disabled"
                            size="icon" class="absolute bottom-3.5 right-3.5 shadow-md rounded-lg">
                            <x-lucide-send class="w-4 h-4" wire:loading.remove wire:target="sendMessage" />
                            <x-lucide-loader-2 class="w-4 h-4 animate-spin hidden" wire:loading
                                wire:target="sendMessage" />
                        </x-ui.button>
                    </div>
                    <p class="text-xs text-muted-foreground flex items-center gap-1">
                        <x-lucide-mouse-pointer-click class="w-3 h-3" />
                        Правый клик по полю ввода для вставки шаблона.
                    </p>
                </div>
            @else
                <div class="flex-1 flex flex-col items-center justify-center text-muted-foreground">
                    <x-lucide-inbox class="w-16 h-16 mb-4 opacity-20" />
                    <h3 class="text-lg font-medium">Выберите тикет</h3>
                </div>
            @endif
        </div>
    </div>

    <!-- Модалка создания нового тикета -->
    @if ($showNewTicketModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
            wire:click.self="$set('showNewTicketModal', false)">
            <div class="bg-card border border-border rounded-lg shadow-2xl max-w-md w-full p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Найти пользователя</h2>
                    <button wire:click="$set('showNewTicketModal', false)"
                        class="text-muted-foreground hover:text-foreground transition-colors">
                        <x-lucide-x class="w-5 h-5" />
                    </button>
                </div>
                <livewire:user-search />
                <p class="text-xs text-muted-foreground">Найдите пользователя, чтобы начать чат поддержки.</p>
            </div>
        </div>
    @endif
</div>
