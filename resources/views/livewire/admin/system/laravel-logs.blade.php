<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    public string $search = '';
    public string $levelFilter = 'all';
    public string $dateFilter = '';
    public int $perPage = 50;

    // Авторизация: только админы могут смотреть системные логи
    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
        $this->levelFilter = session('admin_logs_level', 'all');
    }

    // Сброс пагинации при поиске
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    // Сброс пагинации при смене даты
    public function updatedDateFilter(): void
    {
        $this->resetPage();
    }

    // Установка фильтра уровня (с сохранением в сессию)
    public function setLevelFilter(string $level): void
    {
        $this->levelFilter = $level;
        session(['admin_logs_level' => $level]);
        $this->resetPage();
    }

    // Очистка файла логов
    public function clearLogs(): void
    {
        $logPath = storage_path('logs/laravel.log');
        
        try {
            if (File::exists($logPath)) {
                File::put($logPath, ''); // Очищаем файл, не удаляя его
            }
            
            Log::info('Логи очищены администратором');
            $this->dispatch('show-toast', type: 'success', message: 'Логи очищены');
        } catch (\Exception $e) {
            $this->dispatch('show-toast', type: 'error', message: 'Не удалось очистить логи: ' . $e->getMessage());
        }
    }

    // ============================================
    // ВЫВОД ДАННЫХ (Computed)
    // ============================================

    #[Computed]
    public function logs()
    {
        $logPath = storage_path('logs/laravel.log');
        
        if (!File::exists($logPath)) {
            return new LengthAwarePaginator([], 0, $this->perPage, 1);
        }

        $levelColors = [
            'ERROR'      => 'bg-red-500/10 text-red-500',
            'WARNING'    => 'bg-yellow-500/10 text-yellow-500',
            'INFO'       => 'bg-blue-500/10 text-blue-500',
            'DEBUG'      => 'bg-gray-500/10 text-gray-500',
            'NOTICE'     => 'bg-purple-500/10 text-purple-500',
            'CRITICAL'   => 'bg-red-700/10 text-red-700',
            'ALERT'      => 'bg-orange-500/10 text-orange-500',
            'EMERGENCY'  => 'bg-red-900/10 text-red-900',
        ];
        
        $logs = [];
        $currentEntry = null;

        // ИСПОЛЬЗУЕМ ГЕНЕРАТОР File::lines() ДЛЯ ЭКОНОМИИ ПАМЯТИ!
        // Читаем файл построчно, не грузя весь файл в ОЗУ.
        foreach (File::lines($logPath) as $line) {
            // Если строка начинается с даты — это новая запись лога
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+(\w+)\.(\w+):\s?(.*)/', $line, $matches)) {
                if ($currentEntry) {
                    $logs[] = $currentEntry;
                }
                
                $level = $matches[3];
                $currentEntry = [
                    'timestamp'   => $matches[1],
                    'environment' => $matches[2],
                    'level'       => $level,
                    'message'     => $matches[4],
                    'trace'       => '',
                    'full'        => $line,
                    'level_color' => $levelColors[$level] ?? 'bg-muted text-muted-foreground',
                ];
            } elseif ($currentEntry) {
                // Если строка не начинается с даты — это стек-трейс предыдущей записи
                $currentEntry['trace'] .= $line . "\n";
                $currentEntry['full'] .= "\n" . $line;
            }
        }
        
        // Добавляем самую последнюю запись
        if ($currentEntry) {
            $logs[] = $currentEntry;
        }
        
        // Сортируем от новых к старым
        $logs = array_reverse($logs);
        
        // Применяем фильтры
        if ($this->levelFilter !== 'all') {
            $logs = array_filter($logs, fn($log) => $log['level'] === $this->levelFilter);
        }

        if (!empty($this->search)) {
            $search = strtolower($this->search);
            $logs = array_filter($logs, function ($log) use ($search) {
                return str_contains(strtolower($log['message']), $search) ||
                       str_contains(strtolower($log['full']), $search);
            });
        }

        if (!empty($this->dateFilter)) {
            $logs = array_filter($logs, fn($log) => str_starts_with($log['timestamp'], $this->dateFilter));
        }

        // ВАЖНО: Переиндексируем ключи массива после фильтрации!
        // Иначе пагинация Laravel получит массив с дырами (0, 5, 14...) и сломается.
        $logs = array_values($logs);

        $total = count($logs);
        $page = Paginator::resolveCurrentPage('page');
        
        $offset = ($page - 1) * $this->perPage;
        $paginatedLogs = array_slice($logs, $offset, $this->perPage);

        return new LengthAwarePaginator(
            $paginatedLogs,
            $total,
            $this->perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()]
        );
    }

    #[Computed]
    public function stats(): array
    {
        $logPath = storage_path('logs/laravel.log');
        $stats = ['total_entries' => 0, 'levels' => []];

        if (!File::exists($logPath)) {
            return $stats;
        }

        // Тоже используем генератор для безопасности памяти
        foreach (File::lines($logPath) as $line) {
            if (preg_match('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]\s+\w+\.(\w+):/', $line, $matches)) {
                $level = $matches[1];
                $stats['total_entries']++;
                $stats['levels'][$level] = ($stats['levels'][$level] ?? 0) + 1;
            }
        }

        return $stats;
    }

    #[Computed]
    public function logLevels(): array
    {
        return ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];
    }

    #[Computed]
    public function logSize(): string
    {
        $logPath = storage_path('logs/laravel.log');
        if (!File::exists($logPath)) {
            return '0 B';
        }

        $size = File::size($logPath);
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 2) . ' ' . $units[$i];
    }
}; 
?>

<div class="space-y-6 pb-6">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h1 class="text-2xl font-semibold flex items-center gap-2">
                <x-lucide-file-text class="w-6 h-6" />
                Системные логи
            </h1>
            <p class="text-sm text-muted-foreground">
                Размер файла: {{ $this->logSize }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <x-ui.button wire:click="$refresh" variant="outline" size="sm">
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
            <x-ui.badge size="xs">{{ $this->stats['total_entries'] }}</x-ui.badge>
        </x-ui.button>
        
        @foreach($this->logLevels as $level)
            @php
                $count = $this->stats['levels'][$level] ?? 0;
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
            <x-ui.date-picker wire:model.live="dateFilter" placeholder="Дата" width="w-[10rem]" wire:key="date-filter" />

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
            @forelse ($this->logs as $index => $log)
                <!-- Сделали wire:key уникальным и стабильным -->
                <x-ui.table-row wire:key="log-{{ $log['timestamp'] }}-{{ $index }}">
                    <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap">
                        {{ \Illuminate\Support\Carbon::parse($log['timestamp'])->format('d.m.Y H:i:s') }}
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
        {{ $this->logs->links('partials.pagination') }}
    </div>

    <!-- Info -->
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div class="text-xs text-muted-foreground">
            Показано {{ $this->logs->count() }} из {{ $this->logs->total() }} записей
            @if(!empty($search))
                <span class="ml-2">(фильтр: "{{ $search }}")</span>
            @endif
            @if($levelFilter !== 'all')
                <span class="ml-2">(уровень: {{ $levelFilter }})</span>
            @endif
        </div>
    </div>
</div>