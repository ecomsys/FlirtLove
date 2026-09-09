<?php

namespace App\Console\Commands;

use App\Models\DiaryComment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurgeRejectedDiaryComments extends Command
{
    protected $signature = 'diary-comments:purge-quarantine {--days=30 : Количество дней для хранения}';
    protected $description = 'Мягко удаляет (переносит в карантин) старые отклоненные и спам-комментарии дневников';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $date = now()->subDays($days);

        // ОПТИМИЗИРОВАНО: select('id') тянем только ID, чтобы не жрать память (не грузим тексты комментов)
        $query = DiaryComment::select('id')
            ->whereNull('deleted_at')
            ->whereIn('status', ['rejected', 'spam'])
            ->where('created_at', '<', $date);

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('Нет отклоненных/спам комментариев для переноса в карантин.');
            return Command::SUCCESS;
        }

        $this->info("Найдено {$count} комментариев. Начинаю перенос в карантин...");

        $totalDeleted = 0;

        $query->chunkById(1000, function ($comments) use (&$totalDeleted, $count) { 
            $ids = $comments->pluck('id');
            
            // МЯГКОЕ УДАЛЕНИЕ: один UPDATE-запрос на весь чанк
            DiaryComment::whereIn('id', $ids)->delete();
            
            $totalDeleted += $ids->count();
            $this->info("Перенесено в карантин {$totalDeleted} из {$count}...");
        });

        $this->info("Готово! Перенесено в карантин {$totalDeleted} комментариев, старше {$days} дней.");
        Log::info("Очистка комментов дневников: перенесено в карантин {$totalDeleted} записей");

        return Command::SUCCESS;
    }
}