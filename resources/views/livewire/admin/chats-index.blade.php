<?php

use App\Models\Chat;
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use App\Notifications\ChatDeletedByAdmin;

/**
 * Компонент админки: Чаты пользователей.
 * Отображает список приватных чатов, фильтры по дате и типу, поиск и переписку.
 */
new #[Layout('layouts.admin')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $dateFilter = 'all';
    public string $typeFilter = 'all'; // all, match, paywall
    public int $perPage = 10;
    public ?int $activeChatId = null;
    public string $deleteReason = 'нарушение правил сайта';

    /**
     * Инициализация. Восстанавливаем фильтры из сессии.
     */
    public function mount()
    {
        $saved = session('admin_chats', []);

        if (isset($saved['dateFilter'])) {
            $this->dateFilter = $saved['dateFilter'];
        }
        if (isset($saved['typeFilter'])) {
            $this->typeFilter = $saved['typeFilter'];
        }
    }

   public function updatingSearch(): void
    {
        $this->resetPage();
        $this->activeChatId = null; // ✅ Сбрасываем открытый чат
    }

    public function setDateFilter(string $filter): void
    {
        $this->dateFilter = $filter;
        session(['admin_chats.dateFilter' => $filter]);
        $this->resetPage();
        $this->activeChatId = null; // ✅ Сбрасываем открытый чат
    }

    public function setTypeFilter(string $filter): void
    {
        $this->typeFilter = $filter;
        session(['admin_chats.typeFilter' => $filter]);
        $this->resetPage();
        $this->activeChatId = null; // ✅ Сбрасываем открытый чат
    }

    public function selectChat(int $chatId): void
    {
        $this->activeChatId = $chatId;
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'dateFilter', 'typeFilter']);
        session()->forget('admin_chats');
        $this->resetPage();
    }

    #[Computed]
    public function chats()
    {
        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
        $search = trim($this->search);

        return Chat::query()
            ->where('type', 'private')
            ->with([
                'user1.photos',
                'user2.photos',
                'match',
                'messages' => function ($q) {
                    $q->latest()->limit(1);
                },
            ])
            ->when($search, function ($query) use ($search, $operator) {
                $query->where(function ($q) use ($search, $operator) {
                    $q->whereHas('user1', fn($q2) => $q2->where('name', $operator, "%{$search}%"))->orWhereHas('user2', fn($q2) => $q2->where('name', $operator, "%{$search}%"));
                });
            })
            ->when($this->dateFilter !== 'all', function ($query) {
                $date = match ($this->dateFilter) {
                    'day' => now()->startOfDay(),
                    'week' => now()->startOfWeek(),
                    'month' => now()->startOfMonth(),
                    default => null,
                };
                if ($date) {
                    $query->where('last_message_at', '>=', $date);
                }
            })
            // Фильтр по метчам
            ->when($this->typeFilter === 'match', function ($query) {
                $query->whereExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                             ->from('user_matches')
                             ->whereColumn('user_matches.user1_id', 'chats.user1_id')
                             ->whereColumn('user_matches.user2_id', 'chats.user2_id');
                });
            })
            // Фильтр по пейволу (есть ли в чате системное сообщение)
            ->when($this->typeFilter === 'paywall', fn($q) => $q->whereHas('messages', fn($q2) => $q2->where('type', 'system')))
            ->orderByDesc('last_message_at')
            ->paginate($this->perPage);
    }

        #[Computed]
    public function counts()
    {
        return [
            'total' => Chat::where('type', 'private')->count(),
            'week' => Chat::where('type', 'private')->where('last_message_at', '>=', now()->startOfWeek())->count(),
            'match' => Chat::where('type', 'private')->whereExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('user_matches')
                      ->whereColumn('user_matches.user1_id', 'chats.user1_id')
                      ->whereColumn('user_matches.user2_id', 'chats.user2_id');
            })->count(),
            'paywall' => Chat::where('type', 'private')->whereHas('messages', fn($q) => $q->where('type', 'system'))->count(),
        ];
    }
    #[Computed]
    public function activeChatMessages()
    {
        if (!$this->activeChatId) {
            return null;
        }
        return Chat::with(['user1.photos', 'user2.photos', 'match', 'messages.sender'])->find($this->activeChatId);
    }

    public function deleteChat(int $chatId): void
    {
        $chat = Chat::with(['user1', 'user2'])->find($chatId);
        if ($chat) {
            $user1 = $chat->user1;
            $user2 = $chat->user2;

            DB::transaction(function () use ($chat, $user1, $user2) {
                if ($user1) {
                    $user1->notify(new ChatDeletedByAdmin($this->deleteReason));
                }
                if ($user2) {
                    $user2->notify(new ChatDeletedByAdmin($this->deleteReason));
                }
                $chat->delete();
            });

            if ($this->activeChatId === $chatId) {
                $this->activeChatId = null;
            }
            $this->reset('deleteReason');
            $this->dispatch('show-toast', type: 'success', message: 'Чат удален. Пользователи уведомлены.');
            $this->dispatch('$refresh');
        }
    }
};
?>

