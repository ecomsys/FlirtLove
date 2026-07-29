<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Photo extends Model
{    
    protected $fillable = [
        'user_id',
        'album_id', 
        'path_original',
        'path_large',
        'path_medium',
        'path_thumb',
        'is_primary',
        'is_intimate',
        'status',
        'position'
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_intimate' => 'boolean',
        'position' => 'integer',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================
    
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PhotoComment::class);
    }

    public function approvedComments(): HasMany
    {
        return $this->hasMany(PhotoComment::class)->where('status', 'approved');
    }

    public function pendingComments(): HasMany
    {
        return $this->hasMany(PhotoComment::class)->where('status', 'pending');
    }

    // ============================================
    // ХЕЛПЕР ДЛЯ URL (С ПРОВЕРКОЙ НА ВНЕШНИЕ ССЫЛКИ)
    // ============================================

    /**
     *  Универсальный метод: возвращает URL или полную ссылку
     */
    private function getUrl(?string $path): string
    {
        if (empty($path)) {
            return '';
        }

        // Если это уже полный URL (http/https) - возвращаем как есть
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        // Иначе - генерируем Storage URL
        return Storage::url($path);
    }

    // ============================================
    // АКСЕССОРЫ ДЛЯ URL
    // ============================================

    // Основной URL (по умолчанию отдаем medium для показа в анкете)
    public function getUrlAttribute(): string
    {
        return $this->medium_url; 
    }

    public function getOriginalUrlAttribute(): string
    {
        return $this->getUrl($this->path_original);
    }

    public function getLargeUrlAttribute(): string
    {
        return $this->getUrl($this->path_large);
    }

    public function getMediumUrlAttribute(): string
    {
        return $this->getUrl($this->path_medium);
    }

    public function getThumbUrlAttribute(): string
    {
        return $this->getUrl($this->path_thumb);
    }

    // ============================================
    // РАБОТА С ПУТЯМИ (ХЭШ-ПАПКИ)
    // ============================================

    public static function generatePath(int $userId, string $fileId, string $type): string
    {
        $hash = substr(md5($userId), 0, 2);
        return "photos/approved/{$hash}/{$userId}/{$type}_{$fileId}.webp";
    }

    // ============================================
    // СКОПЫ
    // ============================================

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopePublic($query)
    {
        return $query->where('is_intimate', false);
    }

    // ============================================
    // СОБЫТИЯ МОДЕЛИ
    // ============================================

    protected static function booted()
    {
        static::deleting(function ($photo) {
            $photo->deleteFiles();
        });      
    }

    /**
     * Удалить все файлы фото с диска
     */
    public function deleteFiles(): bool
    {
        $paths = [
            $this->path_original,
            $this->path_large,
            $this->path_medium,
            $this->path_thumb,
        ];

        $deleted = true;
        foreach (array_filter($paths) as $path) {
            //  Удаляем только локальные файлы!
            if (!filter_var($path, FILTER_VALIDATE_URL) && Storage::exists($path)) {
                $deleted = $deleted && Storage::delete($path);
            }
        }

        return $deleted;
    }
}