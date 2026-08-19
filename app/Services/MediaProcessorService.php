<?php

namespace App\Services;

use App\Enums\MediaCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class MediaProcessorService
{
    public function process(string $relativeTempPath, MediaCollection $collection): array
    {
        $config = config("media.collections.{$collection->value}");
        
        if (!$config || !isset($config['variants'])) {
            throw new \Exception("Конфигурация для коллекции {$collection->value} не найдена.");
        }

        $disk = Storage::disk('public');
        
        if (!$disk->exists($relativeTempPath)) {
            throw new \Exception("Временный файл не найден: {$relativeTempPath}");
        }
        
        $absolutePath = $disk->path($relativeTempPath);
        $mimeType = mime_content_type($absolutePath);
        
        if ($mimeType === false) {
            $mimeType = 'application/octet-stream';
        }

        if (!str_starts_with($mimeType, 'image/')) {
            throw new \Exception("Неподдерживаемый тип файла: {$mimeType}. Разрешены только изображения.");
        }

        $manager = new ImageManager(new Driver());
        $baseName = Str::random(40);
        $dirPath = "media/{$collection->value}";
        $variants = [];

        foreach ($config['variants'] as $key => $variantConfig) {
            $image = $manager->read($absolutePath);
            
            [$width, $height] = $this->parseSize($variantConfig['size']);
            $fit = $variantConfig['fit'];
            $format = $variantConfig['format'];
            $quality = $variantConfig['quality'] ?? 80;

            if ($fit === 'cover' && $width && $height) {
                $image->cover($width, $height);
            } elseif (($fit === 'contain' || $fit === 'inside') && $width && $height) {
                // Добавили проверку на 'inside'
                $image->contain($width, $height);
            } elseif ($width && !$height) {
                // Если передали только ширину (например '800w')
                if ($image->width() > $width) {
                    $image->scale(width: $width);
                }
            }

            $encoded = match($format) {
                'webp' => (string) $image->toWebp($quality),
                'jpeg' => (string) $image->toJpeg($quality),
                'png'  => (string) $image->toPng(),
                default => (string) $image->toWebp($quality)
            };

            $ext = $format === 'jpeg' ? 'jpg' : $format;
            $fileName = "{$baseName}_{$key}.{$ext}";
            $path = "{$dirPath}/{$fileName}";
            
            $disk->put($path, $encoded);
            $variants[$key] = $path;
            
            unset($image, $encoded);
            gc_collect_cycles(); 
        }

        // Берем ПОСЛЕДНИЙ сгенерированный вариант (например, 'lg') как главный путь
        $mainPath = end($variants);
        
        if ($config['keep_original'] ?? false) {
            $ext = pathinfo($relativeTempPath, PATHINFO_EXTENSION);
            $fileName = "{$baseName}_orig.{$ext}";
            $origPath = "{$dirPath}/{$fileName}";
            $disk->copy($relativeTempPath, $origPath);
            
            // Добавляем оригинал в массив вариантов
            $variants['orig'] = $origPath;
        }

        if (empty($mainPath)) {
            $mainPath = reset($variants);
        }

        return [
            'main_path' => $mainPath, 
            'variants' => $variants, 
            'mime_type' => $mimeType
        ];
    }

    private function parseSize(string $size): array
    {
        $size = strtolower(trim($size));
        
        if (str_ends_with($size, 'w')) {
            return [(int) rtrim($size, 'w'), null];
        }
        
        $parts = explode('x', $size);
        $width = (int) ($parts[0] ?? 0);
        $height = (int) ($parts[1] ?? 0);
        
        return [$width, $height];
    }
}