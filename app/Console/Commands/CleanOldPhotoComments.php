<?php

namespace App\Console\Commands;

use App\Models\PhotoComment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanOldPhotoComments extends Command
{
    protected $signature = 'comments:clean {--days=30 : Количество дней для хранения}';
    protected $description = 'Физически удаляет старые отклоненные и спам-комментарии из БД';

    public function handle(): void
    {
        $days = (int) $this->option('days');
        $date = now()->subDays($days);

        // Удаляем чанками по 1000 записей, чтобы не блокировать таблицу надолго
        // Используем forceDelete(), так как SoftDeletes не удалит строки физически
        $totalDeleted = 0;
        
        // Создаем базовый запрос
        $query = PhotoComment::query()
            ->onlyTrashed() // Берем только те, что уже в корзине (опционально, но логично)
            ->whereIn('status', ['rejected', 'spam'])
            ->where('created_at', '<', $date);

        // Клонируем запрос для подсчета (чтобы не сбить курсор)
        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('Нет комментариев для удаления.');
            return;
        }

        $this->info("Найдено {$count} комментариев. Начинаю очистку...");

        // Удаляем порциями по 1000
        $query->chunkById(1000, function ($comments) use (&$totalDeleted, $count) { 
             foreach ($comments as $comment) {
                $comment->forceDelete(); // Жесткое удаление!
            }
            $totalDeleted += $comments->count();
            $this->info("Удалено {$totalDeleted} из {$count}...");
        });

        $this->info("Готово! Физически удалено {$totalDeleted} комментариев (rejected/spam), старше {$days} дней.");
        Log::info("Очистка комментариев: физически удалено {$totalDeleted} записей");
    }
}