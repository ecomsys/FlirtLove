<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class LogService
{
    protected string $logPath;

    public function __construct()
    {
        $this->logPath = storage_path('logs/laravel.log');
    }

    /**
     * Получить логи с фильтрацией и пагинацией
     *
     * @param array $filters
     * @param int $perPage
     * @param int $page
     * @return array
     */
    public function getLogs(array $filters = [], int $perPage = 50, int $page = 1): array
    {
        if (!File::exists($this->logPath)) {
            return [
                'logs' => [],
                'total' => 0,
                'stats' => $this->getStats(),
            ];
        }

        $content = File::get($this->logPath);
        
        // Разделяем файл на отдельные записи по шаблону даты
        $pattern = '/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/m';
        $entries = preg_split($pattern, $content, -1, PREG_SPLIT_NO_EMPTY);
        preg_match_all($pattern, $content, $dates);
        $dates = $dates[0];
        
        $logs = [];
        $errorTypes = [];

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
        
        $totalEntries = count($entries);
        
        for ($i = 0; $i < $totalEntries; $i++) {
            $fullEntry = trim($entries[$i]);
            if (empty($fullEntry)) {
                continue;
            }
            
            $fullLog = ($dates[$i] ?? '') . $fullEntry;
            $firstLine = explode("\n", $fullEntry)[0];
            
            if (preg_match('/^(\w+)\.(\w+):/', $firstLine, $matches)) {
                $level = $matches[2];
                $message = substr($firstLine, strpos($firstLine, ': ') + 2);
                
                $logs[] = [
                    'timestamp'    => trim($dates[$i] ?? '', '[]'),
                    'environment'  => $matches[1],
                    'level'        => $level,
                    'message'      => $message,
                    'trace'        => '',
                    'full'         => $fullLog,
                    'level_color'  => $levelColors[$level] ?? 'bg-muted text-muted-foreground',
                ];
                
                if (!isset($errorTypes[$level])) {
                    $errorTypes[$level] = 0;
                }
                $errorTypes[$level]++;
            }
        }
        
        $logs = array_reverse($logs);
        
        // Применяем фильтры
        if (!empty($filters['level']) && $filters['level'] !== 'all') {
            $logs = array_filter($logs, fn($log) => $log['level'] === $filters['level']);
        }

        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $logs = array_filter($logs, function ($log) use ($search) {
                return str_contains(strtolower($log['message']), $search) ||
                       str_contains(strtolower($log['full']), $search);
            });
        }

        if (!empty($filters['date'])) {
            $date = $filters['date'];
            $logs = array_filter($logs, fn($log) => str_contains($log['timestamp'], $date));
        }

        $total = count($logs);
        
        // Пагинация
        $offset = ($page - 1) * $perPage;
        $paginatedLogs = array_slice($logs, $offset, $perPage);

        $paginator = new LengthAwarePaginator(
            $paginatedLogs,
            $total,
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()]
        );

        return [
            'logs'  => $paginator,
            'total' => $total,
            'stats' => $this->getStats(),
        ];
    }

    /**
     * Получить статистику по логам
     *
     * @return array
     */
    public function getStats(): array
    {
        $stats = [
            'total'     => 0,
            'levels'    => [],
            'file_size' => 0,
        ];

        if (!File::exists($this->logPath)) {
            return $stats;
        }

        $content = File::get($this->logPath);
        $lines = explode("\n", $content);
        
        $stats['total'] = count($lines);
        $stats['file_size'] = File::size($this->logPath);

        foreach ($lines as $line) {
            if (preg_match('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]\s+(\w+)\.(\w+):/', $line, $matches)) {
                $level = $matches[2];
                if (!isset($stats['levels'][$level])) {
                    $stats['levels'][$level] = 0;
                }
                $stats['levels'][$level]++;
            }
        }

        return $stats;
    }

    /**
     * Очистить файл логов
     *
     * @return bool
     */
    public function clear(): bool
    {
        try {
            if (File::exists($this->logPath)) {
                File::put($this->logPath, '');
            }
            
            Log::info('Логи очищены администратором');
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Получить список доступных уровней логов
     *
     * @return array
     */
    public function getLogLevels(): array
    {
        return ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];
    }

    /**
     * Получить размер файла логов в удобочитаемом формате
     *
     * @return string
     */
    public function getLogSize(): string
    {
        if (!File::exists($this->logPath)) {
            return '0 B';
        }

        $size = File::size($this->logPath);
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 2) . ' ' . $units[$i];
    }
}