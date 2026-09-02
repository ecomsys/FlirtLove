<?php

use App\Models\Chat;
use App\Actions\Admin\ManageChatsAction;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    /** @var string Поиск по имени или ID чата */
    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** @var int|null ID активного чата для просмотра переписки */
    #[Url(as: 'chat', except: '')]
    public ?int $activeChatId = null;

    /** @var string Фильтр блокировки чата (all, locked, unlocked) */
    #[Url(as: 'lock', except: 'all')]
    public string $lockFilter = 'all';

    /** @var string URL для кнопки "Назад" */
    public string $backUrl = '';

        public function mount(): void
    {
        $previousUrl = url()->previous();
        $this->backUrl = ($previousUrl && $previousUrl !== url()->current()) 
            ? $previousUrl 
            : route('admin.dashboard');

        // ФИКС: Читаем напрямую из Request, так как при wire:navigate Livewire 3 может еще не успеть гидратировать #[Url]
        $qParam = request()->query('q', '');
        $chatParam = request()->query('chat', '');

        // Если пришли по прямой ссылке ?chat=123
        if (!empty($chatParam)) {
            $chat = Chat::find((int) $chatParam);
            if ($chat) {
                $this->activeChatId = $chat->id;
                $this->search = (string) $chat->id; // Подставляем в поле поиска
                $this->lockFilter = $chat->is_locked ? 'locked' : 'unlocked';
                return;
            }
        } 
        
        // Если пришли по ссылке с поиском ?q=123
        if (!empty($qParam) && is_numeric($qParam)) {
            $chat = Chat::find((int) $qParam);
            if ($chat) {
                $this->activeChatId = $chat->id;
                $this->search = (string) $qParam; // Подставляем в поле поиска
                $this->lockFilter = $chat->is_locked ? 'locked' : 'unlocked';
                return;
            }
        }
    }

    public function updatedLockFilter(): void
    {
        $this->resetPage();
        $this->activeChatId = null;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();

        // Умный поиск: если ввели точный ID чата, автоматически открываем его переписку
        if (is_numeric($this->search) && !empty($this->search)) {
            $chat = Chat::find((int) $this->search);
            if ($chat) {
                $this->activeChatId = $chat->id;
                // ФИКС: Автоматически переключаем фильтр на нужную вкладку, чтобы чат не потерялся
                $this->lockFilter = $chat->is_locked ? 'locked' : 'unlocked';
                return;
            }
        }
        
        $this->activeChatId = null;
    }

    public function setLockFilter(string $status): void
    {
        $this->lockFilter = $status;
        $this->search = ''; // ФИКС: Очищаем поиск при ручной смене фильтра
        $this->resetPage();
        $this->activeChatId = null;
    }

    #[Computed]
    public function chatStats(): array
    {
        $baseQuery = Chat::where('type', 'private')
            ->whereHas('participants', fn($q) => $q->whereHas('user', fn($uq) => $uq->withTrashed()->excludeStaff()));

        $stats = (clone $baseQuery)->selectRaw("COUNT(*) as total")
            ->selectRaw("SUM(CASE WHEN is_locked = true THEN 1 ELSE 0 END) as locked")
            ->first();

        $total = $stats->total ?? 0;
        $locked = $stats->locked ?? 0;

        return [
            'total' => $total,
            'locked' => $locked,
            'unlocked' => $total - $locked,
        ];
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->activeChatId = null;
        $this->resetPage();
    }

    public function selectChat(int $chatId): void
    {
        $this->activeChatId = $chatId;
    }

    
    public function toggleLockChat(int $chatId, ManageChatsAction $action): void
    {
        $chat = Chat::find($chatId);
        if (!$chat) return;

        // Делегируем всю логику в Action
        $isLocked = $action->toggleLock($chat, auth()->user());

        $this->dispatch('show-toast', 
            type: $isLocked ? 'warning' : 'success', 
            message: $isLocked ? 'Чат заблокирован. Общение остановлено.' : 'Чат разблокирован.'
        );
    }


    public function with(): array
    {
        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
        $avatarQuery = fn($q) => $q->select(['id', 'user_id', 'is_primary', 'status', 'path_thumb', 'path_medium', 'path_large', 'path_original'])->orderByDesc('is_primary')->limit(1);

        $chats = Chat::where('type', 'private')
            ->whereHas('participants', fn($q) => $q->whereHas('user', fn($uq) => $uq->withTrashed()->excludeStaff()))
            ->when($this->lockFilter === 'locked', fn($q) => $q->where('is_locked', true))
            ->when($this->lockFilter === 'unlocked', fn($q) => $q->where('is_locked', false))
            ->with([
                'participants' => fn($q) => $q->with(['user' => fn($uq) => $uq->withTrashed()->with(['photos' => $avatarQuery])]), 
                'messages' => fn($q) => $q->latest()->limit(1)
            ])
            ->when($this->search, function ($query) use ($operator) {
                $search = $this->search;
                $query->where(function ($q) use ($search, $operator) {
                    $q->whereHas('participants.user', function ($sub) use ($search, $operator) {
                        $sub->withTrashed()->where('name', $operator, "%{$search}%");
                    });
                    
                    if (is_numeric($search)) {
                        $q->orWhere('id', (int) $search);
                    }
                });
            })
            ->orderByDesc('last_message_at')
            ->paginate(20);

        $activeChat = null;
        if ($this->activeChatId) {
            $activeChat = Chat::with([
                'participants' => fn($q) => $q->with(['user' => fn($uq) => $uq->withTrashed()->with(['photos' => $avatarQuery])]), 
                'messages' => fn($q) => $q->latest()->limit(50)->with(['sender' => fn($sq) => $sq->withTrashed()->with(['photos' => $avatarQuery])]) 
            ])->find($this->activeChatId);
        }

        return [
            'chats' => $chats,
            'activeChat' => $activeChat,
        ];
    }
}; 
?>

