<?php

use App\Actions\Admin\ModerateDatingAction;
use App\Enums\MatchStatus;
use App\Enums\SwipeType;
use App\Models\Swipe;
use App\Models\UserMatch;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'mode', except: 'swipes')]
    public string $viewMode = 'swipes'; 

    #[Url(as: 'type', except: 'all')]
    public string $typeFilter = 'all'; 

       public ?string $dateFrom = null;
    public ?string $dateTo = null;
    public int $perPage = 10;

    /** @var string URL для кнопки "Назад" */
    public string $backUrl = '';

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingDateFrom(): void { $this->resetPage(); }
    public function updatingDateTo(): void { $this->resetPage(); }
    public function updatingTypeFilter(): void { $this->resetPage(); }

    public function mount(): void
    {
        // ФИКС: Запоминаем URL "Назад" только при первой загрузке
        $previousUrl = url()->previous();
        $this->backUrl = ($previousUrl && $previousUrl !== url()->current()) 
            ? $previousUrl 
            : route('admin.dashboard');
        
        // Если пришли с поиском, очищаем фильтр типа, чтобы не мешал
        if (!empty($this->search)) {
            $this->typeFilter = 'all';
        }
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode;
        $this->search = ''; // ФИКС: При смене режима очищаем поиск, так как ID из другого режима не найдется
        $this->resetPage();
    }

       public function setTypeFilter(string $type): void
    {
        $this->typeFilter = $type;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'dateFrom', 'dateTo', 'typeFilter']);
        $this->typeFilter = 'all'; 
        $this->resetPage();
    }

    // ============================================
    // ДЕЙСТВИЯ (ДЕЛЕГИРУЕМ В ACTION)
    // ============================================

    public function deleteItem(int $id, ModerateDatingAction $action): void
    {
        if ($this->viewMode === 'matches') {
            $match = UserMatch::find($id);
            if ($match) {
                $action->destroyMatch($match, auth()->user());
                $this->dispatch('show-toast', type: 'warning', message: 'Мэтч принудительно разорван.');
            }
        } else {
            $swipe = Swipe::find($id);
            if ($swipe) {
                $action->destroySwipe($swipe, auth()->user());
                $this->dispatch('show-toast', type: 'success', message: 'Свайп удален');
            }
        }
    }

    public function restoreMatch(int $id, ModerateDatingAction $action): void
    {
        $match = UserMatch::find($id);
        if ($match) {
            $action->restoreMatch($match, auth()->user());
            $this->dispatch('show-toast', type: 'success', message: 'Мэтч восстановлен.');
        }
    }
    
    // ============================================
    // ВЫВОД ДАННЫХ
    // ============================================

    #[Computed]
    public function stats(): array
    {
        return Cache::remember('dating_admin_stats', 60, function () {
            $baseSwipeQuery = Swipe::where(function ($q) {
                $q->whereNull('user_id')->orWhereHas('user', fn($q2) => $q2->excludeStaff());
            });
                
            $baseMatchQuery = UserMatch::where(function ($q) {
                $q->whereNull('user1_id')->orWhereHas('user1', fn($q2) => $q2->excludeStaff());
            })->where(function ($q) {
                $q->whereNull('user2_id')->orWhereHas('user2', fn($q2) => $q2->excludeStaff());
            });

            return [
                'total_likes' => (clone $baseSwipeQuery)->where('type', 'like')->count(),
                'total_dislikes' => (clone $baseSwipeQuery)->where('type', 'dislike')->count(),
                'total_superlikes' => (clone $baseSwipeQuery)->where('type', 'superlike')->count(),
                'total_swipes' => (clone $baseSwipeQuery)->count(),
                'total_matches' => $baseMatchQuery->count(),
            ];
        });
    }

    #[Computed]
    public function items()
    {
        return $this->viewMode === 'matches' ? $this->getMatches() : $this->getSwipes();
    }

       private function getSwipes()
    {
        $searchOperator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
        $avatarQuery = fn($q) => $q->select(['id', 'user_id', 'is_primary', 'status', 'path_thumb', 'path_medium', 'path_large', 'path_original'])->orderByDesc('is_primary')->limit(1);

        return Swipe::with([
                // ФИКС: Добавлено withTrashed()
                'user' => fn($q) => $q->withTrashed()->select('id', 'name', 'email', 'role', 'status', 'is_premium', 'premium_expires_at', 'last_seen')->with(['photos' => $avatarQuery]),
                'targetUser' => fn($q) => $q->withTrashed()->select('id', 'name', 'email', 'role', 'status', 'is_premium', 'premium_expires_at', 'last_seen')->with(['photos' => $avatarQuery]),
            ])
            ->where(function ($q) {
                // ФИКС: Ищем даже удаленных юзеров
                $q->whereNull('user_id')->orWhereHas('user', fn($q2) => $q2->withTrashed()->excludeStaff());
            })
            ->where(function ($q) {
                $q->whereNull('target_user_id')->orWhereHas('targetUser', fn($q2) => $q2->withTrashed()->excludeStaff());
            })
            ->when($this->typeFilter !== 'all', fn($q) => $q->where('type', $this->typeFilter))
            ->when($this->search, function ($q) use ($searchOperator) {
                $search = $this->search;
                $q->where(function ($innerQ) use ($search, $searchOperator) {
                    $innerQ->whereRaw("CAST(id AS TEXT) {$searchOperator} ?", ["%{$search}%"])
                    // ФИКС: Ищем по имени даже удаленных
                    ->orWhereHas('user', fn($q2) => $q2->withTrashed()->where('name', $searchOperator, "%{$search}%"))
                    ->when(is_numeric($search), fn($q3) => $q3->orWhere('user_id', $search));
                });
            })
            ->when($this->dateFrom, fn($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->latest('created_at')->latest('id')
            ->paginate($this->perPage)->onEachSide(2);
    }

        private function getMatches()
    {
        $searchOperator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
        $avatarQuery = fn($q) => $q->select(['id', 'user_id', 'is_primary', 'status', 'path_thumb', 'path_medium', 'path_large', 'path_original'])->orderByDesc('is_primary')->limit(1);

        return UserMatch::with([
                // ФИКС: Добавлено withTrashed()
                'user1' => fn($q) => $q->withTrashed()->select('id', 'name', 'email', 'role', 'status', 'is_premium', 'premium_expires_at', 'last_seen')->with(['photos' => $avatarQuery]),
                'user2' => fn($q) => $q->withTrashed()->select('id', 'name', 'email', 'role', 'status', 'is_premium', 'premium_expires_at', 'last_seen')->with(['photos' => $avatarQuery]),
            ])
            ->where(function ($q) {
                $q->whereNull('user1_id')->orWhereHas('user1', fn($q2) => $q2->withTrashed()->excludeStaff());
            })
            ->where(function ($q) {
                $q->whereNull('user2_id')->orWhereHas('user2', fn($q2) => $q2->withTrashed()->excludeStaff());
            })
            ->when($this->search, function ($q) use ($searchOperator) {
                $search = $this->search;
                $q->where(function ($innerQ) use ($search, $searchOperator) {
                    $innerQ->whereRaw("CAST(id AS TEXT) {$searchOperator} ?", ["%{$search}%"])
                    // ФИКС: Ищем по имени даже удаленных
                    ->orWhereHas('user1', fn($q2) => $q2->withTrashed()->where('name', $searchOperator, "%{$search}%"))
                    ->orWhereHas('user2', fn($q2) => $q2->withTrashed()->where('name', $searchOperator, "%{$search}%"))
                    ->when(is_numeric($search), function ($q3) use ($search) {
                        $q3->orWhere('user1_id', $search)->orWhere('user2_id', $search);
                    });
                });
            })
            ->when($this->dateFrom, fn($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->latest('created_at')->latest('id')
            ->paginate($this->perPage)->onEachSide(2);
    }
}; 
?>


<div class="flex flex-col gap-6">
    <!-- Шапка -->
    <div class="flex items-center justify-between flex-wrap gap-4">
         <div class="flex items-center gap-4">            
            <a href="{{ $backUrl }}" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            <h1 class="text-2xl font-semibold flex items-center gap-2">
                <x-lucide-heart class="w-6 h-6 text-pink-500" />
                Модерация знакомств
            </h1>
         </div>
        <div class="flex items-center gap-3 text-sm text-muted-foreground">
            <span>Свайпов: {{ $this->stats['total_swipes'] }}</span>
            <span>•</span>
            <span>Матчей: {{ $this->stats['total_matches'] }}</span>
        </div>
    </div>

    <!-- Блок фильтров по типу (только для свайпов) -->
    @if ($viewMode === 'swipes')
        <div class="flex gap-1.5" wire:key="type-filter-buttons">
            <x-ui.button wire:click="setTypeFilter('like')" variant="{{ $typeFilter === 'like' ? 'default' : 'secondary' }}" size="sm" wire:key="type-like">
                <x-lucide-thumbs-up class="w-3 h-3 inline mr-1" /> Лайки <x-ui.badge size="xs">{{ $this->stats['total_likes'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="setTypeFilter('dislike')" variant="{{ $typeFilter === 'dislike' ? 'default' : 'secondary' }}" size="sm" wire:key="type-dislike">
                <x-lucide-thumbs-down class="w-3 h-3 inline mr-1" /> Дизлайки <x-ui.badge size="xs">{{ $this->stats['total_dislikes'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="setTypeFilter('superlike')" variant="{{ $typeFilter === 'superlike' ? 'default' : 'secondary' }}" size="sm" wire:key="type-superlike">
                <x-lucide-star class="w-3 h-3 inline mr-1" /> Суперлайки <x-ui.badge size="xs">{{ $this->stats['total_superlikes'] }}</x-ui.badge>
            </x-ui.button>
        </div>
    @endif       

    <!-- Переключатель режима (Свайпы/Матчи) и Поиск -->
    <div class="flex justify-between items-center flex-wrap gap-3" wire:key="filters-wrapper">
        <div class="flex gap-1.5" wire:key="mode-buttons">
            <x-ui.button wire:click="setViewMode('swipes')" variant="{{ $viewMode === 'swipes' ? 'default' : 'secondary' }}" size="sm" wire:key="mode-swipes">
                Свайпы <x-ui.badge size="xs">{{ $this->stats['total_swipes'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="setViewMode('matches')" variant="{{ $viewMode === 'matches' ? 'default' : 'secondary' }}" size="sm" wire:key="mode-matches">
                Матчи <x-ui.badge size="xs">{{ $this->stats['total_matches'] }}</x-ui.badge>
            </x-ui.button>
        </div>

        <div class="flex items-center gap-2 flex-1 justify-end ml-auto">
            <div class="relative w-55">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск по имени или ID..." class="pl-9 " />
                <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                @if (!empty($search))
                    <button wire:click="$set('search', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"><x-lucide-x class="w-4 h-4" /></button>
                @endif
            </div>
            <x-ui.date-picker wire:model.live="dateFrom" placeholder="с" width="w-[10rem]" wire:key="date-from-search" />
            <span class="text-muted-foreground">—</span>
            <x-ui.date-picker wire:model.live="dateTo" placeholder="по" width="w-[10rem]" wire:key="date-to-search" />
            <x-ui.button wire:click="resetFilters" variant="outline" size="sm">
                <x-lucide-rotate-ccw class="w-4 h-4 inline mr-2" /><span>Сбросить</span>
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
                    <x-ui.table-head>Статус</x-ui.table-head>
                @endif
                <x-ui.table-head>Дата</x-ui.table-head>
                <x-ui.table-head class="text-right w-16">Действия</x-ui.table-head>
            </x-ui.table-row>
        </x-ui.table-header>
        <x-ui.table-body>
            @forelse ($this->items as $item)
                @php 
                    // ФИКС: Проверяем, является ли этот элемент искомым (по ID)
                    $isHighlighted = is_numeric($this->search) && $item->id == (int)$this->search; 
                @endphp

                <x-ui.table-row 
                    wire:key="{{ $viewMode }}-{{ $item->id }}-{{ $item->status ?? 'trashed' }}"
                    class="{{ $isHighlighted ? 'bg-blue-500/10 ring-2 ring-blue-500/50' : '' }}"
                    x-data="{ isHi: {{ $isHighlighted ? 'true' : 'false' }} }"
                    x-init="isHi && setTimeout(() => { $el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 200)"
                >
                    <x-ui.table-cell class="text-xs font-mono whitespace-nowrap {{ $isHighlighted ? 'text-blue-500 font-bold' : 'text-muted-foreground' }}">
                        #{{ $item->id }}
                    </x-ui.table-cell>

                    @if ($viewMode === 'swipes')
                        <!-- Кто оценил -->
                        <x-ui.table-cell>
                            @if($item->user)
                                <a href="{{ route('admin.users.show', $item->user->id) }}" wire:navigate class="group flex items-center gap-3 hover:text-primary transition-colors">
                                    <x-avatar src="{{ $item->user->avatar_url }}" name="{{ $item->user->name }}" size="sm" userId="{{ $item->user->id }}" showStatus="true" :isOnline="$item->user->is_online" />
                                    <div class="flex flex-col">
                                        <div class="flex gap-2 items-center">
                                            <x-user-status-sign :user="$item->user" />
                                            <span class="text-sm font-medium">{{ $item->user->name }}</span>
                                            @if($item->user->has_active_premium)<x-lucide-crown class="w-3 h-3 text-yellow-500" />@endif                                           
                                            <span class="text-xs text-muted-foreground font-normal">(ID: {{ $item->user->id }})</span>                                        
                                        </div>                                        
                                        <span class="text-xs text-muted-foreground">{{ $item->user->email }}</span>
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
                                    <x-avatar src="{{ $item->targetUser->avatar_url }}" name="{{ $item->targetUser->name }}" size="sm" userId="{{ $item->targetUser->id }}" showStatus="true" :isOnline="$item->targetUser->is_online" />
                                    <div class="flex flex-col">
                                        <div class="flex gap-2 items-center">
                                            <x-user-status-sign :user="$item->targetUser" />
                                            <span class="text-sm font-medium">{{ $item->targetUser->name }}</span>
                                            @if($item->targetUser->has_active_premium)<x-lucide-crown class="w-3 h-3 text-yellow-500" />@endif                                               
                                            <span class="text-xs text-muted-foreground font-normal">(ID: {{ $item->targetUser->id }})</span>
                                        </div>
                                        <span class="text-xs text-muted-foreground">{{ $item->targetUser->email }}</span>
                                    </div>
                                </a>
                            @else
                                <span class="text-sm text-muted-foreground italic">Пользователь удален</span>
                            @endif
                        </x-ui.table-cell>

                        <!-- Тип свайпа (через Enum) -->
                        <x-ui.table-cell>
                            @php $swipeType = \App\Enums\SwipeType::tryFrom($item->type); @endphp
                            @if($swipeType)
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium {{ $swipeType->color() }}">
                                    @if($swipeType === \App\Enums\SwipeType::Like)<x-lucide-heart class="w-3 h-3 fill-current" />@endif
                                    @if($swipeType === \App\Enums\SwipeType::Superlike)<x-lucide-star class="w-3 h-3 fill-current" />@endif
                                    @if($swipeType === \App\Enums\SwipeType::Dislike)<x-lucide-thumbs-down class="w-3 h-3" />@endif
                                    {{ $swipeType->label() }}
                                </span>
                            @endif
                        </x-ui.table-cell>
                    @else
                        <!-- Матч: Пользователь 1 -->
                        <x-ui.table-cell>
                            @if($item->user1)
                                <a href="{{ route('admin.users.show', $item->user1->id) }}" wire:navigate class="group flex items-center gap-3 hover:text-primary transition-colors">
                                    <x-avatar src="{{ $item->user1->avatar_url }}" name="{{ $item->user1->name }}" size="sm" userId="{{ $item->user1->id }}" showStatus="true" :isOnline="$item->user1->is_online" />
                                    <div class="flex flex-col">
                                        <div class="flex gap-2 items-center">
                                            <x-user-status-sign :user="$item->user1" />
                                            <span class="text-sm font-medium">{{ $item->user1->name }}</span>
                                            @if($item->user1->has_active_premium)<x-lucide-crown class="w-3 h-3 text-yellow-500" />@endif                                              
                                            <span class="text-xs text-muted-foreground font-normal">(ID: {{ $item->user1->id }})</span>
                                        </div>                                        
                                        <span class="text-xs text-muted-foreground">{{ $item->user1->email }}</span>
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
                                    <x-avatar src="{{ $item->user2->avatar_url }}" name="{{ $item->user2->name }}" size="sm" userId="{{ $item->user2->id }}" showStatus="true" :isOnline="$item->user2->is_online" />
                                    <div class="flex flex-col">
                                        <div class="flex gap-2 items-center">
                                            <x-user-status-sign :user="$item->user2" />
                                            <span class="text-sm font-medium">{{ $item->user2->name }}</span>
                                            @if($item->user2->has_active_premium)<x-lucide-crown class="w-3 h-3 text-yellow-500" />@endif                                              
                                            <span class="text-xs text-muted-foreground font-normal">(ID: {{ $item->user2->id }})</span>
                                        </div>                                              
                                        <span class="text-xs text-muted-foreground">{{ $item->user2->email }}</span>
                                    </div>
                                </a>
                            @else
                                <span class="text-sm text-muted-foreground italic">Пользователь удален</span>
                            @endif
                        </x-ui.table-cell>

                        <!-- Статус мэтча (через Enum) -->
                        <x-ui.table-cell>
                            @php $matchStatus = \App\Enums\MatchStatus::tryFrom($item->status); @endphp
                            @if($matchStatus)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $matchStatus->color() }}">
                                    {{ $matchStatus->label() }}
                                </span>
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
                                @if ($viewMode === 'matches')
                                    @if ($item->status === 'active')
                                        <x-ui.dropdown-menu-item 
                                            variant="destructive" 
                                            wire:click="deleteItem({{ $item->id }})" 
                                            wire:confirm="Принудительно разорвать мэтч? Пользователи больше не смогут общаться."
                                            wire:target="deleteItem({{ $item->id }})"
                                            wire:loading.attr="disabled"
                                        >
                                            <x-lucide-trash-2 class="w-4 h-4 mr-2" wire:loading.remove wire:target="deleteItem({{ $item->id }})" />
                                            <x-lucide-loader-2 class="w-4 h-4 mr-2 animate-spin hidden" wire:loading wire:target="deleteItem({{ $item->id }})" />
                                            Разорвать мэтч
                                        </x-ui.dropdown-menu-item>
                                    @else
                                        <x-ui.dropdown-menu-item 
                                            variant="success" 
                                            wire:click="restoreMatch({{ $item->id }})" 
                                            wire:confirm="Восстановить мэтч? Пользователи снова смогут общаться."
                                            wire:target="restoreMatch({{ $item->id }})"
                                            wire:loading.attr="disabled"
                                        >
                                            <x-lucide-rotate-ccw class="w-4 h-4 mr-2" wire:loading.remove wire:target="restoreMatch({{ $item->id }})" />
                                            <x-lucide-loader-2 class="w-4 h-4 mr-2 animate-spin hidden" wire:loading wire:target="restoreMatch({{ $item->id }})" />
                                            Восстановить мэтч
                                        </x-ui.dropdown-menu-item>
                                    @endif
                                @else
                                    <x-ui.dropdown-menu-item 
                                        variant="destructive" 
                                        wire:click="deleteItem({{ $item->id }})" 
                                        wire:confirm="Удалить этот свайп?"
                                        wire:target="deleteItem({{ $item->id }})"
                                        wire:loading.attr="disabled"
                                    >
                                        <x-lucide-trash-2 class="w-4 h-4 mr-2" wire:loading.remove wire:target="deleteItem({{ $item->id }})" />
                                        <x-lucide-loader-2 class="w-4 h-4 mr-2 animate-spin hidden" wire:loading wire:target="deleteItem({{ $item->id }})" />
                                        Удалить
                                    </x-ui.dropdown-menu-item>
                                @endif
                            </x-ui.dropdown-menu-content>
                        </x-ui.dropdown-menu>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @empty
                <x-ui.table-row wire:key="empty-state">
                    <x-ui.table-cell colspan="6" class="py-12 text-center text-muted-foreground">
                        <x-lucide-heart class="w-12 h-12 mx-auto mb-3 opacity-20" />
                        <p class="text-sm">Нет данных для отображения</p>
                        @if($search || $dateFrom || $dateTo || $typeFilter !== 'all')
                            <x-ui.button wire:click="resetFilters" variant="link" class="mt-2">Сбросить фильтры</x-ui.button>
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

