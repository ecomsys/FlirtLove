<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    // КОНСТАНТЫ ТИПОВ
    public const TYPE_IMAGE = 'image';
    public const TYPE_VIDEO = 'video';
    public const TYPE_DOCUMENT = 'document';

    protected $fillable = [
        'collection',
        'file_name',
        'disk_path',
        'variants',
        'url',
        'type',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
        'variants' => 'array',
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

    public function scopeOfCollection(Builder $query, string $collection): Builder
    {
        return $query->where('collection', $collection);
    }

    public function scopeImages(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_IMAGE);
    }

    public function scopeVideos(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_VIDEO);
    }

    // ============================================
    // ХЕЛПЕРЫ
    // ============================================

    public function safeDelete(): bool
    {
        $disk = Storage::disk('public');

        // Защита: не удаляем файлы из темп-папок (ими управляет другая логика)
        if ($this->disk_path && !str_starts_with($this->disk_path, 'media/temp/') && $disk->exists($this->disk_path)) {
            $disk->delete($this->disk_path);
        }

        // Удаляем все нарезанные варианты (sm, lg, и т.д.)
        if (!empty($this->variants)) {
            foreach ($this->variants as $variantPath) {
                if ($disk->exists($variantPath)) {
                    $disk->delete($variantPath);
                }
            }
        }

        return $this->delete();
    }  

    /**
     * Получить URL конкретного варианта (thumb, sm, md, lg, orig).
     * ИСПРАВЛЕНО: Убрали asset(), оставили только Storage::url() 
     * Это спасет от двойной обертки URL при переезде на S3/CDN.
     */
    public function getVariantUrl(?string $key = null): string
    {
        $variants = $this->variants ?? [];
        
        // 1. Если просим конкретный ключ (например 'sm') и он есть — отдаем его
        if ($key && isset($variants[$key])) {
            return Storage::url($variants[$key]);
        }
        
        // 2. Если просим 'orig' или 'lg', а их нет — отдаем самый большой сгенерированный (последний в массиве)
        if (in_array($key, ['orig', 'lg']) && !empty($variants)) {
            return Storage::url(end($variants));
        }
        
        // 3. Если просим 'thumb' или 'sm', а их нет — отдаем ПЕРВЫЙ доступный (самый маленький)
        if (in_array($key, ['thumb', 'sm']) && !empty($variants)) {
            return Storage::url(reset($variants));
        }
        
        // 4. Фоллбэк: отдаем то, что лежит в базе (для обрабатываемых файлов)
        return $this->url;
    }
}