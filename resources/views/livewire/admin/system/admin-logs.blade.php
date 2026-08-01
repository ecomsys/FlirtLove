<?php

use App\Models\AdminLog;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    public string $search = '';
    public string $actionFilter = 'all';
    public ?int $adminFilter = null;
    public string $dateFilter = '';
    public int $perPage = 50;

    /**
     * Загрузка компонента. Восстанавливаем фильтры из сессии.
     */
    public function mount(): void
    {
        $this->actionFilter = session('admin_audit_action', 'all');
        $this->adminFilter = session('admin_audit_admin', null);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDateFilter(): void
    {
        $this->resetPage();
    }

    public function updatedAdminFilter(): void
    {
        session(['admin_audit_admin' => $this->adminFilter]);
        $this->resetPage();
    }

    /**
     * Установка фильтра по типу действия.
     */
    public function setActionFilter(string $action): void
    {
        $this->actionFilter = $action;
        session(['admin_audit_action' => $action]);
        $this->resetPage();
    }

    // ============================================
    // ВЫВОД ДАННЫХ (Computed)
    // ============================================

    /**
     * Получение логов с фильтрацией и пагинацией.
     */
    #[Computed]
    public function logs(): LengthAwarePaginator
    {
        $query = AdminLog::with(['admin', 'loggable'])->latest();

        // Фильтр по типу действия
        if ($this->actionFilter !== 'all') {
            $query->where('action', $this->actionFilter);
        }

        // Фильтр по админу
        if ($this->adminFilter) {
            $query->where('admin_id', $this->adminFilter);
        }

        // Фильтр по дате
        if ($this->dateFilter) {
            $query->whereDate('created_at', $this->dateFilter);
        }

        // Поиск
        if (!empty($this->search)) {
            $search = strtolower($this->search);
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhere('loggable_type', 'like', "%{$search}%")
                  ->orWhere('loggable_id', $search);
            });
        }

        return $query->paginate($this->perPage);
    }

    /**
     * Получение доступных действий для фильтров со счетчиками.
     */
    #[Computed]
    public function actions(): \Illuminate\Support\Collection
    {
        return AdminLog::selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderBy('action')
            ->pluck('count', 'action');
    }

    /**
     * Список админов, которые совершали действия (для фильтра).
     */
    #[Computed]
    public function admins()
    {
        return User::whereHas('adminLogs')->select('id', 'name')->orderBy('name')->get();
    }

    /**
     * Общее количество записей в базе.
     */
    #[Computed]
    public function totalEntries(): int
    {
        return AdminLog::count();
    }
};
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h1 class="text-2xl font-semibold flex items-center gap-2">
                <x-lucide-shield-check class="w-6 h-6" />
                Журнал аудита
            </h1>
            <p class="text-sm text-muted-foreground">
                Всего записей: {{ $this->totalEntries }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <x-ui.button wire:click="$refresh" variant="outline" size="sm">
                <x-lucide-refresh-ccw class="w-4 h-4" />
            </x-ui.button>
            
            <!-- Кнопка очистки УМЫШЛЕННО ОТСУТСТВУЕТ согласно правилам миграции: "Логи нельзя удалять никак" -->
        </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-2">
        <!-- Кнопка "Все" -->
        <x-ui.button
            wire:click="setActionFilter('all')"
            variant="{{ $actionFilter === 'all' ? 'default' : 'secondary' }}"
            size="sm"
            class="flex items-center gap-1.5"
        >
            Все
            <x-ui.badge size="xs">{{ $this->totalEntries }}</x-ui.badge>
        </x-ui.button>
        
        <!-- Фильтры по типу действия -->
        @foreach($this->actions as $action => $count)
            @php
                // Раскраска кнопок по типу действия
                $actionColor = match(true) {
                    str_contains($action, 'ban') || str_contains($action, 'delete') || str_contains($action, 'reject') => 'text-red-500',
                    str_contains($action, 'approve') || str_contains($action, 'unban') || str_contains($action, 'activate') => 'text-green-500',
                    str_contains($action, 'update') || str_contains($action, 'edit') => 'text-blue-500',
                    str_contains($action, 'refund') || str_contains($action, 'warning') => 'text-yellow-500',
                    default => 'text-muted-foreground',
                };
            @endphp
            <x-ui.button
                wire:click="setActionFilter('{{ $action }}')"
                variant="{{ $actionFilter === $action ? 'default' : 'secondary' }}"
                size="sm"
                class="flex items-center gap-1.5"
            >
                <span class="{{ $actionColor }}">
                    <x-lucide-git-branch class="w-4 h-4" />
                </span>
                {{ $action }}
                <x-ui.badge variant="outline" size="xs">{{ $count }}</x-ui.badge>
            </x-ui.button>
        @endforeach

        <div class="flex items-center gap-2 ml-auto">
            <!-- Фильтр по Админу -->
            <select 
                wire:model.live="adminFilter" 
                class="flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus:outline-none focus:ring-1 focus:ring-ring"
            >
                <option value="">Все админы</option>
                @foreach($this->admins as $admin)
                    <option value="{{ $admin->id }}">{{ $admin->name }}</option>
                @endforeach
            </select>

            <!-- Фильтр по дате -->
            <x-ui.date-picker wire:model.live="dateFilter" placeholder="Дата" width="w-[10rem]" wire:key="date-filter-audit" />

            <!-- Поиск -->
            <div class="relative w-64">
                <x-ui.input
                    wire:model.live.debounce.300ms="search"
                    type="search"
                    placeholder="Поиск (IP, action, ID)..."
                    class="pl-9 pr-8"
                />
                <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                @if(!empty($search))
                    <button
                        wire:click="$set('search', '')"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    >
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Table -->
    <x-ui.table>
        <x-ui.table-header>
            <x-ui.table-row>
                <x-ui.table-head class="w-40">Время</x-ui.table-head>
                <x-ui.table-head class="w-32">Админ</x-ui.table-head>
                <x-ui.table-head class="w-40">Действие</x-ui.table-head>
                <x-ui.table-head class="w-40">Объект</x-ui.table-head>
                <x-ui.table-head>Изменения (Before / After)</x-ui.table-head>
                <x-ui.table-head class="w-32">IP</x-ui.table-head>
            </x-ui.table-row>
        </x-ui.table-header>

        <x-ui.table-body>
            @forelse ($this->logs as $log)
                <x-ui.table-row wire:key="log-{{ $log->id }}">
                    <!-- Время -->
                    <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap">
                        {{ $log->created_at->format('d.m.Y H:i:s') }}
                    </x-ui.table-cell>

                    <!-- Админ -->
                    <x-ui.table-cell>
                        @if($log->admin)
                            <span class="text-sm font-medium">{{ $log->admin->name }}</span>
                        @else
                            <x-ui.badge variant="secondary" size="sm">Система</x-ui.badge>
                        @endif
                    </x-ui.table-cell>

                    <!-- Действие -->
                    <x-ui.table-cell>
                        @php
                            $badgeColor = match(true) {
                                str_contains($log->action, 'ban') || str_contains($log->action, 'delete') => 'bg-red-500/10 text-red-500',
                                str_contains($log->action, 'approve') || str_contains($log->action, 'unban') => 'bg-green-500/10 text-green-500',
                                str_contains($log->action, 'update') => 'bg-blue-500/10 text-blue-500',
                                default => 'bg-muted text-muted-foreground',
                            };
                        @endphp
                        <span class="px-2 py-0.5 rounded text-xs font-medium {{ $badgeColor }}">
                            {{ $log->action }}
                        </span>
                    </x-ui.table-cell>

                    <!-- Объект (loggable) -->
                    <x-ui.table-cell class="text-sm">
                        @if($log->loggable)
                            <span class="text-muted-foreground">{{ class_basename($log->loggable_type) }}</span> #{{ $log->loggable_id }}
                        @else
                            <span class="text-muted-foreground">{{ class_basename($log->loggable_type) }}</span> #{{ $log->loggable_id }} <span class="text-xs text-destructive">(удален)</span>
                        @endif
                    </x-ui.table-cell>

                    <!-- Изменения (Diff) -->
                    <x-ui.table-cell class="max-w-[25rem] whitespace-normal">
                        @if($log->before || $log->after)
                            <details class="group">
                                <summary class="cursor-pointer text-sm hover:text-primary transition-colors flex items-center gap-1">
                                    <x-lucide-code class="w-4 h-4 text-muted-foreground" />
                                    Показать дифф
                                    <x-lucide-chevron-down class="shrink-0 w-4 h-4 inline group-open:rotate-180 transition-transform" />
                                </summary>
                                <div class="mt-2 p-3 bg-muted/30 rounded-lg overflow-x-auto space-y-2">
                                    @if($log->before)
                                        <div class="text-xs">
                                            <span class="text-red-500 font-bold">BEFORE:</span>
                                            <pre class="text-muted-foreground whitespace-pre-wrap font-mono mt-1">{{ json_encode($log->before, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    @endif
                                    
                                    @if($log->before && $log->after)
                                        <x-lucide-arrow-down class="w-4 h-4 text-muted-foreground" />
                                    @endif

                                    @if($log->after)
                                        <div class="text-xs">
                                            <span class="text-green-500 font-bold">AFTER:</span>
                                            <pre class="text-muted-foreground whitespace-pre-wrap font-mono mt-1">{{ json_encode($log->after, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    @endif
                                </div>
                            </details>
                        @else
                            <span class="text-xs text-muted-foreground">—</span>
                        @endif
                    </x-ui.table-cell>

                    <!-- IP -->
                    <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap">
                        <div class="flex items-center gap-1.5">
                            <x-lucide-globe class="w-3 h-3" />
                            {{ $log->ip_address }}
                        </div>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @empty
                <x-ui.table-row>
                    <x-ui.table-cell colspan="6" class="py-12 text-center text-muted-foreground">
                        <div class="flex flex-col items-center gap-2">
                            <x-lucide-shield-question class="w-12 h-12 opacity-30" />
                            <p>Логи не найдены</p>
                            @if(!empty($search) || $actionFilter !== 'all' || !empty($dateFilter) || $adminFilter)
                                <x-ui.button
                                    wire:click="$set('search', ''); $set('actionFilter', 'all'); $set('dateFilter', ''); $set('adminFilter', null)"
                                    variant="outline"
                                    size="sm"
                                >
                                    Сбросить фильтры
                                </x-ui.button>
                            @endif
                        </div>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @endforelse
        </x-ui.table-body>
    </x-ui.table>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $this->logs->links('partials.pagination') }}
    </div>

    <!-- Info -->
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div class="text-xs text-muted-foreground">
            Показано {{ $this->logs->count() }} из {{ $this->logs->total() }} записей
            @if(!empty($search))
                <span class="ml-2">(фильтр: "{{ $search }}")</span>
            @endif
            @if($actionFilter !== 'all')
                <span class="ml-2">(действие: {{ $actionFilter }})</span>
            @endif
        </div>
    </div>
</div>