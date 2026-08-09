<?php

namespace App\Console\Commands;

use App\Models\PhotoComment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurgeRejectedComments extends Command
{
    protected $signature = 'comments:purge-quarantine {--days=30 : Количество дней для хранения}';
    protected $description = 'Физически удаляет старые отклоненные и спам-комментарии из БД';

    public function handle(): void
    {
        $days = (int) $this->option('days');
        $date = now()->subDays($days);

        // Создаем базовый запрос: ищем отклоненные/спам комменты старше X дней.
        $query = PhotoComment::withTrashed()
            ->whereIn('status', ['rejected', 'spam'])
            ->where('created_at', '<', $date);

        // Клонируем запрос для подсчета
        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('Нет отклоненных/спам комментариев для удаления.');
            return;
        }

        $this->info("Найдено {$count} комментариев. Начинаю очистку...");

        $totalDeleted = 0;

        // Удаляем чанками по 1000
        $query->chunkById(1000, function ($comments) use (&$totalDeleted, $count) { 
            // ИСПРАВЛЕНО: Пакетное удаление. 1 SQL-запрос DELETE на чанк (вместо 1000 запросов в цикле)
            $ids = $comments->pluck('id');
            PhotoComment::withTrashed()->whereIn('id', $ids)->forceDelete();
            
            $totalDeleted += $ids->count();
            $this->info("Удалено {$totalDeleted} из {$count}...");
        });

        $this->info("Готово! Физически удалено {$totalDeleted} комментариев (rejected/spam), старше {$days} дней.");
        Log::info("Очистка комментариев: физически удалено {$totalDeleted} записей");
    }
}