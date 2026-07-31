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

/**
 * Job для обработки одобренных фотографий.
 * Создает 4 версии (original, large, medium, thumb) в формате WebP,
 * сохраняет пути в БД и удаляет исходный загруженный файл.
 */
class ProcessApprovedPhoto implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public int $photoId) {}

    /**
     * Генерация пути к файлу на основе хэша ID пользователя.
     * Используем 3 символа хэша (4096 папок) и папку типа фото.
     */
    private function getStoragePath(int $userId, string $photoType, string $size, string $fileId): string
    {
        $hash = substr(md5((string) $userId), 0, 3);
        // Пример: photos/profile/a3f/105/large_64a9f.webp
        return "photos/{$photoType}/{$hash}/{$userId}/{$size}_{$fileId}.webp";
    }

    /**
     * Выполнение Job.
     */
    public function handle(): void
    {
        // Увеличиваем лимит памяти для Intervention Image
        ini_set('memory_limit', '256M');

        $paths = [];

        try {
            $photo = Photo::find($this->photoId);
            if (!$photo) {
                Log::warning('Фото не найдено', ['photo_id' => $this->photoId]);
                return;
            }

            // Защита от двойной обработки
            if ($photo->status !== 'pending') {
                Log::info('Фото уже обработано', ['photo_id' => $this->photoId, 'status' => $photo->status]);
                return;
            }

            $userId = $photo->user_id;
            $photoType = $photo->type; // 'profile' или 'verification'
            
            // Читаем оригинальный путь (теперь он хранится в path_original)
            $originalDbPath = $photo->path_original;
            $originalPath = storage_path('app/public/' . $originalDbPath);
            
            if (!file_exists($originalPath)) {
                Log::warning('Оригинальный файл не найден', ['path' => $originalPath]);
                // Если файла нет, отклоняем фото, чтобы не висело в pending
                $photo->markAsRejected(0, 'file_missing'); // 0 = Система
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
                // Передаем $photoType в генератор пути
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

            // Обновляем БД и удаляем оригинал в транзакции
            DB::transaction(function () use ($photo, $paths, $originalDbPath) {
                // ВАЖНО: Обновляем 4 поля путей. Поля 'path' больше не существует!
                $photo->update([
                    'path_original' => $paths['original'],
                    'path_large'    => $paths['large'],
                    'path_medium'   => $paths['medium'],
                    'path_thumb'    => $paths['thumb'],
                    'status'        => 'approved',
                    'moderated_at'  => now(),
                ]);

                // Удаляем исходный загруженный файл (временный)
                Storage::disk('public')->delete($originalDbPath);
            });

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

            // Откатываем статус фото
            DB::transaction(function () {
                $photo = Photo::find($this->photoId);
                if ($photo && $photo->status === 'pending') {
                    // Используем наш хелпер из модели! 0 = Система
                    $photo->markAsRejected(0, 'processing_error');
                }
            });

            if ($this->attempts() < $this->tries) {
                $this->release(60 * 5);
            } else {
                $this->fail($e);
            }
        }
    }
}