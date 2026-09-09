<?php

namespace App\Console\Commands;

use App\Models\Photo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurgeRejectedPhotos extends Command
{
    protected $signature = 'photos:purge-quarantine';
    protected $description = 'Физически удаляет файлы отклоненных/спам фото старше 30 дней (Очистка карантина)';

    public function handle(): int
    {
        $cutoffDate = now()->subDays(30);

        // Ищем только те фото, что УЖЕ лежат в карантине (soft deleted) 
        // и имеют статус rejected или spam.
        $query = Photo::onlyTrashed()
            ->whereIn('status', ['rejected', 'spam'])
            ->where('deleted_at', '<', $cutoffDate);

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('Нет фото в карантине для удаления.');
            return Command::SUCCESS;
        }

        $this->info("Найдено {$count} фото в карантине. Начинаю физическую очистку...");

        $deletedCount = 0;
        $failedCount = 0;

        // Удаляем чанками по 500
        $query->chunkById(500, function ($photos) use (&$deletedCount, &$failedCount, $count) {
            foreach ($photos as $photo) {
                try {
                    // forceDelete() на экземпляре модели вызовет booted() -> forceDeleting -> deleteFiles()
                    // Это гарантированно удалит файлы (original, large, medium, thumb) с диска!
                    $photo->forceDelete(); 
                    $deletedCount++;
                } catch (\Exception $e) {
                    $failedCount++;
                    Log::error('Крон: Ошибка физ. удаления фото', [
                        'photo_id' => $photo->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            $this->info("Обработано {$deletedCount} из {$count}... (Ошибок: {$failedCount})");
            
            // Микро-задержка 0.1 сек, чтобы не перегружать I/O диска при удалении тысяч файлов
            usleep(100000); 
            
        });

        if ($deletedCount > 0 || $failedCount > 0) {
            Log::info("Крон очистки карантина фото завершен. Удалено: {$deletedCount}, Ошибок: {$failedCount}.");
        }

        $this->info("Очистка завершена. Удалено: {$deletedCount}, Ошибок: {$failedCount}.");

        return Command::SUCCESS;
    }
}