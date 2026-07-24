<?php

use App\Services\LogService;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| Системные логи (админка)
|--------------------------------------------------------------------------
| Компонент для просмотра, фильтрации и очистки системных логов.
| Использует LogService для парсинга и пагинации.
*/
new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    public int $page = 1;
    public string $search = '';
    public string $levelFilter = 'all';
    public string $dateFilter = '';
    public int $perPage = 50;
    public int $totalLogs = 0;

    protected LogService $logService;

    /**
     * Инициализация сервиса логов
     */
    public function boot(LogService $logService): void
    {
        $this->logService = $logService;
    }

    /**
     * Получение данных для представления
     */
    public function with(): array
    {
        $filters = [];

        if ($this->levelFilter !== 'all') {
            $filters['level'] = $this->levelFilter;
        }

        if (!empty($this->search)) {
            $filters['search'] = $this->search;
        }

        if (!empty($this->dateFilter)) {
            $filters['date'] = $this->dateFilter;
        }

        $data = $this->logService->getLogs($filters, $this->perPage, $this->page);

        return [
            'logs' => $data['logs'],
            'total' => $data['total'],
            'stats' => $data['stats'],
            'logLevels' => $this->logService->getLogLevels(),
            'logSize' => $this->logService->getLogSize(),
        ];
    }

    /**
     * Сброс пагинации при смене страницы
     */
    public function updatedPage($page): void
    {
        $this->page = $page;
    }

    /**
     * Очистка всех системных логов
     */
    public function clearLogs(): void
    {
        $result = $this->logService->clear();

        if ($result) {
            $this->dispatch('show-toast', 
                type: 'success', 
                message: 'Логи очищены'
            );
            $this->dispatch('$refresh');
        } else {
            $this->dispatch('show-toast', 
                type: 'error', 
                message: 'Не удалось очистить логи'
            );
        }
    }

    /**
     * Установка фильтра по уровню ошибки
     */
    public function setLevelFilter(string $level): void
    {
        $this->levelFilter = $level;
        $this->resetPage();
    }

    /**
     * Сброс пагинации при изменении поиска
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Сброс пагинации при изменении даты
     */
    public function updatedDateFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Принудительное обновление данных
     */
    public function refresh(): void
    {
        $this->dispatch('$refresh');
        $this->dispatch('show-toast', 
            type: 'success', 
            message: 'Логи обновлены'
        );
    }
}; ?>



