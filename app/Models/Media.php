<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $fillable = [
        'collection',
        'file_name',
        'disk_path',
        'url',
        'type',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ============================================
    // СКОПЫ
    // ============================================

    public function scopeOfCollection($query, string $collection)
    {
        return $query->where('collection', $collection);
    }

    public function scopeImages($query)
    {
        return $query->where('type', 'image');
    }

    public function scopeVideos($query)
    {
        return $query->where('type', 'video');
    }

    // ============================================
    // ХЕЛПЕРЫ
    // ============================================

        /**
     * Удобный метод для загрузки и создания записи в БД.
     * Теперь он принимает Enum и сам применяет правила сжатия и кропа!
     */
    public static function createFromFile(\Illuminate\Http\UploadedFile $file, \App\Enums\MediaCollection $collection, ?int $userId = null): self
    {
        $fileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $cleanName = \Illuminate\Support\Str::slug($fileName) . '-' . uniqid();
        
        // Определяем тип
        $type = str_starts_with($file->getMimeType(), 'video/') ? 'video' : 'image';

        // Если это картинка - конвертируем в WebP по правилам коллекции
        if ($type === 'image') {
            $cleanName .= '.webp';
            $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
            $image = $manager->read($file->getRealPath());

            // 1. Если нужно квадрат — делаем кроп по центру
            if ($collection->shouldBeSquare()) {
                // cover обрезает картинку так, чтобы она стала строго заданного размера
                $size = min($image->width(), $image->height());
                $image->cover($size, $size);
            }

            // 2. Масштабируем по ширине, не превышая maxWidth
            if ($image->width() > $collection->maxWidth()) {
                $image->scale(width: $collection->maxWidth());
            }

            // 3. Кодируем в WebP с нужным качеством
            $encoded = (string) $image->toWebp($collection->quality());
            
            $diskPath = "media/{$collection->value}/" . $cleanName;
            Storage::disk('public')->put($diskPath, $encoded);
            unset($image); // Освобождаем память
            
            $size = strlen($encoded); // Записываем новый размер файла
            $mimeType = 'image/webp';
        } else {
            // Видео просто сохраняем
            $cleanName .= '.' . $file->getClientOriginalExtension();
            $diskPath = $file->store("media/{$collection->value}", 'public');
            $size = $file->getSize();
            $mimeType = $file->getMimeType();
        }

        return self::create([
            'collection' => $collection->value, // Сохраняем значение Enum (строку 'gifts')
            'file_name' => $file->getClientOriginalName(),
            'disk_path' => $diskPath,
            'url' => Storage::url($diskPath),
            'type' => $type,
            'mime_type' => $mimeType,
            'size' => $size,
            'uploaded_by' => $userId,
        ]);
    }
    /**
     * Безопасное удаление файла с диска и из БД.
     */
    public function safeDelete(): bool
    {
        if (Storage::disk('public')->exists($this->disk_path)) {
            Storage::disk('public')->delete($this->disk_path);
        }
        return $this->delete();
    }
}