<div class="space-y-6">
    <h1 class="text-2xl font-semibold flex items-center gap-2">
        <x-lucide-messages-square class="w-6 h-6" />
        Чаты пользователей
        @if ($this->counts['week'] > 0)
            <x-ui.badge variant="info" size="sm">{{ $this->counts['week'] }} за неделю</x-ui.badge>
        @endif
    </h1>

    <div class="flex items-center justify-between flex-wrap gap-4">
        <!-- Фильтры по дате (старые кнопки) -->
        <div class="flex flex-wrap gap-1.5">
            <x-ui.button wire:click="setDateFilter('all')" variant="{{ $dateFilter === 'all' ? 'default' : 'secondary' }}"
                size="sm" wire:key="filter-all">
                За всё время
            </x-ui.button>
            <x-ui.button wire:click="setDateFilter('day')"
                variant="{{ $dateFilter === 'day' ? 'default' : 'secondary' }}" size="sm" wire:key="filter-day">
                Сегодня
            </x-ui.button>
            <x-ui.button wire:click="setDateFilter('week')"
                variant="{{ $dateFilter === 'week' ? 'default' : 'secondary' }}" size="sm" wire:key="filter-week">
                На неделе
            </x-ui.button>
            <x-ui.button wire:click="setDateFilter('month')"
                variant="{{ $dateFilter === 'month' ? 'default' : 'secondary' }}" size="sm"
                wire:key="filter-month">
                В этом месяце
            </x-ui.button>
        </div>

        <div class="flex items-center gap-3">
            <!-- Фильтры по типу (Новые кнопки) -->
            <div class="flex flex-wrap gap-1.5">
                <x-ui.button wire:click="setTypeFilter('all')"
                    variant="{{ $typeFilter === 'all' ? 'default' : 'secondary' }}" size="sm" wire:key="type-all">
                    Все <x-ui.badge size="xs">{{ $this->counts['total'] }}</x-ui.badge>
                </x-ui.button>
                <x-ui.button wire:click="setTypeFilter('match')"
                    variant="{{ $typeFilter === 'match' ? 'default' : 'secondary' }}" size="sm"
                    wire:key="type-match">
                    По метчам <x-ui.badge size="xs" variant="success">{{ $this->counts['match'] }}</x-ui.badge>
                </x-ui.button>
                <x-ui.button wire:click="setTypeFilter('paywall')"
                    variant="{{ $typeFilter === 'paywall' ? 'default' : 'secondary' }}" size="sm"
                    wire:key="type-paywall">
                    Ожидают Premium <x-ui.badge size="xs"
                        variant="destructive">{{ $this->counts['paywall'] }}</x-ui.badge>
                </x-ui.button>
            </div>

            <!-- Поиск -->
            <div class="relative w-72">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск по имени..."
                    class="pl-9 pr-8" />
                <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                @if (!empty($search))
                    <button wire:click="resetFilters"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                @endif
            </div>
        </div>
    </div>



    <!-- Интерфейс чата (Список + Переписка) -->
    <div
        class="grid grid-cols-1 lg:grid-cols-3 gap-6 bg-card border border-border rounded-lg p-4 min-h-[calc(100vh-20rem)]">

        <!-- Левая панель: Список чатов -->
        <div
            class="lg:col-span-1 border-r border-border pr-4 space-y-2 overflow-y-auto little-scroll max-h-[calc(100vh-17rem)]">
            @forelse ($this->chats as $chat)
                @php
                    $u1 = $chat->user1;
                    $u2 = $chat->user2;
                    $lastMsg = $chat->messages->first();
                @endphp

                <div wire:click="selectChat({{ $chat->id }})"
                    class="p-3 rounded-lg cursor-pointer transition-colors {{ $this->activeChatId === $chat->id ? 'bg-primary/10 border border-primary/30' : 'hover:bg-muted/50 border border-transparent' }}"
                    wire:key="chat-list-{{ $chat->id }}">
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <x-avatar src="{{ $u1?->avatar_url }}" name="{{ $u1?->name }}" size="sm"
                                userId="{{ $u1?->id }}" showStatus="true" />
                            <x-avatar src="{{ $u2?->avatar_url }}" name="{{ $u2?->name }}" size="sm"
                                userId="{{ $u2?->id }}" showStatus="true"
                                class="absolute -bottom-2 -right-2 w-6 h-6 rounded-full border-2 border-card" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-center">
                                <span class="font-medium text-sm truncate flex items-center gap-1">
                                    {{ $u1?->name }} & {{ $u2?->name }}
                                    @if ($chat->match)
                                        <x-lucide-heart class="w-3 h-3 text-pink-500 fill-current" />
                                    @endif
                                </span>
                                @if ($chat->last_message_at)
                                    <span
                                        class="text-[10px] text-muted-foreground whitespace-nowrap ml-2">{{ $chat->last_message_at->diffForHumans() }}</span>
                                @endif
                            </div>
                            <p class="text-xs text-muted-foreground truncate mt-0.5">
                                @if ($lastMsg)
                                    @if ($lastMsg->type === 'system')
                                        <span class="text-destructive font-medium">🔒 Требуется Premium</span>
                                    @else
                                        {{ $lastMsg->body }}
                                    @endif
                                @else
                                    <span class="italic">Нет сообщений</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-12 text-muted-foreground text-sm" wire:key="empty-chats">
                    <p>Чаты не найдены</p>
                </div>
            @endforelse

            <div class="pt-4" wire:key="pagination-chats">
                {{ $this->chats->links('partials.pagination') }}
            </div>
        </div>

        <!-- Правая панель: Переписка -->
        <div class="lg:col-span-2 flex flex-col bg-muted/10 rounded-lg p-4">
            @if ($this->activeChatMessages)
                @php $chat = $this->activeChatMessages; @endphp

                <!-- Шапка чата -->
                <div class="flex items-start justify-between border-b border-border pb-3 mb-4">
                    <div class="flex items-center gap-8">
                        <!-- User 1 -->
                        <div class="flex items-center gap-3">
                            <x-avatar src="{{ $chat->user1?->avatar_url }}" name="{{ $chat->user1?->name }}"
                                size="sm" userId="{{ $chat->user1?->id }}" showStatus="true" />
                            <div class="text-sm">
                                <a href="{{ route('admin.users.show', $chat->user1_id) }}"
                                    class="hover:text-primary font-medium flex items-center gap-1">
                                    {{ $chat->user1?->name }}
                                    @if ($chat->user1?->is_premium)
                                        <x-ui.badge variant="warning" size="xs">Premium</x-ui.badge>
                                    @endif
                                </a>
                                <div class="text-xs text-muted-foreground">ID: {{ $chat->user1?->id }} •
                                    {{ $chat->user1?->email }}</div>
                            </div>
                        </div>

                        <span class="text-muted-foreground">&</span>

                        <!-- User 2 -->
                        <div class="flex items-center gap-3">
                            <x-avatar src="{{ $chat->user2?->avatar_url }}" name="{{ $chat->user2?->name }}"
                                size="sm" userId="{{ $chat->user2?->id }}" showStatus="true" />
                            <div class="text-sm">
                                <a href="{{ route('admin.users.show', $chat->user2_id) }}"
                                    class="hover:text-primary font-medium flex items-center gap-1">
                                    {{ $chat->user2?->name }}
                                    @if ($chat->user2?->is_premium)
                                        <x-ui.badge variant="warning" size="xs">Premium</x-ui.badge>
                                    @endif
                                </a>
                                <div class="text-xs text-muted-foreground">ID: {{ $chat->user2?->id }} •
                                    {{ $chat->user2?->email }}</div>
                            </div>
                        </div>

                        @if ($chat->match)
                            <x-ui.badge variant="info" size="xs" class="ml-2">Взаимный лайк</x-ui.badge>
                        @endif
                    </div>

                    <!-- Кнопка удаления -->
                    <x-ui.alert-dialog wire:key="delete-chat-{{ $chat->id }}">
                        <x-ui.alert-dialog-trigger>
                            <x-ui.button variant="destructive" size="icon-sm" wire:loading.attr="disabled"
                                wire:target="deleteChat({{ $chat->id }})">
                                <x-lucide-trash-2 class="w-4 h-4" />
                            </x-ui.button>
                        </x-ui.alert-dialog-trigger>
                        <x-ui.alert-dialog-content>
                            <x-ui.alert-dialog-header>
                                <x-ui.alert-dialog-title>Удалить чат?</x-ui.alert-dialog-title>
                                <x-ui.alert-dialog-description>
                                    Вся переписка будет удалена безвозвратно. <br>
                                    Выберите причину удаления:
                                </x-ui.alert-dialog-description>
                            </x-ui.alert-dialog-header>
                            <div class="py-2 space-y-2">
                                <x-ui.select wire:model="deleteReason">
                                    <x-ui.select-trigger><x-ui.select-value
                                            placeholder="Выберите причину..." /></x-ui.select-trigger>
                                    <x-ui.select-content>
                                        <x-ui.select-item value="нарушение правил сайта" wire:key="reason-1">Нарушение
                                            правил сайта</x-ui.select-item>
                                        <x-ui.select-item value="спам или реклама" wire:key="reason-2">Спам или
                                            реклама</x-ui.select-item>
                                        <x-ui.select-item value="оскорбительное поведение"
                                            wire:key="reason-3">Оскорбительное поведение</x-ui.select-item>
                                        <x-ui.select-item value="мошенничество"
                                            wire:key="reason-4">Мошенничество</x-ui.select-item>
                                        <x-ui.select-item value="пропаганда наркотиков" wire:key="reason-5">Пропаганда
                                            наркотиков</x-ui.select-item>
                                    </x-ui.select-content>
                                </x-ui.select>
                            </div>
                            <x-ui.alert-dialog-footer>
                                <x-ui.alert-dialog-cancel>Отмена</x-ui.alert-dialog-cancel>
                                <x-ui.alert-dialog-action wire:click="deleteChat({{ $chat->id }})">Удалить и
                                    уведомить</x-ui.alert-dialog-action>
                            </x-ui.alert-dialog-footer>
                        </x-ui.alert-dialog-content>
                    </x-ui.alert-dialog>
                </div>

                <!-- Лента сообщений -->
                <div x-data x-init="setTimeout(() => { $el.scrollTop = $el.scrollHeight; }, 50)"
                    class="flex-1 overflow-y-auto space-y-4 pr-2 max-h-[calc(100vh-23rem)] flex flex-col little-scroll">
                    @php $messages = $chat->messages->reverse(); @endphp
                    @foreach ($messages as $message)
                        @if ($message->type === 'system')
                            <div class="flex justify-center" wire:key="msg-{{ $message->id }}">
                                <div
                                    class="bg-destructive/10 text-destructive text-xs font-medium px-4 py-2 rounded-lg text-center max-w-md border border-destructive/20">
                                    🔒 {{ $message->body }}
                                </div>
                            </div>
                        @else
                            @php
                                $isUser1 = $message->sender_id === $chat->user1_id;
                                $sender = $isUser1 ? $chat->user1 : $chat->user2;
                            @endphp
                            <div class="flex items-end gap-2 {{ $isUser1 ? 'justify-start' : 'justify-end' }}"
                                wire:key="msg-{{ $message->id }}">
                                @if ($isUser1)
                                    <x-avatar src="{{ $sender?->avatar_url }}" name="{{ $sender?->name }}"
                                        size="xs" userId="{{ $sender?->id }}" showStatus="true" />
                                @endif
                                <div class="max-w-[70%]">
                                    <div
                                        class="{{ $isUser1 ? 'bg-muted text-foreground' : 'bg-primary text-primary-foreground' }} rounded-2xl px-4 py-2 text-sm">
                                        {{ $message->body }}
                                    </div>
                                    <div
                                        class="text-[10px] text-muted-foreground mt-1 {{ $isUser1 ? 'text-left' : 'text-right' }}">
                                        {{ $message->created_at->format('d.m H:i') }}
                                    </div>
                                </div>
                                @if (!$isUser1)
                                    <x-avatar src="{{ $sender?->avatar_url }}" name="{{ $sender?->name }}"
                                        size="xs" userId="{{ $sender?->id }}" showStatus="true" />
                                @endif
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
