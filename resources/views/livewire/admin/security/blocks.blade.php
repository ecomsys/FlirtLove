<?php

use App\Models\AdminLog;
use App\Models\UserBlock;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    public string $search = '';
    public string $reasonFilter = 'all';
    public array $selectedBlocks = [];
    public bool $selectAll = false;
    public int $perPage = 15;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedReasonFilter(): void
    {
        $this->resetPage();
    }

    public function toggleSelectAll(): void
    {
        if ($this->selectAll) {
            $this->selectedBlocks = $this->blocks->getCollection()->pluck('id')->toArray();
        } else {
            $this->selectedBlocks = [];
        }
    }

    /**
     * Разблокировать конкретную пару.
     */
    public function unblock(int $id): void
    {
        $block = UserBlock::find($id);
        if ($block) {
            AdminLog::record('user.unblock', $block->blocker, auth()->user(), 
                ['unblocked_user_id' => $block->blocked_id], 
                ['block_reason' => $block->reason]
            );
            
            $block->delete();
            $this->dispatch('show-toast', type: 'success', message: 'Пользователь разблокирован');
        }
    }

    /**
     * Массовая разблокировка выбранных записей.
     */
    public function unblockSelected(): void
    {
        if (empty($this->selectedBlocks)) {
            $this->dispatch('show-toast', type: 'info', message: 'Выберите записи для разблокировки');
            return;
        }

        $count = count($this->selectedBlocks);
        UserBlock::whereIn('id', $this->selectedBlocks)->delete();

        AdminLog::record('user.mass_unblock', new UserBlock(), auth()->user(), [], ['count' => $count]);

        $this->selectedBlocks = [];
        $this->selectAll = false;
        $this->dispatch('show-toast', type: 'success', message: "Разблокировано пар: {$count}");
    }

    // ============================================
    // ВЫВОД ДАННЫХ
    // ============================================

    #[Computed]
    public function blocks()
    {
        $query = UserBlock::with(['blocker', 'blocked']);

        // Поиск по именам инициатора или жертвы
        if (!empty($this->search)) {
            $search = strtolower($this->search);
            $query->where(function ($q) use ($search) {
                $q->whereHas('blocker', fn($q) => $q->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('blocked', fn($q) => $q->where('name', 'like', "%{$search}%"))
                  ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        // Фильтр по причине
        if ($this->reasonFilter !== 'all') {
            if ($this->reasonFilter === 'no_reason') {
                $query->whereNull('reason');
            } else {
                $query->where('reason', $this->reasonFilter);
            }
        }

        return $query->latest()->paginate($this->perPage);
    }

    /**
     * Получение уникальных причин из БД для фильтра.
     */
    #[Computed]
    public function reasons(): \Illuminate\Support\Collection
    {
        return UserBlock::select('reason')
            ->whereNotNull('reason')
            ->distinct()
            ->orderBy('reason')
            ->pluck('reason');
    }

    /**
     * Статистика для бейджей.
     */
    #[Computed]
    public function counts(): array
    {
        return [
            'total' => UserBlock::count(),
            'with_reason' => UserBlock::whereNotNull('reason')->count(),
            'no_reason' => UserBlock::whereNull('reason')->count(),
        ];
    }
}; 
?>

<div class="space-y-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <h1 class="text-2xl font-semibold flex items-center gap-2">
            <x-lucide-ban class="w-6 h-6" />
            Черные списки (Блокировки)
        </h1>
        
        @if(count($selectedBlocks) > 0)
            <x-ui.alert-dialog>
                <x-ui.alert-dialog-trigger>
                    <x-ui.button variant="destructive" size="sm" class="gap-2">
                        <x-lucide-lock-open class="w-4 h-4 inline" />
                        Разблокировать выбранные
                        <x-ui.badge variant="warning" size="xs">{{ count($selectedBlocks) }}</x-ui.badge>
                    </x-ui.button>
                </x-ui.alert-dialog-trigger>
                <x-ui.alert-dialog-content>
                    <x-ui.alert-dialog-header>
                        <x-ui.alert-dialog-title>Массовая разблокировка</x-ui.alert-dialog-title>
                        <x-ui.alert-dialog-description>
                            Вы уверены? Выбранные пользователи снова смогут видеть друг друга и писать. 
                            Это действие <strong class="text-destructive">нельзя отменить</strong>.
                        </x-ui.alert-dialog-description>
                    </x-ui.alert-dialog-header>
                    <x-ui.alert-dialog-footer>
                        <x-ui.alert-dialog-cancel>Отмена</x-ui.alert-dialog-cancel>
                        <x-ui.alert-dialog-action wire:click="unblockSelected">
                            <x-lucide-lock-open class="w-4 h-4" />
                            Разблокировать
                        </x-ui.alert-dialog-action>
                    </x-ui.alert-dialog-footer>
                </x-ui.alert-dialog-content>
            </x-ui.alert-dialog>
        @endif
    </div>

    <!-- Фильтры -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-1.5">
            <x-ui.button wire:click="$set('reasonFilter', 'all')" variant="{{ $reasonFilter === 'all' ? 'default' : 'secondary' }}" size="sm">
                Все <x-ui.badge size="xs">{{ $this->counts['total'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="$set('reasonFilter', 'no_reason')" variant="{{ $reasonFilter === 'no_reason' ? 'default' : 'secondary' }}" size="sm">
                Без причины <x-ui.badge size="xs" variant="outline">{{ $this->counts['no_reason'] }}</x-ui.badge>
            </x-ui.button>
            
            @foreach($this->reasons as $reason)
                <x-ui.button wire:click="$set('reasonFilter', '{{ $reason }}')" variant="{{ $reasonFilter === $reason ? 'default' : 'secondary' }}" size="sm">
                    {{ ucfirst($reason) }}
                </x-ui.button>
            @endforeach
        </div>

        <div class="relative w-64">
            <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск по именам или причине..." class="pl-9 pr-8" />
            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            @if(!empty($search))
                <button wire:click="$set('search', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
                    <x-lucide-x class="w-4 h-4" />
                </button>
            @endif
        </div>
    </div>

    <!-- Таблица -->
    <x-ui.table>
        <x-ui.table-header>
            <x-ui.table-row>
                <x-ui.table-head class="w-8">
                    <x-checkbox wire:model.live="selectAll" wire:change="toggleSelectAll" />
                </x-ui.table-head>
                <x-ui.table-head>Кто заблокировал (Инициатор)</x-ui.table-head>
                <x-ui.table-head></x-ui.table-head>
                <x-ui.table-head>Кого заблокировали (Жертва)</x-ui.table-head>
                <x-ui.table-head>Причина</x-ui.table-head>
                <x-ui.table-head>Дата блокировки</x-ui.table-head>
                <x-ui.table-head class="text-right">Действия</x-ui.table-row>
            </x-ui.table-row>
        </x-ui.table-header>

        <x-ui.table-body>
            @forelse ($this->blocks as $block)
                <x-ui.table-row wire:key="block-{{ $block->id }}">
                    <x-ui.table-cell class="w-8">
                        <x-checkbox wire:model.live="selectedBlocks" value="{{ $block->id }}" />
                    </x-ui.table-cell>
                    
                    <!-- Инициатор -->
                    <x-ui.table-cell>
                        @if($block->blocker)
                            <div class="flex items-center gap-2">
                                <x-avatar src="{{ $block->blocker->avatar_url }}" name="{{ $block->blocker->name }}" size="sm" />
                                <div>
                                    <div class="text-sm font-medium">{{ $block->blocker->name }}</div>
                                    <div class="text-xs text-muted-foreground">ID: {{ $block->blocker_id }}</div>
                                </div>
                            </div>
                        @else
                            <span class="text-xs text-muted-foreground">Удален</span>
                        @endif
                    </x-ui.table-cell>

                    <!-- Стрелочка -->
                    <x-ui.table-cell class="text-muted-foreground">
                        <x-lucide-arrow-right class="w-4 h-4" />
                    </x-ui.table-cell>

                    <!-- Заблокированный -->
                    <x-ui.table-cell>
                        @if($block->blocked)
                            <div class="flex items-center gap-2">
                                <x-avatar src="{{ $block->blocked->avatar_url }}" name="{{ $block->blocked->name }}" size="sm" />
                                <div>
                                    <div class="text-sm font-medium">{{ $block->blocked->name }}</div>
                                    <div class="text-xs text-muted-foreground">ID: {{ $block->blocked_id }}</div>
                                </div>
                            </div>
                        @else
                            <span class="text-xs text-muted-foreground">Удален</span>
                        @endif
                    </x-ui.table-cell>

                    <!-- Причина -->
                    <x-ui.table-cell>
                        @if($block->reason)
                            <x-ui.badge variant="outline" size="sm">{{ $block->reason }}</x-ui.badge>
                        @else
                            <span class="text-xs text-muted-foreground">Не указана</span>
                        @endif
                    </x-ui.table-cell>

                    <!-- Дата -->
                    <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap">
                        {{ $block->created_at->format('d.m.Y H:i') }}
                    </x-ui.table-cell>

                    <!-- Действия -->
                    <x-ui.table-cell class="text-right">
                        <x-ui.alert-dialog>
                            <x-ui.alert-dialog-trigger>
                                <x-ui.button variant="ghost" size="icon-sm" class="text-destructive hover:text-destructive">
                                    <x-lucide-lock-open class="w-4 h-4" />
                                </x-ui.button>
                            </x-ui.alert-dialog-trigger>
                            <x-ui.alert-dialog-content>
                                <x-ui.alert-dialog-header>
                                    <x-ui.alert-dialog-title>Снять блокировку?</x-ui.alert-dialog-title>
                                    <x-ui.alert-dialog-description>
                                        Вы уверены, что хотите разблокировать <strong>{{ $block->blocked?->name }}</strong> для <strong>{{ $block->blocker?->name }}</strong>? Они снова смогут видеть анкеты друг друга.
                                    </x-ui.alert-dialog-description>
                                </x-ui.alert-dialog-header>
                                <x-ui.alert-dialog-footer>
                                    <x-ui.alert-dialog-cancel>Отмена</x-ui.alert-dialog-cancel>
                                    <x-ui.alert-dialog-action wire:click="unblock({{ $block->id }})">
                                        Разблокировать
                                    </x-ui.alert-dialog-action>
                                </x-ui.alert-dialog-footer>
                            </x-ui.alert-dialog-content>
                        </x-ui.alert-dialog>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @empty
                <x-ui.table-row>
                    <x-ui.table-cell colspan="7" class="py-12 text-center text-muted-foreground">
                        <div class="flex flex-col items-center gap-2">
                            <x-lucide-shield-check class="w-12 h-12 opacity-30" />
                            <p>Блокировок не найдено</p>
                        </div>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @endforelse
        </x-ui.table-body>
    </x-ui.table>

    <!-- Пагинация -->
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div class="text-xs text-muted-foreground">
            Показано {{ $this->blocks->firstItem() ?? 0 }} - {{ $this->blocks->lastItem() ?? 0 }} из {{ $this->blocks->total() }}
        </div>
        {{ $this->blocks->links('partials.pagination') }}
    </div>
</div>