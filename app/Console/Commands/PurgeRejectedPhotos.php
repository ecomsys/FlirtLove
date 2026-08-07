<?php

namespace App\Console\Commands;

use App\Models\Photo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurgeRejectedPhotos extends Command
{
    protected $signature = 'photos:purge-quarantine';
    protected $description = 'Физически удаляет файлы отклоненных фото старше 30 дней (Очистка карантина)';

    public function handle(): int
    {
        $cutoffDate = now()->subDays(30);

        // Создаем базовый запрос
        $query = Photo::onlyTrashed()
            ->where('status', 'rejected')
            ->where('deleted_at', '<', $cutoffDate);

        // Клонируем запрос для подсчета (чтобы не сбить курсор чанков)
        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('Нет фото в карантине для удаления.');
            return 0;
        }

        $this->info("Найдено {$count} фото в карантине. Начинаю очистку...");

        $deletedCount = 0;
        $failedCount = 0;

        // Удаляем чанками по 500 (фото удаляются дольше из-за файлов, 500 — оптимальный размер)
        $query->chunkById(500, function ($photos) use (&$deletedCount, &$failedCount, $count) {
            foreach ($photos as $photo) {
                try {
                    // forceDelete() сам вызовет событие forceDeleting в модели, которое удалит файлы с диска!
                    $photo->forceDelete(); 
                    $deletedCount++;
                } catch (\Exception $e) {
                    $failedCount++;
                    Log::error('Ошибка при очистке карантина фото', [
                        'photo_id' => $photo->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            $this->info("Обработано {$deletedCount} из {$count}...");
        });

        if ($deletedCount > 0 || $failedCount > 0) {
            Log::info("Крон очистки карантина фото завершен. Удалено: {$deletedCount}, Ошибок: {$failedCount}.");
        }

        $this->info("Очистка завершена. Удалено: {$deletedCount}, Ошибок: {$failedCount}.");

        return 0;
    }
}