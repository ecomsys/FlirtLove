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
     * Мы будем вызывать его из компонентов Livewire.
     */
    public static function createFromFile(\Illuminate\Http\UploadedFile $file, string $collection = 'default', ?int $userId = null): self
    {
        $fileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $cleanName = \Illuminate\Support\Str::slug($fileName) . '-' . uniqid();
        
        // Определяем тип
        $type = str_starts_with($file->getMimeType(), 'video/') ? 'video' : 'image';

        // Если это картинка - конвертируем в WebP
        if ($type === 'image') {
            $cleanName .= '.webp';
            $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
            $image = $manager->read($file->getRealPath());
            if ($image->width() > 1200) {
                $image->scale(width: 1200); // Макс ширина для склада 1200px
            }
            $encoded = (string) $image->toWebp(80);
            $diskPath = "media/{$collection}/" . $cleanName;
            Storage::disk('public')->put($diskPath, $encoded);
            unset($image);
        } else {
            // Видео просто сохраняем (кодирование видео тут требует ffmpeg, оставляем на потом)
            $cleanName .= '.' . $file->getClientOriginalExtension();
            $diskPath = $file->store("media/{$collection}", 'public');
        }

        return self::create([
            'collection' => $collection,
            'file_name' => $file->getClientOriginalName(),
            'disk_path' => $diskPath,
            'url' => Storage::url($diskPath),
            'type' => $type,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
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