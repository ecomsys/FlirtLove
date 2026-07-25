<?php

use App\Models\Swipe;
use App\Models\UserMatch;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed; // ← добавить
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.admin')] class extends Component {
    use WithPagination;

    public string $search = '';
    public ?string $dateFrom = null;
    public ?string $dateTo = null;
    public string $typeFilter = 'all'; // all, like, dislike, superlike
    public string $viewMode = 'swipes'; // swipes, matches
    public int $perPage = 10;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }
    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }
    public function updatingDateTo(): void
    {
        $this->resetPage();
    }
    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }
    public function updatingViewMode(): void
    {
        $this->resetPage();
    }

    public function setTypeFilter(string $type): void
    {
        $this->typeFilter = $type;
        $this->resetPage();
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode;
        $this->resetPage();
    }

    #[Computed]
    public function stats()
    {
        return [
            'total_likes' => Swipe::where('type', 'like')->count(),
            'total_dislikes' => Swipe::where('type', 'dislike')->count(),
            'total_superlikes' => Swipe::where('type', 'superlike')->count(),
            'total_swipes' => Swipe::count(),
            'total_matches' => UserMatch::count(),
        ];
    }

    #[Computed]
    public function items()
    {
        if ($this->viewMode === 'matches') {
            return $this->getMatches();
        }
        return $this->getSwipes();
    }

    private function getSwipes()
    {
        return Swipe::with(['user', 'targetUser'])
            ->when($this->typeFilter !== 'all', fn($q) => $q->where('type', $this->typeFilter))
            ->when($this->search, function ($q) {
                $q->whereHas('user', fn($q2) => $q2->where('name', 'ilike', "%{$this->search}%"))->orWhereHas('targetUser', fn($q2) => $q2->where('name', 'ilike', "%{$this->search}%"));
            })
            ->when($this->dateFrom, fn($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->latest()
            ->paginate($this->perPage);
    }

    private function getMatches()
    {
        return UserMatch::with(['user1', 'user2'])
            ->when($this->search, function ($q) {
                $q->whereHas('user1', fn($q2) => $q2->where('name', 'ilike', "%{$this->search}%"))->orWhereHas('user2', fn($q2) => $q2->where('name', 'ilike', "%{$this->search}%"));
            })
            ->when($this->dateFrom, fn($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->latest()
            ->paginate($this->perPage);
    }

    public function deleteItem(int $id): void
    {
        if ($this->viewMode === 'matches') {
            $item = UserMatch::find($id);
            if ($item) {
                $item->delete();
                $this->dispatch('show-toast', type: 'success', message: 'Матч удалён');
            }
        } else {
            $item = Swipe::find($id);
            if ($item) {
                $item->delete();
                $this->dispatch('show-toast', type: 'success', message: 'Свайп удалён');
            }
        }
        $this->dispatch('$refresh');
    }
};
?>

<!-- шаблон -->
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


    <div class="flex justify-between items-center">

        <!-- Фильтр по типу (только для свайпов) -->
        @if ($viewMode === 'swipes')
            <div class="flex gap-1.5">
                {{-- <x-ui.button
                    wire:click="setTypeFilter('all')"
                    variant="{{ $typeFilter === 'all' ? 'default' : 'secondary' }}"
                    size="sm"
                >
                    Все
                    <x-ui.badge size="xs">{{ $this->stats['total_swipes'] }}</x-ui.badge>
                </x-ui.button> --}}
                <x-ui.button wire:click="setTypeFilter('like')"
                    variant="{{ $typeFilter === 'like' ? 'default' : 'secondary' }}" size="sm">
                    <x-lucide-thumbs-up class="w-3 h-3 inline mr-1" />
                    Лайки
                    <x-ui.badge size="xs">{{ $this->stats['total_likes'] }}</x-ui.badge>
                </x-ui.button>
                <x-ui.button wire:click="setTypeFilter('dislike')"
                    variant="{{ $typeFilter === 'dislike' ? 'default' : 'secondary' }}" size="sm">
                    <x-lucide-thumbs-down class="w-3 h-3 inline mr-1" />
                    Дизлайки
                    <x-ui.badge size="xs">{{ $this->stats['total_dislikes'] }}</x-ui.badge>
                </x-ui.button>
                <x-ui.button wire:click="setTypeFilter('superlike')"
                    variant="{{ $typeFilter === 'superlike' ? 'default' : 'secondary' }}" size="sm">
                    <x-lucide-star class="w-3 h-3 inline mr-1" />
                    Суперлайки
                    <x-ui.badge size="xs">{{ $this->stats['total_superlikes'] }}</x-ui.badge>
                </x-ui.button>
            </div>
        @endif

        <div class="flex items-center gap-2 flex-1 justify-end ml-4">
            <x-ui.input wire:model.live.debounce.300ms="search" placeholder="Поиск по имени..." class="w-48" />
            <x-ui.date-picker wire:model.live="dateFrom" placeholder="с" width="w-[10rem]" />
            <span class="text-muted-foreground">—</span>
            <x-ui.date-picker wire:model.live="dateTo" placeholder="по" width="w-[10rem]" />
            <x-ui.button wire:click="$set('dateFrom', null); $set('dateTo', null); $set('search', '')" variant="outline"
                size="sm">
                <x-lucide-rotate-ccw class="w-4 h-4 inline mr-2" />
                <span>Сбросить</span>
            </x-ui.button>
        </div>
    </div>

    <!-- Фильтры + вкладки -->
    <div class="flex flex-wrap items-center gap-3 ">
        <!-- Вкладки: Свайпы / Матчи -->
        <div class="flex gap-1.5">
            <x-ui.button wire:click="setViewMode('swipes')"
                variant="{{ $viewMode === 'swipes' ? 'default' : 'secondary' }}" size="sm">
                Свайпы
                <x-ui.badge size="xs">{{ $this->stats['total_swipes'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="setViewMode('matches')"
                variant="{{ $viewMode === 'matches' ? 'default' : 'secondary' }}" size="sm">
                Матчи
                <x-ui.badge size="xs">{{ $this->stats['total_matches'] }}</x-ui.badge>
            </x-ui.button>
        </div>
    </div>

    <!-- Таблица -->
    <x-ui.table>
        <x-ui.table-header>
            <x-ui.table-row>
                <x-ui.table-head class="w-12">ID</x-ui.table-head>
                @if ($viewMode === 'swipes')
                    <x-ui.table-head>Оценил</x-ui.table-head>
                    <x-ui.table-head>Получил оценку</x-ui.table-head>
                    <x-ui.table-head>Тип</x-ui.table-head>
                @else
                    <x-ui.table-head>Пользователь 1</x-ui.table-head>
                    <x-ui.table-head>Пользователь 2</x-ui.table-head>
                @endif
                <x-ui.table-head>Дата</x-ui.table-head>
                <x-ui.table-head class="text-right">Действия</x-ui.table-head>
            </x-ui.table-row>
        </x-ui.table-header>
        <x-ui.table-body>
            @forelse ($this->items as $item)
                <x-ui.table-row wire:key="{{ $viewMode }}-{{ $item->id }}">
                    <x-ui.table-cell class="text-muted-foreground text-xs">{{ $item->id }}</x-ui.table-cell>

                    @if ($viewMode === 'swipes')
                        <x-ui.table-cell>
                            <div class="flex items-center">
                                <a href="{{ route('admin.users.show', $item->user) }}"
                                    class="flex gap-4 hover:text-primary text-sm">
                                    <x-avatar src="{{ $item->user->avatar_url }}" name="{{ $item->user->name }}"
                                        size="sm" userId="{{ $item->user->id }}" showStatus="true" />

                                    <div class="flex flex-col">
                                        <span>{{ $item->user->name }}<span class="text-xs text-muted-foreground">(ID:
                                                {{ $item->user->id }})</span></span>
                                        <span
                                            class="text-xs text-muted-foreground group-hover:text-primary/80 transition-colors">{{ $item->user->email }}</span>
                                    </div>
                                </a>
                            </div>
                        </x-ui.table-cell>
                        <x-ui.table-cell>
                            <div class="flex items-center">
                                <a href="{{ route('admin.users.show', $item->targetUser) }}"
                                    class="flex gap-4 hover:text-primary text-sm">
                                    <x-avatar src="{{ $item->targetUser->avatar_url }}"
                                        name="{{ $item->targetUser->name }}" size="sm"
                                        userId="{{ $item->targetUser->id }}" showStatus="true" />

                                    <div class="flex flex-col">
                                        <span>{{ $item->targetUser->name }}<span
                                                class="text-xs text-muted-foreground">(ID:
                                                {{ $item->targetUser->id }})</span></span>
                                        <span
                                            class="text-xs text-muted-foreground group-hover:text-primary/80 transition-colors">{{ $item->targetUser->email }}</span>
                                    </div>
                                </a>
                            </div>

                        </x-ui.table-cell>
                        <x-ui.table-cell>
                            @if ($item->type === 'like')
                                <x-ui.badge variant="success" size="xs">❤️ Лайк</x-ui.badge>
                            @elseif($item->type === 'superlike')
                                <x-ui.badge variant="warning" size="xs">⭐ Суперлайк</x-ui.badge>
                            @else
                                <x-ui.badge variant="destructive" size="xs">👎 Дизлайк</x-ui.badge>
                            @endif
                        </x-ui.table-cell>
                    @else
                        <x-ui.table-cell>
                            <div class="flex items-center">
                                <a href="{{ route('admin.users.show', $item->user1) }}"
                                    class="flex gap-4 hover:text-primary text-sm">
                                    <x-avatar src="{{ $item->user1->avatar_url }}" name="{{ $item->user1->name }}"
                                        size="sm" userId="{{ $item->user1->id }}" showStatus="true" />

                                    <div class="flex flex-col">
                                        <span>{{ $item->user1->name }}<span class="text-xs text-muted-foreground">(ID:
                                                {{ $item->user1->id }})</span></span>
                                        <span
                                            class="text-xs text-muted-foreground group-hover:text-primary/80 transition-colors">{{ $item->user1->email }}</span>
                                    </div>
                                </a>
                            </div>
                        </x-ui.table-cell>
                        <x-ui.table-cell>
                            <div class="flex items-center">
                                <a href="{{ route('admin.users.show', $item->user2) }}"
                                    class="flex gap-4 hover:text-primary text-sm">
                                    <x-avatar src="{{ $item->user2->avatar_url }}" name="{{ $item->user2->name }}"
                                        size="sm" userId="{{ $item->user2->id }}" showStatus="true" />

                                    <div class="flex flex-col">
                                        <span>{{ $item->user2->name }}<span class="text-xs text-muted-foreground">(ID:
                                                {{ $item->user2->id }})</span></span>
                                        <span
                                            class="text-xs text-muted-foreground group-hover:text-primary/80 transition-colors">{{ $item->user2->email }}</span>
                                    </div>
                                </a>
                            </div>
                        </x-ui.table-cell>
                    @endif

                    <x-ui.table-cell class="text-muted-foreground text-xs">
                        {{ $item->created_at->format('d.m.Y H:i') }}
                    </x-ui.table-cell>
                    <x-ui.table-cell class="text-right">
                        <x-ui.dropdown-menu>
                            <x-ui.dropdown-menu-trigger>
                                <x-ui.button variant="ghost" size="icon-sm">
                                    <x-lucide-more-horizontal class="w-4 h-4" />
                                </x-ui.button>
                            </x-ui.dropdown-menu-trigger>
                            <x-ui.dropdown-menu-content align="end">
                                <x-ui.dropdown-menu-item variant="destructive"
                                    wire:click="deleteItem({{ $item->id }})">
                                    <x-lucide-trash-2 class="w-4 h-4" />
                                    Удалить
                                </x-ui.dropdown-menu-item>
                            </x-ui.dropdown-menu-content>
                        </x-ui.dropdown-menu>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @empty
                <x-ui.table-row>
                    <x-ui.table-cell colspan="6" class="py-12 text-center text-muted-foreground">
                        <x-lucide-heart class="w-12 h-12 mx-auto mb-2 opacity-30" />
                        <p>Нет данных</p>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @endforelse
        </x-ui.table-body>
    </x-ui.table>

    <div class="mt-4">
        {{ $this->items->links('partials.pagination') }}
    </div>
</div>
