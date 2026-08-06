<?php 

namespace App\Jobs;

use App\Models\Photo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\DB;

// php artisan queue:work --queue=heav

class ProcessApprovedPhoto implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public int $photoId)
    {
        // Тяжелые джобы ресайза лучше гнать в отдельную очередь, чтобы не блокировать почту/пуши
        $this->onQueue('heavy');
    }

    /**
     * Генерация пути к файлу на основе хэша ID пользователя.
     */
    private function getStoragePath(int $userId, string $photoType, string $size, string $fileId): string
    {
        $hash = substr(md5((string) $userId), 0, 3);
        return "photos/{$photoType}/{$hash}/{$userId}/{$size}_{$fileId}.webp";
    }

    /**
     * Выполнение Job.
     */
    public function handle(): void
    {
        // Увеличиваем лимит памяти для Intervention Image (GD драйвер прожорлив)
        ini_set('memory_limit', '256M');

        $paths = [];
        $originalDbPath = null;

        try {
            $photo = Photo::find($this->photoId);
            if (!$photo) {
                Log::warning('Фото не найдено', ['photo_id' => $this->photoId]);
                return;
            }

            // Защита от двойной обработки
            if ($photo->path_large) {
                Log::info('Фото уже обработано', ['photo_id' => $this->photoId, 'status' => $photo->status]);
                return;
            }

            $userId = $photo->user_id;
            $photoType = $photo->type; // 'profile' или 'verification'
            
            $originalDbPath = $photo->path_original;
            $originalPath = storage_path('app/public/' . $originalDbPath);
            
            if (!file_exists($originalPath)) {
                Log::warning('Оригинальный файл не найден', ['path' => $originalPath]);
                // ИСПРАВЛЕНО: null вместо 0, чтобы не нарушить Foreign Key
                $photo->markAsRejected(null, 'file_missing'); 
                return;
            }

            $fileId = uniqid();
            $manager = new ImageManager(new Driver());
            $image = $manager->read($originalPath);

            $sizes = [
                'original' => ['width' => null, 'quality' => 90],
                'large'    => ['width' => 1600, 'quality' => 85],
                'medium'   => ['width' => 820, 'quality' => 80],
                'thumb'    => ['width' => 200, 'quality' => 70, 'cover' => true],
            ];
            
            foreach ($sizes as $sizeName => $config) {
                $fullPath = $this->getStoragePath($userId, $photoType, $sizeName, $fileId);
                
                if (isset($config['cover']) && $config['cover']) {
                    $resized = $image->cover(200, 200);
                } else {
                    $resized = $config['width'] 
                        ? $image->scale(width: $config['width']) 
                        : $image;
                }
                
                Storage::disk('public')->put(
                    $fullPath,
                    (string) $resized->toWebp($config['quality'])
                );
                
                $paths[$sizeName] = $fullPath;
                
                if ($sizeName !== 'original') {
                    unset($resized);
                }
            }

            unset($image);
            gc_collect_cycles();

            // Обновляем БД в транзакции (файловые операции не внутри!)
            DB::transaction(function () use ($photo, $paths) {
                $photo->update([
                    'path_original' => $paths['original'],
                    'path_large'    => $paths['large'],
                    'path_medium'   => $paths['medium'],
                    'path_thumb'    => $paths['thumb'],
                    // Статус уже 'approved', но мы обновляем moderated_at для фиксации времени готовности
                    'moderated_at'  => now(),
                ]);
            });

            // Удаляем исходный загруженный файл ТОЛЬКО после успешного коммита в БД
            if ($originalDbPath) {
                Storage::disk('public')->delete($originalDbPath);
            }

            Log::info('Фото успешно обработано', [
                'photo_id' => $this->photoId,
                'user_id'  => $userId,
            ]);

        } catch (\Exception $e) {
            Log::error('Ошибка обработки фото', [
                'photo_id' => $this->photoId,
                'error'    => $e->getMessage(),
            ]);

            // Чистим недособранные webp-файлы
            foreach ($paths as $failedPath) {
                Storage::disk('public')->delete($failedPath);
            }

            // ИСПРАВЛЕНО: Откатываем статус фото. 
            // Проверяем на 'approved', так как экшен уже поменял статус до диспатча.
            $photo = Photo::find($this->photoId);
            if ($photo && $photo->status === 'approved') {
                $photo->markAsRejected(null, 'processing_error');
            }

            if ($this->attempts() < $this->tries) {
                // Повторяем через 5 минут
                $this->release(60 * 5);
            } else {
                $this->fail($e);
            }
        }
    }
}