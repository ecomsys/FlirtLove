<?php

namespace App\Console\Commands;

use App\Models\PhotoComment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurgeRejectedComments extends Command
{
    protected $signature = 'comments:purge-quarantine {--days=30 : Количество дней для хранения}';
    protected $description = 'Мягко удаляет (переносит в карантин) старые отклоненные и спам-комментарии';

    public function handle(): void
    {
        $days = (int) $this->option('days');
        $date = now()->subDays($days);

        // Ищем только ЖИВЫЕ комменты (deleted_at IS NULL), которые старше X дней
        // ВАЖНО: select('id') чтобы не жрать память, вытаскивая тексты комментов
        $query = PhotoComment::select('id')
            ->whereNull('deleted_at')
            ->whereIn('status', ['rejected', 'spam'])
            ->where('created_at', '<', $date);

        // Клонируем запрос для подсчета
        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('Нет отклоненных/спам комментариев для переноса в карантин.');
            return;
        }

        $this->info("Найдено {$count} комментариев. Начинаю перенос в карантин...");

        $totalDeleted = 0;

        // Удаляем чанками по 1000
        $query->chunkById(1000, function ($comments) use (&$totalDeleted, $count) { 
            // Достаем только ID из коллекции легких моделей
            $ids = $comments->pluck('id');
            
            // МЯГКОЕ УДАЛЕНИЕ: один UPDATE-запрос на весь чанк
            // Eloquent сам подставит deleted_at = now() для всех этих ID
            PhotoComment::whereIn('id', $ids)->delete();
            
            $totalDeleted += $ids->count();
            $this->info("Перенесено в карантин {$totalDeleted} из {$count}...");
        });

        $this->info("Готово! Перенесено в карантин {$totalDeleted} комментариев (rejected/spam), старше {$days} дней.");
        Log::info("Очистка комментариев: перенесено в карантин (soft delete) {$totalDeleted} записей");
    }
}