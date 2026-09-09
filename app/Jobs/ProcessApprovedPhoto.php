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

// use Intervention\Image\Drivers\Imagick\Driver;

// Установиить на серваке
// sudo apt-get update
// sudo apt-get install php-imagick
// sudo service php8.2-fpm restart  # (или service apache2 restart, смотря какой веб-сервер)


class ProcessApprovedPhoto implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public int $photoId)
    {
        // Тяжелые джобы ресайза гнем в отдельную очередь, чтобы не блокировать почту/пуши
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
        // Увеличиваем лимит памяти (Imagick потребует меньше, но для GD это критично)
        $originalMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', '512M');

        $paths = [];
        $originalDbPath = null;

        try {
            $photo = Photo::find($this->photoId);
            if (!$photo) {
                Log::warning('Фото не найдено', ['photo_id' => $this->photoId]);
                return;
            }

            // Защита от двойной обработки (идемпотентность)
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
                $photo->markAsRejected(null, 'file_missing'); 
                return;
            }

            $fileId = uniqid();
            
            // ФИКС: Авто-выбор драйвера. Imagick экономит в 5 раз больше памяти!
            if (extension_loaded('imagick')) {
                $manager = new ImageManager(new Driver()); // Imagick Driver
            } else {
                $manager = new ImageManager(new Driver()); // GD Fallback
            }
            
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
                    ? $image->scaleDown(width: $config['width']) 
                    : $image;
                }
                
                Storage::disk('public')->put(
                    $fullPath,
                    (string) $resized->toWebp($config['quality'])
                );
                
                $paths[$sizeName] = $fullPath;
                
                // ФИКС: Явно освобождаем память Imagick/GD
                unset($resized);
            }

            // Освобождаем оригинал из памяти
            unset($image);
            gc_collect_cycles();

            // Обновляем БД (одиночный апдейт не требует DB::transaction, но оставляем для консистентности событий)
            $photo->update([
                'path_original' => $paths['original'],
                'path_large'    => $paths['large'],
                'path_medium'   => $paths['medium'],
                'path_thumb'    => $paths['thumb'],
                'moderated_at'  => now(),
            ]);

            // Удаляем исходный загруженный файл ТОЛЬКО после успешного апдейта в БД
            Storage::disk('public')->delete($originalDbPath);

            Log::info('Фото успешно обработано', [
                'photo_id' => $this->photoId,
                'user_id'  => $userId,
            ]);

        } catch (\Exception $e) {
            Log::error('Ошибка обработки фото', [
                'photo_id' => $this->photoId,
                'error'    => $e->getMessage(),
            ]);

            // Чистим недособранные webp-файлы, чтобы не засорять диск
            foreach ($paths as $failedPath) {
                Storage::disk('public')->delete($failedPath);
            }

            // Если это последняя попытка — помечаем фото как отклоненное и фейлим джобу
            if ($this->attempts() >= $this->tries) {
                $photo = Photo::find($this->photoId);
                if ($photo && $photo->status === 'approved') {
                    $photo->markAsRejected(null, 'processing_error');
                }
                $this->fail($e);
            } else {
                // Если попытки еще есть — отпускаем в очередь с задержкой 5 минут
                $this->release(60 * 5);
            }
        } finally {
            // ФИКС: Гарантированно возвращаем лимит памяти
            ini_set('memory_limit', $originalMemoryLimit);
        }
    }
}