<div class="space-y-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
          <div class="flex items-center gap-4">
            <a href="{{ $backUrl }}" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-semibold flex items-center gap-2">
                    <x-lucide-message-circle class="w-6 h-6" />
                    Чаты пользователей
                </h1>
                <p class="text-sm text-muted-foreground">Просмотр и модерация переписок</p>
            </div>
        </div>

         <div class="relative w-72">
            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground z-10" />
            <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск по имени, id чата ..." class="pl-9 pr-8" />
            @if (!empty($search))
                <button wire:click="clearSearch" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground z-10">
                    <x-lucide-x class="w-4 h-4" />
                </button>
            @endif
        </div>
    </div>

    <!-- Интерфейс чата (Список + Переписка) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 bg-card border border-border rounded-lg p-4 min-h-[calc(100vh-16rem)]">

               <!-- Левая панель: Список чатов -->
        <div wire:poll.15s class="lg:col-span-1 border-r border-border pr-4 flex flex-col h-[calc(100vh-16rem)]">

                       <!-- ФИЛЬТР БЛОКИРОВКИ -->
            <div class="flex gap-1.5 mb-3 shrink-0">
                <x-ui.button title="Все чаты" wire:click="setLockFilter('all')" variant="{{ $lockFilter === 'all' ? 'default' : 'secondary' }}" size="sm" class="flex-1 text-xs">
                    Все <x-ui.badge size="xs" class="ml-1">{{ $this->chatStats['total'] }}</x-ui.badge>
                </x-ui.button>
                <x-ui.button title="Активные чаты" wire:click="setLockFilter('unlocked')" variant="{{ $lockFilter === 'unlocked' ? 'default' : 'secondary' }}" size="sm" class="flex-1 text-xs">
                    Акт. <x-ui.badge size="xs" class="ml-1">{{ $this->chatStats['unlocked'] }}</x-ui.badge>
                </x-ui.button>
                <x-ui.button title="Заблокированные чаты" wire:click="setLockFilter('locked')" variant="{{ $lockFilter === 'locked' ? 'destructive' : 'secondary' }}" size="sm" class="flex-1 text-xs">
                    <x-lucide-lock class="w-3 h-3 inline mr-1" /><x-ui.badge size="xs" class="ml-1">{{ $this->chatStats['locked'] }}</x-ui.badge>
                </x-ui.button>
            </div>

            <div class="flex-1 min-h-0 overflow-y-auto space-y-2 pr-1 little-scroll">
                @forelse ($chats as $chat)
                    @php 
                        $u1 = $chat->participants->get(0)?->user;
                        $u2 = $chat->participants->get(1)?->user;
                        $lastMsg = $chat->messages->first();
                    @endphp

                    <div wire:click="selectChat({{ $chat->id }})"
                        class="p-2 rounded-lg cursor-pointer transition-colors {{ $this->activeChatId === $chat->id ? 'bg-primary/10 border border-primary/30' : 'bg-muted/30 hover:bg-muted border border-transparent' }} {{ $chat->is_locked ? 'border-destructive/20' : '' }}"
                        wire:key="chat-list-{{ $chat->id }}"
                        x-data="{ isHi: {{ $this->activeChatId === $chat->id ? 'true' : 'false' }} }"
                        x-init="isHi && setTimeout(() => { $el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 200)"
                    >
                        <div class="flex items-center gap-3">
                            <!-- Сдвоенные аватарки с онлайн статусом -->
                            <div class="relative shrink-0 flex flex-col ">
                                <x-avatar src="{{ $u1?->avatar_url }}" name="{{ $u1?->name }}" size="sm"  userId="{{ $u1?->id }}" showStatus="true" :isOnline="$u1?->is_online" />
                                <x-avatar src="{{ $u2?->avatar_url }}" name="{{ $u2?->name }}" size="sm"  userId="{{ $u2?->id }}" showStatus="true" :isOnline="$u2?->is_online" />
                            </div>
                            
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-start gap-1">
                                    <div class="flex flex-col gap-1 min-w-0">
                                        <!-- Юзер 1 (кликабельный) -->
                                        <div class="flex items-center gap-1 min-w-0">
                                            @if($u1)
                                                <x-user-status-sign :user="$u1" class="shrink-0" />
                                                <span class="text-sm font-medium truncate">{{ $u1->name }}</span>
                                                @if($u1->has_active_premium) <x-lucide-crown class="w-3 h-3 text-yellow-500 shrink-0" /> @endif                                                
                                            @else <span class="text-sm text-muted-foreground truncate">Удален</span> @endif
                                        </div>
                                        <p class="text-xs text-muted-foreground truncate mt-1">
                                            @if ($lastMsg)
                                                @if ($lastMsg->type === 'system')
                                                    <span class="text-blue-500 font-medium">Системное</span>
                                                @else
                                                    {{ Str::limit($lastMsg->body, 30) }}
                                                @endif
                                            @else
                                                <span class="italic">Нет сообщений</span>
                                            @endif
                                        </p>
                                        <!-- Юзер 2 (кликабельный) -->
                                        <div class="flex items-center gap-1 min-w-0">
                                            @if($u2)
                                                <x-user-status-sign :user="$u2" class="shrink-0" />
                                                <span class="text-sm font-medium truncate">{{ $u2->name }}</span>
                                                @if($u2->has_active_premium) <x-lucide-crown class="w-3 h-3 text-yellow-500 shrink-0" /> @endif                                               
                                            @else <span class="text-sm text-muted-foreground truncate">Удален</span> @endif
                                        </div>
                                    </div>

                                    <div class="flex flex-col items-end gap-1 shrink-0">
                                        @if ($chat->is_locked) 
                                            <x-ui.badge variant="destructive" size="xs">
                                                <x-lucide-lock class="w-3 h-3 inline mr-0.5" /> Заблок.
                                            </x-ui.badge> 
                                        @endif
                                        @if ($chat->last_message_at)
                                            <span class="text-[10px] text-muted-foreground whitespace-nowrap">{{ $chat->last_message_at->diffForHumans() }}</span>
                                        @endif
                                        @if ($chat->id)
                                            <span class="text-[10px] text-muted-foreground bg-muted p-1 rounded-xs whitespace-nowrap">#{{ $chat->id }}</span>
                                        @endif
                                    </div>
                                </div>                               
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12 text-muted-foreground text-sm">
                        Чаты не найдены
                    </div>
                @endforelse
            </div>

            <div class="shrink-0 pt-4 mt-2 border-t border-border">
                {{ $chats->links('partials.pagination') }}
            </div>
        </div>

        <!-- Правая панель: Переписка -->
        <div class="lg:col-span-2 flex flex-col bg-muted/10 rounded-lg p-4">
            @if ($activeChat)
                @php 
                    $u1 = $activeChat->participants->get(0)?->user;
                    $u2 = $activeChat->participants->get(1)?->user;
                @endphp

                <!-- Шапка чата -->
                <div class="flex items-center justify-between border-b border-border gap-4 pb-3 mb-4">                    
                        <!-- User 1 -->
                        <div class="flex items-center gap-2">
                            <x-avatar src="{{ $u1?->avatar_url }}" name="{{ $u1?->name }}" size="sm" userId="{{ $u1?->id }}" showStatus="true" :isOnline="$u1?->is_online"/>
                            <div class="flex flex-col">
                                <div class="flex items-center gap-1">
                                    @if($u1)
                                        <x-user-status-sign :user="$u1" />
                                        <a href="{{ route('admin.users.show', $u1->id) }}" wire:navigate class="text-sm font-medium hover:text-primary">{{ $u1->name }}</a>
                                        @if($u1->has_active_premium) <x-lucide-crown class="w-3 h-3 text-yellow-500" /> @endif                                        
                                    @else <span class="text-sm text-muted-foreground">Удален</span> @endif
                                </div>
                                 <div class="text-xs text-muted-foreground">{{ $u1?->email ?? '-' }}</div>
                            </div>
                        </div>

                         <!-- Кнопка блокировки -->
                        @if($activeChat->is_locked)
                            <x-ui.button wire:click="toggleLockChat({{ $activeChat->id }})" wire:target="toggleLockChat({{ $activeChat->id }})" variant="success" size="sm" wire:confirm="Разблокировать чат?">
                                <x-lucide-unlock class="w-4 h-4" wire:loading.remove wire:target="toggleLockChat({{ $activeChat->id }})" />
                                <x-lucide-loader-2 class="w-4 h-4 animate-spin hidden" wire:loading wire:target="toggleLockChat({{ $activeChat->id }})" />
                                Разблокировать
                            </x-ui.button>
                        @else
                            <x-ui.button wire:click="toggleLockChat({{ $activeChat->id }})" wire:target="toggleLockChat({{ $activeChat->id }})" variant="destructive" size="sm" wire:confirm="Заблокировать чат для общения?">
                                <x-lucide-lock class="w-4 h-4" wire:loading.remove wire:target="toggleLockChat({{ $activeChat->id }})" />
                                <x-lucide-loader-2 class="w-4 h-4 animate-spin hidden" wire:loading wire:target="toggleLockChat({{ $activeChat->id }})" />
                                Заблокировать
                            </x-ui.button>
                        @endif

                        <!-- User 2 -->
                        <div class="flex items-center gap-2">
                            <x-avatar src="{{ $u2?->avatar_url }}" name="{{ $u2?->name }}" size="sm" userId="{{ $u2?->id }}" showStatus="true" :isOnline="$u2?->is_online"/>
                            <div class="flex flex-col">
                                <div class="flex items-center gap-1">
                                    @if($u2)
                                        <x-user-status-sign :user="$u2" />
                                        <a href="{{ route('admin.users.show', $u2->id) }}" wire:navigate class="text-sm font-medium hover:text-primary">{{ $u2->name }}</a>
                                        @if($u2->has_active_premium) <x-lucide-crown class="w-3 h-3 text-yellow-500" /> @endif                                        
                                    @else <span class="text-sm text-muted-foreground">Удален</span> @endif
                                </div>
                                 <div class="text-xs text-muted-foreground">{{ $u2?->email ?? '-' }}</div>
                            </div>
                        </div>                    
                </div>

                <!-- Лента сообщений -->
                <div wire:poll.10s 
                    x-data="{ autoScroll: true }"
                    x-init="setTimeout(() => { $el.scrollTop = $el.scrollHeight; }, 50)"
                    @scroll="autoScroll = ($el.scrollHeight - $el.scrollTop - $el.clientHeight < 100)"
                    x-effect="if (autoScroll) { $el.scrollTop = $el.scrollHeight }"
                    class="flex-1 overflow-y-auto space-y-4 pr-2 little-scroll flex flex-col max-h-[calc(100vh-23rem)]">

                    @foreach ($activeChat->messages->sortBy('created_at') as $message)
                        @if ($message->type === 'system')
                            @php
                                // Определяем цвет системного сообщения по тексту
                                $isBlock = str_contains($message->body, 'заблокирован');
                                $isUnblock = str_contains($message->body, 'разблокирован');
                                
                                $msgClasses = $isBlock 
                                    ? 'bg-red-500/10 text-red-600 border-red-500/20' 
                                    : ($isUnblock 
                                        ? 'bg-green-500/10 text-green-600 border-green-500/20' 
                                        : 'bg-blue-500/10 text-blue-600 border-blue-500/20'); // Синий по умолчанию (для "У вас мэтч!" и т.д.)
                            @endphp
                            <div class="flex justify-center" wire:key="msg-{{ $message->id }}">
                                <div class="whitespace-pre-line break-words text-xs font-medium px-4 py-2 rounded-lg text-center max-w-md border {{ $msgClasses }}">{{ $message->body }}</div>
                            </div>
                        @else
                            @php
                                $isUser1 = $message->sender_id === $u1?->id;
                                $sender = $isUser1 ? $u1 : $u2;
                            @endphp
                            <div class="flex items-end gap-2 {{ $isUser1 ? 'justify-start' : 'justify-end' }}" wire:key="msg-{{ $message->id }}">                               
                                <div class="max-w-[70%]">
                                    <div class="text-left whitespace-pre-line break-words {{ $isUser1 ? 'bg-muted text-foreground' : 'bg-primary text-primary-foreground' }} rounded-2xl px-4 py-2 text-sm">{{ $message->body }}
                                        @if($message->isGift())
                                            <div class="mt-2 flex items-center gap-2 bg-background/20 p-2 rounded-lg">
                                                <img src="{{ $message->gift?->image_url }}" class="w-8 h-8 object-contain" alt="Gift">
                                                <span class="text-xs font-medium">Подарок: {{ $message->gift?->name }}</span>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="text-[10px] text-muted-foreground mt-1 {{ $isUser1 ? 'text-left' : 'text-right' }}">
                                        {{ $message->created_at->format('d.m H:i') }}
                                    </div>
                                </div>                               
                            </div>
                        @endif
                    @endforeach
                </div>
            @else
                <div class="flex-1 flex flex-col items-center justify-center text-muted-foreground">
                    <x-lucide-message-circle class="w-16 h-16 mb-4 opacity-20" />
                    <h3 class="text-lg font-medium">Выберите чат</h3>
                    <p class="text-sm">Нажмите на диалог слева, чтобы просмотреть переписку.</p>
                </div>
            @endif
        </div>
    </div>
</div>