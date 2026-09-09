<?php

namespace App\Console\Commands;

use App\Models\Report;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurgeOldReports extends Command
{
    protected $signature = 'reports:purge-quarantine {--days=30 : Количество дней для хранения}';
    protected $description = 'Физически удаляет закрытые (resolved/rejected) жалобы старше 30 дней';

    public function handle(): void
    {
        $days = (int) $this->option('days');
        $date = now()->subDays($days);

        // ОПТИМИЗИРОВАНО: select('id') - тянем только ID, чтобы не жрать память (не грузим тексты жалоб)
        $query = Report::withTrashed()
            ->select('id')
            ->whereIn('status', ['resolved', 'rejected'])
            ->where('created_at', '<', $date);

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('Нет старых жалоб для удаления.');
            return;
        }

        $this->info("Найдено {$count} закрытых жалоб. Начинаю очистку...");

        $totalDeleted = 0;

        // Удаляем чанками по 1000
        $query->chunkById(1000, function ($reports) use (&$totalDeleted, $count) { 
            // Достаем только ID из коллекции легких моделей
            $ids = $reports->pluck('id');
            
            // Жесткое удаление из БД.
            Report::withTrashed()->whereIn('id', $ids)->forceDelete();
            
            $totalDeleted += $ids->count();
            $this->info("Удалено {$totalDeleted} из {$count}...");
        });

        $this->info("Готово! Физически удалено {$totalDeleted} жалоб, старше {$days} дней.");
        Log::info("Очистка жалоб: физически удалено {$totalDeleted} записей");
    }
}