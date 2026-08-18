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

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeOfCollection($query, string $collection)
    {
        return $query->where('collection', $collection);
    }

    public function scopeImages($query)
    {
        return $query->where('type', 'image');
    }

    public function safeDelete(): bool
    {
        $disk = Storage::disk('public');

        if ($this->disk_path && !str_starts_with($this->disk_path, 'media/temp/') && $disk->exists($this->disk_path)) {
            $disk->delete($this->disk_path);
        }

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
     */
    public function getVariantUrl(string $key = null): string
    {
        $variants = $this->variants ?? [];
        
        // 1. Если просим конкретный ключ (например 'sm') и он есть — отдаем его
        if ($key && isset($variants[$key])) {
            return asset(Storage::url($variants[$key]));
        }
        
        // 2. Если просим 'orig', а его нет — отдаем самый большой сгенерированный (последний в массиве)
        if ($key === 'orig' && !empty($variants)) {
            return asset(Storage::url(end($variants)));
        }
        
        // 3. Если просим 'lg', а его нет — отдаем самый большой сгенерированный
        if ($key === 'lg' && !empty($variants)) {
            return asset(Storage::url(end($variants)));
        }
        
        // 4. Если просим 'thumb' или 'sm', а их нет — отдаем ПЕРВЫЙ доступный (самый маленький)
        if (in_array($key, ['thumb', 'sm']) && !empty($variants)) {
            return asset(Storage::url(reset($variants)));
        }
        
        // 5. Фоллбэк: отдаем то, что лежит в базе (для обрабатываемых файлов)
        return asset($this->url);
    }
}