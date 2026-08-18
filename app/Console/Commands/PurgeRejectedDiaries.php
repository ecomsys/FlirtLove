<?php

namespace App\Console\Commands;

use App\Models\AdminLog;
use App\Models\Diary;
use Illuminate\Console\Command;

class PurgeRejectedDiaries extends Command
{
    protected $signature = 'diaries:purge-rejected';
    protected $description = 'Удаляет навсегда отклоненные записи дневников старше 30 дней';

    public function handle(): int
    {
        $cutoffDate = now()->subDays(30);

        // Ищем отклоненные посты, обновленные более 30 дней назад
        $diaries = Diary::where('status', 'rejected')
            ->where('updated_at', '<', $cutoffDate)
            ->get();

        if ($diaries->isEmpty()) {
            $this->info('Нет отклоненных записей для удаления.');
            return 0;
        }

        $count = 0;
        foreach ($diaries as $diary) {
            AdminLog::record('diary.auto_purge', $diary, null, ['status' => 'rejected'], null);
            $diary->forceDelete();
            $count++;
        }

        $this->info("Удалено {$count} отклоненных записей старше 30 дней.");
        return 0;
    }
}