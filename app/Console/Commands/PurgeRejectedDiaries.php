<?php

namespace App\Console\Commands;

use App\Models\AdminLog;
use App\Models\Diary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurgeRejectedDiaries extends Command
{
    protected $signature = 'diaries:purge-rejected {--days=30 : Количество дней для хранения}';
    protected $description = 'Физически удаляет отклоненные записи дневников старше X дней';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $date = now()->subDays($days);

        // ОПТИМИЗИРОВАНО: select('id') - тянем только ID. 
        // longText поля 'body' остались бы в памяти и убили бы сервер при ->get()
        $query = Diary::select('id')
            ->where('status', 'rejected')
            ->where('updated_at', '<', $date);

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('Нет отклоненных записей для удаления.');
            return Command::SUCCESS;
        }

        $this->info("Найдено {$count} отклоненных записей. Начинаю физическую очистку...");

        $totalDeleted = 0;
        $firstDiaryId = null;

        // Удаляем чанками по 1000
        $query->chunkById(1000, function ($diaries) use (&$totalDeleted, $count, &$firstDiaryId) { 
            $ids = $diaries->pluck('id');
            
            // Запоминаем первый ID для красивого лога
            if (!$firstDiaryId) $firstDiaryId = $ids->first();
            
            // ЖЕСТКОЕ УДАЛЕНИЕ: Bulk Delete. База сама каскадно удалит все комменты/лайки к этим постам!
            Diary::whereIn('id', $ids)->forceDelete();
            
            $totalDeleted += $ids->count();
            $this->info("Удалено {$totalDeleted} из {$count}...");
            
            // Микро-задержка для I/O базы
            usleep(100000);
        });

        // Записываем ОДИН общий лог, чтобы не рвать таблицу AdminLog на тысячи строк
        if ($totalDeleted > 0) {
            $dummyDiary = new Diary(['id' => $firstDiaryId]); // Создаем пустышку для лога
            $dummyDiary->exists = true;
            
            AdminLog::record('diary.auto_purge', $dummyDiary, null, ['status' => 'rejected', 'count' => $count], null);
        }

        $this->info("Готово! Физически удалено {$totalDeleted} записей старше {$days} дней.");
        Log::info("Очистка дневников: физически удалено {$totalDeleted} записей");

        return Command::SUCCESS;
    }
}