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
        // Ищем отклоненные фото, которые были удалены (soft deleted) более 30 дней назад
        $cutoffDate = now()->subDays(30);

        // cursor() читает из БД по одной записи, экономя память (не жрет ОЗУ при миллионах строк)
        $photos = Photo::onlyTrashed()
            ->where('status', 'rejected')
            ->where('deleted_at', '<', $cutoffDate)
            ->cursor();

        $deletedCount = 0;
        $failedCount = 0;

        foreach ($photos as $photo) {
            try {
                // forceDelete() сам вызовет событие forceDeleting в модели, которое удалит файлы с диска!
                // Нам не нужно вызывать deleteFiles() вручную.
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

        if ($deletedCount > 0 || $failedCount > 0) {
            Log::info("Крон очистки карантина завершен. Удалено: {$deletedCount}, Ошибок: {$failedCount}.");
        }

        $this->info("Очистка завершена. Удалено: {$deletedCount}, Ошибок: {$failedCount}.");

        return 0;
    }
}