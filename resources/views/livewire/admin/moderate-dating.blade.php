<?php

use App\Models\Swipe;
use App\Models\UserMatch;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Компонент модерации знакомств (Свайпы и Матчи).
 * Отвечает за просмотр истории взаимодействий пользователей,
 * фильтрацию, поиск и удаление нежелательных связей (матчей/свайпов).
 */
new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    /** @var string Поисковый запрос по имени пользователя */
    public string $search = '';
    
    /** @var string|null Начальная дата для фильтрации по дате */
    public ?string $dateFrom = null;
    
    /** @var string|null Конечная дата для фильтрации по дате */
    public ?string $dateTo = null;
    
    /** @var string Фильтр типа свайпа (all, like, dislike, superlike) */
    public string $typeFilter = 'all'; 
    
    /** @var string Текущий режим просмотра (swipes, matches) */
    public string $viewMode = 'swipes'; 
    
    /** @var int Количество элементов на странице */
    public int $perPage = 10;

    /**
     * Хуки Livewire: сброс пагинации при изменении любого фильтра.
     */
    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingDateFrom(): void { $this->resetPage(); }
    public function updatingDateTo(): void { $this->resetPage(); }
    public function updatingTypeFilter(): void { $this->resetPage(); }
    public function updatingViewMode(): void { $this->resetPage(); }

   /**
     * Инициализация компонента.
     * Восстанавливает сохраненные в сессии фильтры и режим просмотра.
     */
    public function mount()
    {
        $saved = session('moderate_dating', []);
        
        if (isset($saved['viewMode'])) {
            $this->viewMode = $saved['viewMode'];
        }
        if (isset($saved['typeFilter'])) {
            $this->typeFilter = $saved['typeFilter'];
        }
    }

    /**
     * Переключение режима просмотра (Свайпы/Матчи).
     * Сохраняет выбор в сессию.
     * 
     * @param string $mode Выбранный режим
     */
    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode;
        session([
            'moderate_dating' => array_merge(
                session('moderate_dating', []),
                ['viewMode' => $mode]
            )
        ]);
        $this->resetPage();
    }

    /**
     * Установка фильтра типа свайпа (Лайк/Дизлайк/Суперлайк).
     * Сохраняет выбор в сессию.
     * 
     * @param string $type Выбранный тип
     */
    public function setTypeFilter(string $type): void
    {
        $this->typeFilter = $type;
        session([
            'moderate_dating' => array_merge(
                session('moderate_dating', []),
                ['typeFilter' => $type]
            )
        ]);
        $this->resetPage();
    }

    /**
     * Полный сброс текстовых и дата-фильтров.
     */
    public function resetFilters(): void
    {
        // ✅ ИСПРАВЛЕНО: Добавлен typeFilter в сброс, чтобы очищались и кнопки Лайков/Дизлайков
        $this->reset(['search', 'dateFrom', 'dateTo', 'typeFilter']);
        $this->typeFilter = 'all'; // Возвращаем по умолчанию
        
        $this->resetPage();
    }
    
    /**
     * Вычисляемое свойство: Статистика для бейджей.
     * Кешируется на 60 секунд, чтобы не нагружать БД 5 COUNT-запросами при каждом рендере.
     */
    #[Computed]
    public function stats()
    {
        return Cache::remember('dating_admin_stats', 60, function () {
            return [
                'total_likes' => Swipe::where('type', 'like')->count(),
                'total_dislikes' => Swipe::where('type', 'dislike')->count(),
                'total_superlikes' => Swipe::where('type', 'superlike')->count(),
                'total_swipes' => Swipe::count(),
                'total_matches' => UserMatch::count(),
            ];
        });
    }

    /**
     * Вычисляемое свойство: Список элементов для текущей страницы.
     * Возвращает свайпы или матчи в зависимости от выбранного режима.
     */
    #[Computed]
    public function items()
    {
        return $this->viewMode === 'matches' ? $this->getMatches() : $this->getSwipes();
    }

    /**
     * Получение списка свайпов с пагинацией.
     */
    private function getSwipes()
    {
        return Swipe::with(['user', 'targetUser'])
            ->when($this->typeFilter !== 'all', fn($q) => $q->where('type', $this->typeFilter))
            // ВАЖНО: orWhereHas обернут в замыкание where(), 
            // чтобы поиск по имени не ломал фильтры по типу и дате.
            ->when($this->search, function ($q) {
                $q->where(function ($innerQ) {
                    $innerQ->whereHas('user', fn($q2) => $q2->where('name', 'ilike', "%{$this->search}%"))
                           ->orWhereHas('targetUser', fn($q2) => $q2->where('name', 'ilike', "%{$this->search}%"));
                });
            })
            ->when($this->dateFrom, fn($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->latest()
            ->paginate($this->perPage)->onEachSide(2);
    }

    /**
     * Получение списка матчей с пагинацией.
     */
    private function getMatches()
    {
        return UserMatch::with(['user1', 'user2'])
            // Аналогичная изоляция поиска для матчей
            ->when($this->search, function ($q) {
                $q->where(function ($innerQ) {
                    $innerQ->whereHas('user1', fn($q2) => $q2->where('name', 'ilike', "%{$this->search}%"))
                           ->orWhereHas('user2', fn($q2) => $q2->where('name', 'ilike', "%{$this->search}%"));
                });
            })
            ->when($this->dateFrom, fn($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->latest()
            ->paginate($this->perPage)->onEachSide(2);
    }

    /**
     * Удаление элемента (Свайпа или Матча).
     * Обернуто в транзакцию для поддержания целостности БД.
     * 
     * @param int $id ID удаляемого элемента
     */
    public function deleteItem(int $id): void
    {
        // Передаем $id внутрь транзакции через use()
        DB::transaction(function () use ($id) {
            if ($this->viewMode === 'matches') {
                $match = UserMatch::find($id);
                if ($match) {
                    $match->delete();

                    // Чистим "осиротевшие" свайпы между этими двумя пользователями
                    Swipe::where(function ($q) use ($match) {
                        $q->where('user_id', $match->user1_id)
                          ->where('target_user_id', $match->user2_id);
                    })->orWhere(function ($q) use ($match) {
                        $q->where('user_id', $match->user2_id)
                          ->where('target_user_id', $match->user1_id);
                    })->delete();

                    $this->dispatch('show-toast', type: 'success', message: 'Матч и история свайпов удалены');
                }
            } else {
                $swipe = Swipe::find($id);
                if ($swipe) {
                    // Если удаляем лайк/суперлайк, нужно удалить и связанный с ним матч
                    if ($swipe->type === 'like' || $swipe->type === 'superlike') {
                        UserMatch::where(function ($q) use ($swipe) {
                            $q->where('user1_id', $swipe->user_id)->where('user2_id', $swipe->target_user_id);
                        })->orWhere(function ($q) use ($swipe) {
                            $q->where('user1_id', $swipe->target_user_id)->where('user2_id', $swipe->user_id);
                        })->delete();
                    }
                    
                    $swipe->delete();
                    $this->dispatch('show-toast', type: 'success', message: 'Свайп (и связанный матч, если был) удалён');
                }
            }
        });
        
        // Сбрасываем кэш статистики, т.к. количество записей изменилось
        Cache::forget('dating_admin_stats');
        $this->dispatch('$refresh');
    }
}; 
?>

<!-- ========================================== -->
<!-- ШАБЛОН                                     -->
<!-- ========================================== -->
<div class="flex flex-col gap-6">
    <!-- Шапка -->
    <div class="flex items-center justify-between flex-wrap gap-4 ">
        <h1 class="text-2xl font-semibold flex items-center gap-2">
            <x-lucide-heart class="w-6 h-6 text-pink-500" />
            Модерация знакомств
        </h1>
        <div class="flex items-center gap-3 text-sm text-muted-foreground">
            <span>Свайпов: {{ $this->stats['total_swipes'] }}</span>
            <span>•</span>
            <span>Матчей: {{ $this->stats['total_matches'] }}</span>
        </div>
    </div>

    <!-- Блок фильтров по типу и дате -->
    <div class="flex justify-between items-center flex-wrap gap-4">
        @if ($viewMode === 'swipes')
            <div class="flex gap-1.5" wire:key="type-filter-buttons">
                <x-ui.button wire:click="setTypeFilter('like')" variant="{{ $typeFilter === 'like' ? 'default' : 'secondary' }}" size="sm" wire:key="type-like">
                    <x-lucide-thumbs-up class="w-3 h-3 inline mr-1" />
                    Лайки <x-ui.badge size="xs">{{ $this->stats['total_likes'] }}</x-ui.badge>
                </x-ui.button>
                <x-ui.button wire:click="setTypeFilter('dislike')" variant="{{ $typeFilter === 'dislike' ? 'default' : 'secondary' }}" size="sm" wire:key="type-dislike">
                    <x-lucide-thumbs-down class="w-3 h-3 inline mr-1" />
                    Дизлайки <x-ui.badge size="xs">{{ $this->stats['total_dislikes'] }}</x-ui.badge>
                </x-ui.button>
                <x-ui.button wire:click="setTypeFilter('superlike')" variant="{{ $typeFilter === 'superlike' ? 'default' : 'secondary' }}" size="sm" wire:key="type-superlike">
                    <x-lucide-star class="w-3 h-3 inline mr-1" />
                    Суперлайки <x-ui.badge size="xs">{{ $this->stats['total_superlikes'] }}</x-ui.badge>
                </x-ui.button>
            </div>
        @endif

        <div class="flex items-center gap-2 flex-1 justify-end ml-auto">
            <x-ui.input wire:model.live.debounce.300ms="search" placeholder="Поиск по имени..." class="w-48" />
            <x-ui.date-picker wire:model.live="dateFrom" placeholder="с" width="w-[10rem]" />
            <span class="text-muted-foreground">—</span>
            <x-ui.date-picker wire:model.live="dateTo" placeholder="по" width="w-[10rem]" />
            <!-- Кнопка сброса фильтров -->
            <x-ui.button wire:click="resetFilters" variant="outline" size="sm">
                <x-lucide-rotate-ccw class="w-4 h-4 inline mr-2" />
                <span>Сбросить</span>
            </x-ui.button>
        </div>
    </div>

    <!-- Переключатель режима (Свайпы/Матчи) -->
   <div class="flex flex-wrap items-center gap-3" wire:key="filters-wrapper">
        <div class="flex gap-1.5" wire:key="mode-buttons">
            <x-ui.button wire:click="setViewMode('swipes')" variant="{{ $viewMode === 'swipes' ? 'default' : 'secondary' }}" size="sm" wire:key="mode-swipes">
                Свайпы <x-ui.badge size="xs">{{ $this->stats['total_swipes'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="setViewMode('matches')" variant="{{ $viewMode === 'matches' ? 'default' : 'secondary' }}" size="sm" wire:key="mode-matches">
                Матчи <x-ui.badge size="xs">{{ $this->stats['total_matches'] }}</x-ui.badge>
            </x-ui.button>
        </div>
    </div>

    <!-- Таблица данных -->
    <x-ui.table wire:key="dating-table">
        <x-ui.table-header>
            <x-ui.table-row>
                <x-ui.table-head class="w-16">ID</x-ui.table-head>
                @if ($viewMode === 'swipes')
                    <x-ui.table-head>Оценил</x-ui.table-head>
                    <x-ui.table-head>Получил оценку</x-ui.table-head>
                    <x-ui.table-head>Тип</x-ui.table-head>
                @else
                    <x-ui.table-head>Пользователь 1</x-ui.table-head>
                    <x-ui.table-head>Пользователь 2</x-ui.table-head>
                @endif
                <x-ui.table-head>Дата</x-ui.table-head>
                <x-ui.table-head class="text-right w-16">Действия</x-ui.table-head>
            </x-ui.table-row>
        </x-ui.table-header>
        <x-ui.table-body>
            @forelse ($this->items as $item)
                <x-ui.table-row wire:key="{{ $viewMode }}-{{ $item->id }}">
                    <x-ui.table-cell class="text-muted-foreground text-xs font-mono">{{ $item->id }}</x-ui.table-cell>

                    @if ($viewMode === 'swipes')
                        <!-- Кто оценил -->
                        <x-ui.table-cell>
                            @if($item->user)
                                <a href="{{ route('admin.users.show', $item->user->id) }}" wire:navigate class="group flex items-center gap-3 hover:text-primary transition-colors">
                                    <x-avatar src="{{ $item->user->avatar_url }}" name="{{ $item->user->name }}" size="sm" userId="{{ $item->user->id }}" showStatus="true" />
                                    <div class="flex flex-col">
                                        <span class="text-sm font-medium">{{ $item->user->name }} <span class="text-xs text-muted-foreground font-normal">(ID: {{ $item->user->id }})</span></span>
                                        <span class="text-xs text-muted-foreground group-hover:text-primary/80 transition-colors">{{ $item->user->email }}</span>
                                    </div>
                                </a>
                            @else
                                <span class="text-sm text-muted-foreground italic">Пользователь удален</span>
                            @endif
                        </x-ui.table-cell>

                        <!-- Кого оценили -->
                        <x-ui.table-cell>
                            @if($item->targetUser)
                                <a href="{{ route('admin.users.show', $item->targetUser->id) }}" wire:navigate class="group flex items-center gap-3 hover:text-primary transition-colors">
                                    <x-avatar src="{{ $item->targetUser->avatar_url }}" name="{{ $item->targetUser->name }}" size="sm" userId="{{ $item->targetUser->id }}" showStatus="true" />
                                    <div class="flex flex-col">
                                        <span class="text-sm font-medium">{{ $item->targetUser->name }} <span class="text-xs text-muted-foreground font-normal">(ID: {{ $item->targetUser->id }})</span></span>
                                        <span class="text-xs text-muted-foreground group-hover:text-primary/80 transition-colors">{{ $item->targetUser->email }}</span>
                                    </div>
                                </a>
                            @else
                                <span class="text-sm text-muted-foreground italic">Пользователь удален</span>
                            @endif
                        </x-ui.table-cell>

                        <!-- Тип свайпа -->
                        <x-ui.table-cell>
                            @if ($item->type === 'like')
                                <x-ui.badge variant="success" size="xs" class="inline-flex items-center gap-1">
                                    <x-lucide-heart class="w-3.5 h-3.5 fill-current" /> Лайк
                                </x-ui.badge>
                            @elseif($item->type === 'superlike')
                                <x-ui.badge variant="warning" size="xs" class="inline-flex items-center gap-1">
                                    <x-lucide-star class="w-3.5 h-3.5 fill-current" /> Суперлайк
                                </x-ui.badge>
                            @else
                                <x-ui.badge variant="destructive" size="xs" class="inline-flex items-center gap-1">
                                    <x-lucide-thumbs-down class="w-3.5 h-3.5" /> Дизлайк
                                </x-ui.badge>
                            @endif
                        </x-ui.table-cell>
                    @else
                        <!-- Матч: Пользователь 1 -->
                        <x-ui.table-cell>
                            @if($item->user1)
                                <a href="{{ route('admin.users.show', $item->user1->id) }}" wire:navigate class="group flex items-center gap-3 hover:text-primary transition-colors">
                                    <x-avatar src="{{ $item->user1->avatar_url }}" name="{{ $item->user1->name }}" size="sm" userId="{{ $item->user1->id }}" showStatus="true" />
                                    <div class="flex flex-col">
                                        <span class="text-sm font-medium">{{ $item->user1->name }} <span class="text-xs text-muted-foreground font-normal">(ID: {{ $item->user1->id }})</span></span>
                                        <span class="text-xs text-muted-foreground group-hover:text-primary/80 transition-colors">{{ $item->user1->email }}</span>
                                    </div>
                                </a>
                            @else
                                <span class="text-sm text-muted-foreground italic">Пользователь удален</span>
                            @endif
                        </x-ui.table-cell>

                        <!-- Матч: Пользователь 2 -->
                        <x-ui.table-cell>
                            @if($item->user2)
                                <a href="{{ route('admin.users.show', $item->user2->id) }}" wire:navigate class="group flex items-center gap-3 hover:text-primary transition-colors">
                                    <x-avatar src="{{ $item->user2->avatar_url }}" name="{{ $item->user2->name }}" size="sm" userId="{{ $item->user2->id }}" showStatus="true" />
                                    <div class="flex flex-col">
                                        <span class="text-sm font-medium">{{ $item->user2->name }} <span class="text-xs text-muted-foreground font-normal">(ID: {{ $item->user2->id }})</span></span>
                                        <span class="text-xs text-muted-foreground group-hover:text-primary/80 transition-colors">{{ $item->user2->email }}</span>
                                    </div>
                                </a>
                            @else
                                <span class="text-sm text-muted-foreground italic">Пользователь удален</span>
                            @endif
                        </x-ui.table-cell>
                    @endif

                    <!-- Дата создания -->
                    <x-ui.table-cell class="text-muted-foreground text-xs whitespace-nowrap">
                        {{ $item->created_at->format('d.m.Y H:i') }}
                    </x-ui.table-cell>

                    <!-- Меню действий -->
                    <x-ui.table-cell class="text-right">
                        <x-ui.dropdown-menu>
                            <x-ui.dropdown-menu-trigger>
                                <x-ui.button variant="ghost" size="icon-sm">
                                    <x-lucide-more-horizontal class="w-4 h-4" />
                                </x-ui.button>
                            </x-ui.dropdown-menu-trigger>
                            <x-ui.dropdown-menu-content align="end">
                                <!-- wire:confirm защищает от случайного клика -->
                                <x-ui.dropdown-menu-item variant="destructive" wire:click="deleteItem({{ $item->id }})" wire:confirm="Удалить этот элемент? Связанные данные также будут удалены.">
                                    <x-lucide-trash-2 class="w-4 h-4 mr-2" />
                                    Удалить
                                </x-ui.dropdown-menu-item>
                            </x-ui.dropdown-menu-content>
                        </x-ui.dropdown-menu>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @empty
                <!-- Пустое состояние -->
                <x-ui.table-row wire:key="empty-state">
                    <!-- Динамический colspan в зависимости от количества колонок -->
                    <x-ui.table-cell colspan="{{ $viewMode === 'swipes' ? 6 : 5 }}" class="py-12 text-center text-muted-foreground">
                        <x-lucide-heart class="w-12 h-12 mx-auto mb-3 opacity-20" />
                        <p class="text-sm">Нет данных для отображения</p>
                        @if($search || $dateFrom || $dateTo || $typeFilter !== 'all')
                            <x-ui.button wire:click="resetFilters" variant="link" class="mt-2">
                                Сбросить фильтры
                            </x-ui.button>
                        @endif
                    </x-ui.table-cell>
                </x-ui.table-row>
            @endforelse
        </x-ui.table-body>
    </x-ui.table>

    <!-- Пагинация -->
    <div class="mt-4" wire:key="pagination-wrapper">        
        {{ $this->items->links('partials.pagination') }}
    </div>
</div>