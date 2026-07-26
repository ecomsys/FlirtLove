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

    /** @var int Максимальное количество попыток выполнения */
    public int $tries = 3;
    
    /** @var int Таймаут выполнения в секундах */
    public int $timeout = 120;

    public function __construct(public int $photoId) {}

    /**
     * Генерация пути к файлу на основе хэша ID пользователя.
     * Позволяет распределить файлы по папкам для оптимизации ФС.
     */
    private function getStoragePath(int $userId, string $fileId, string $type): string
    {
        $hash = substr(md5((string) $userId), 0, 3);
        return "photos/approved/{$hash}/{$userId}/{$type}_{$fileId}.webp";
    }

    /**
     * Выполнение Job.
     */
    public function handle(): void
    {
        // Увеличиваем лимит памяти для Intervention Image
        ini_set('memory_limit', '256M');

        // Выносим массив путей наружу, чтобы иметь доступ к нему в catch
        // для очистки недособранных файлов в случае ошибки.
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
            $originalDbPath = $photo->path;
            $originalPath = storage_path('app/public/' . $originalDbPath);
            
            if (!file_exists($originalPath)) {
                Log::warning('Оригинальный файл не найден', ['path' => $originalPath]);
                // Если файла нет, просто меняем статус, чтобы не висел в pending
                $photo->update(['status' => 'approved']);
                return;
            }

            $fileId = uniqid();
            $manager = new ImageManager(new Driver());
            $image = $manager->read($originalPath);

            // Конфигурация размеров и качества сжатия
            $sizes = [
                'original' => ['width' => null, 'quality' => 90],
                'large' => ['width' => 1600, 'quality' => 85],
                'medium' => ['width' => 820, 'quality' => 80],
                'thumb' => ['width' => 200, 'quality' => 70, 'cover' => true], // cover - обрезка в квадрат
            ];
            
            foreach ($sizes as $type => $config) {
                $fullPath = $this->getStoragePath($userId, $fileId, $type);
                
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
                
                $paths[$type] = $fullPath;
                
                // Очищаем память от ресурса Intervention Image
                if ($type !== 'original') {
                    unset($resized);
                }
            }

            unset($image);
            gc_collect_cycles();

            // Обновляем БД и удаляем оригинал в транзакции
            DB::transaction(function () use ($photo, $paths, $originalDbPath) {
                $photo->update([
                    'path' => $paths['medium'],
                    'path_original' => $paths['original'],
                    'path_large' => $paths['large'],
                    'path_medium' => $paths['medium'],
                    'path_thumb' => $paths['thumb'],
                    'status' => 'approved',
                ]);

                Storage::disk('public')->delete($originalDbPath);
            });

            Log::info('Фото успешно обработано', [
                'photo_id' => $this->photoId,
                'user_id' => $userId,
            ]);

        } catch (\Exception $e) {
            Log::error('Ошибка обработки фото', [
                'photo_id' => $this->photoId,
                'error' => $e->getMessage(),
            ]);

            // Чистим недособранные webp-файлы, если произошла ошибка на середине цикла
            foreach ($paths as $failedPath) {
                Storage::disk('public')->delete($failedPath);
            }

            // Откатываем статус фото, чтобы его можно было попробовать снова
            DB::transaction(function () {
                $photo = Photo::find($this->photoId);
                if ($photo && $photo->status === 'pending') {
                    $photo->update(['status' => 'rejected']);
                }
            });

            // Логика ретраев: если попытки не исчерпаны, откладываем на 5 минут
            if ($this->attempts() < $this->tries) {
                $this->release(60 * 5);
            } else {
                // Если попытки кончились, полностью фейлим Job
                $this->fail($e);
            }
        }
    }
}