<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h1 class="text-2xl font-semibold flex items-center gap-2">
                <x-lucide-file-text class="w-6 h-6" />
                Системные логи
            </h1>
            <p class="text-sm text-muted-foreground">
                Размер файла: {{ $logSize }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <x-ui.button wire:click="refresh" variant="outline" size="sm">
                <x-lucide-refresh-ccw class="w-4 h-4" />
            </x-ui.button>

            <x-ui.alert-dialog>
                <x-ui.alert-dialog-trigger>
                    <x-ui.button variant="destructive" size="sm">
                        <x-lucide-trash-2 class="w-4 h-4" />
                        Очистить логи
                    </x-ui.button>
                </x-ui.alert-dialog-trigger>
                <x-ui.alert-dialog-content>
                    <x-ui.alert-dialog-header>
                        <x-ui.alert-dialog-title>Очистка системных логов</x-ui.alert-dialog-title>
                        <x-ui.alert-dialog-description>
                            Вы уверены? Все логи будут удалены безвозвратно.
                            Это действие <strong class="text-destructive">нельзя отменить</strong>.
                        </x-ui.alert-dialog-description>
                    </x-ui.alert-dialog-header>
                    <x-ui.alert-dialog-footer>
                        <x-ui.alert-dialog-cancel>Отмена</x-ui.alert-dialog-cancel>
                        <x-ui.alert-dialog-action wire:click="clearLogs">
                            <x-lucide-trash-2 class="w-4 h-4" />
                            Очистить
                        </x-ui.alert-dialog-action>
                    </x-ui.alert-dialog-footer>
                </x-ui.alert-dialog-content>
            </x-ui.alert-dialog>
        </div>
    </div>

<!-- Filters -->
<div class="flex flex-wrap items-center gap-2">
    <x-ui.button
        wire:click="setLevelFilter('all')"
        variant="{{ $levelFilter === 'all' ? 'default' : 'secondary' }}"
        size="sm"
        class="flex items-center gap-1.5"
    >
        Все
        <x-ui.badge size="xs">{{ $total }}</x-ui.badge>
    </x-ui.button>
    @foreach($logLevels as $level)
        @php
            $count = $stats['levels'][$level] ?? 0;
        @endphp
        @if($count > 0)
            <x-ui.button
                wire:click="setLevelFilter('{{ $level }}')"
                variant="{{ $levelFilter === $level ? 'default' : 'secondary' }}"
                size="sm"
                class="flex items-center gap-1.5"
            >
                <span class="
                    @if($level === 'ERROR') text-red-500
                    @elseif($level === 'WARNING') text-yellow-500
                    @elseif($level === 'INFO') text-blue-500
                    @elseif($level === 'DEBUG') text-gray-500
                    @elseif($level === 'NOTICE') text-purple-500
                    @elseif($level === 'CRITICAL') text-red-700
                    @elseif($level === 'ALERT') text-orange-500
                    @elseif($level === 'EMERGENCY') text-red-900
                    @else text-muted-foreground
                    @endif
                ">
                    @if($level === 'ERROR')
                        <x-lucide-circle-x class="w-4 h-4" />
                    @elseif($level === 'WARNING')
                        <x-lucide-triangle-alert class="w-4 h-4" />
                    @elseif($level === 'INFO')
                        <x-lucide-info class="w-4 h-4" />
                    @elseif($level === 'DEBUG')
                        <x-lucide-bug class="w-4 h-4" />
                    @elseif($level === 'NOTICE')
                        <x-lucide-megaphone class="w-4 h-4" />
                    @elseif($level === 'CRITICAL')
                        <x-lucide-skull class="w-4 h-4" />
                    @elseif($level === 'ALERT')
                        <x-lucide-bell class="w-4 h-4" />
                    @elseif($level === 'EMERGENCY')
                        <x-lucide-flame class="w-4 h-4" />
                    @else
                        <x-lucide-file-text class="w-4 h-4" />
                    @endif
                </span>
                {{ $level }}
                <x-ui.badge variant="outline" size="xs">{{ $count }}</x-ui.badge>
            </x-ui.button>
        @endif
    @endforeach

    <div class="flex items-center gap-2 ml-auto">
        <x-ui.input
            wire:model.live.debounce.300ms="dateFilter"
            type="date"
            class="w-40"
            placeholder="Дата"
        />

        <div class="relative w-64">
            <x-ui.input
                wire:model.live.debounce.300ms="search"
                type="search"
                placeholder="Поиск по тексту..."
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
                <x-ui.table-head class="w-24">Уровень</x-ui.table-head>
                <x-ui.table-head>Сообщение</x-ui.table-head>
            </x-ui.table-row>
        </x-ui.table-header>

        <x-ui.table-body>
            @forelse ($logs as $log)
                <x-ui.table-row wire:key="log-{{ $loop->index }}">
                    <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap">
                        {{ \Carbon\Carbon::parse($log['timestamp'])->format('d.m.Y H:i:s') }}
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        <span class="px-2 py-0.5 rounded text-xs font-medium {{ $log['level_color'] ?? 'bg-muted text-muted-foreground' }}">
                            {{ $log['level'] }}
                        </span>
                    </x-ui.table-cell>
                    <x-ui.table-cell class="max-w-[25rem] whitespace-normal">
                        <div class="min-w-0">
                            <details class="group">
                                <summary class="cursor-pointer text-sm hover:text-primary transition-colors flex items-center gap-1">
                                    <span class="line-clamp-2">{{ $log['message'] }}</span>
                                    <x-lucide-chevron-down class="shrink-0 w-4 h-4 inline group-open:rotate-180 transition-transform" />
                                </summary>
                                <div class="mt-2 p-3 bg-muted/30 rounded-lg overflow-x-auto">
                                    <pre class="text-xs text-muted-foreground whitespace-pre-wrap font-mono">{{ $log['full'] ?? $log['message'] }}</pre>
                                </div>
                            </details>
                        </div>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @empty
                <x-ui.table-row>
                    <x-ui.table-cell colspan="3" class="py-12 text-center text-muted-foreground">
                        <div class="flex flex-col items-center gap-2">
                            <x-lucide-file-text class="w-12 h-12 opacity-30" />
                            <p>Логи не найдены</p>
                            @if(!empty($search) || $levelFilter !== 'all' || !empty($dateFilter))
                                <x-ui.button
                                    wire:click="$set('search', ''); $set('levelFilter', 'all'); $set('dateFilter', '')"
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
        {{ $logs->links('partials.pagination') }}
    </div>

    <!-- Info -->
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div class="text-xs text-muted-foreground">
            Показано {{ count($logs) }} из {{ $total }} записей
            @if(!empty($search))
                <span class="ml-2">(фильтр: "{{ $search }}")</span>
            @endif
            @if($levelFilter !== 'all')
                <span class="ml-2">(уровень: {{ $levelFilter }})</span>
            @endif
        </div>
    </div>
</div>