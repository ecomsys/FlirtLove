<?php

namespace App\Console\Commands;

use App\Models\PhotoComment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanOldPhotoComments extends Command
{
    protected $signature = 'comments:clean {--days=30 : Количество дней для хранения}';
    protected $description = 'Удаляет старые отклоненные и спам-комментарии';

    public function handle(): void
    {
        $days = (int) $this->option('days');
        $date = now()->subDays($days);

        $count = PhotoComment::whereIn('status', ['rejected', 'spam'])
            ->where('created_at', '<', $date)
            ->count();

        if ($count === 0) {
            $this->info('Нет комментариев для удаления.');
            return;
        }

        $deleted = PhotoComment::whereIn('status', ['rejected', 'spam'])
            ->where('created_at', '<', $date)
            ->delete();

        $this->info("Удалено {$deleted} комментариев (rejected/spam), старше {$days} дней.");
        Log::info("Очистка комментариев: удалено {$deleted} записей");
    }
}