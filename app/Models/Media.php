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
     * Получить URL конкретного варианта (например, 'sm' или 'cover_lg').
     * Если варианта нет, вернет основной URL.
     */
    public function getVariantUrl(string $key = null): string
    {
        if ($key && isset($this->variants[$key])) {
            return Storage::url($this->variants[$key]);
        }
        return $this->url;
    }

    /**
     * Безопасное удаление файла с диска и из БД.
     * Удаляет главный файл и все сгенерированные варианты (variants).
     */
    public function safeDelete(): bool
    {
        $disk = Storage::disk('public');

        // 1. Удаляем главный файл (если он не временный)
        if ($this->disk_path && !str_starts_with($this->disk_path, 'media/temp/') && $disk->exists($this->disk_path)) {
            $disk->delete($this->disk_path);
        }

        // 2. Удаляем все сгенерированные варианты (sm, lg, cover_sm и т.д.)
        if (!empty($this->variants)) {
            foreach ($this->variants as $variantPath) {
                if ($disk->exists($variantPath)) {
                    $disk->delete($variantPath);
                }
            }
        }

        return $this->delete();
    }  
}