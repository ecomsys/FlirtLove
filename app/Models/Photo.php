<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Photo extends Model
{    

    protected $fillable = [
        'user_id',
        'album_id', 
        'path',          // дефолтный 
        'path_original', // путь original
        'path_large',    // путь large w = 1600px
        'path_medium',   // путь medium w = 820px
        'path_thumb',    // путь тумбнейла 200*200
        'is_primary',    // на аватар (boolean)
        'is_intimate',   // 18+ (boolean)
        'status'         // pending, approved, rejected
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_intimate' => 'boolean',
    ];

    protected $appends = [
        'url',
        'original_url',
        'large_url',
        'medium_url',
        'thumb_url',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================
    
       // Отношение к альбому
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    // Скоуп для получения фото по альбому
    public function scopeInAlbum($query, $albumId)
    {
        return $query->where('album_id', $albumId);
    }

    /**
     * Юзер
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Комментарии к фото
     */
    public function comments(): HasMany
    {
        return $this->hasMany(PhotoComment::class);
    }

    /**
     * Одобренные комментарии
     */
    public function approvedComments(): HasMany
    {
        return $this->hasMany(PhotoComment::class)->where('status', 'approved');
    }

    /**
     * Комментарии на модерации
     */
    public function pendingComments(): HasMany
    {
        return $this->hasMany(PhotoComment::class)->where('status', 'pending');
    }

    /**
     * Количество комментариев на модерации
     */
    public function getPendingCommentsCountAttribute(): int
    {
        return $this->pendingComments()->count();
    }

    // ============================================
    // РАБОТА С ПУТЯМИ (ХЭШ-ПАПКИ)
    // ============================================

    /**
     * Сгенерировать путь для фото с хэш-папками
     */
    public static function generatePath(int $userId, string $fileId, string $type): string
    {
        $hash = substr(md5($userId), 0, 2); // 00-ff
        return "photos/approved/{$hash}/{$userId}/{$type}_{$fileId}.webp";
    }

    /**
     * Получить хэш-путь для текущего пользователя
     */
    public function getUserHashPath(): string
    {
        $hash = substr(md5($this->user_id), 0, 2);
        return "photos/approved/{$hash}/{$this->user_id}/";
    }

    /**
     * Найти все фото пользователя
     */
    public static function getUserPhotos(int $userId): array
    {
        $hash = substr(md5($userId), 0, 2);
        $path = "photos/approved/{$hash}/{$userId}/";

        if (!Storage::disk('public')->exists($path)) {
            return [];
        }

        return Storage::disk('public')->files($path);
    }

    /**
     * Удалить все фото пользователя
     */
    public static function deleteUserPhotos(int $userId): bool
    {
        $hash = substr(md5($userId), 0, 2);
        $path = "photos/approved/{$hash}/{$userId}/";

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->deleteDirectory($path);
        }

        return true;
    }

    // ============================================
    // АКСЕССОРЫ ДЛЯ URL
    // ============================================

    /**
     * Основной URL (для совместимости)
     */
    public function getUrlAttribute(): string
    {
        if (filter_var($this->path, FILTER_VALIDATE_URL)) {
            return $this->path;
        }

        return $this->path ? asset('storage/' . $this->path) : '';
    }

    /**
     * URL оригинала (максимальное качество)
     */
    public function getOriginalUrlAttribute(): string
    {
        if (filter_var($this->path_original, FILTER_VALIDATE_URL)) {
            return $this->path_original;
        }

        return $this->path_original ? asset('storage/' . $this->path_original) : $this->url;
    }

    /**
     * URL Large (1600px для больших экранов)
     */
    public function getLargeUrlAttribute(): string
    {
        if (filter_var($this->path_large, FILTER_VALIDATE_URL)) {
            return $this->path_large;
        }

        return $this->path_large ? asset('storage/' . $this->path_large) : $this->url;
    }

    /**
     * URL Medium (820px стандарт)
     */
    public function getMediumUrlAttribute(): string
    {
        if (filter_var($this->path_medium, FILTER_VALIDATE_URL)) {
            return $this->path_medium;
        }

        return $this->path_medium ? asset('storage/' . $this->path_medium) : $this->url;
    }

    /**
     * URL Thumb (200x200 для превью)
     */
    public function getThumbUrlAttribute(): string
    {
        if (filter_var($this->path_thumb, FILTER_VALIDATE_URL)) {
            return $this->path_thumb;
        }

        return $this->path_thumb ? asset('storage/' . $this->path_thumb) : $this->url;
    }

    // ============================================
    // ПОЛУЧЕНИЕ ОПТИМАЛЬНОЙ ВЕРСИИ
    // ============================================

    /**
     * Получить оптимальный URL для контекста
     */
    public function getOptimalUrl(string $context = 'default'): string
    {
        return match ($context) {
            'gallery' => $this->medium_url,
            'profile' => $this->medium_url,
            'zoom' => $this->large_url,
            'thumbnail' => $this->thumb_url,
            'download' => $this->original_url,
            default => $this->medium_url,
        };
    }

    /**
     * Получить все версии фото в массиве
     */
    public function getVersionsAttribute(): array
    {
        return [
            'original' => $this->original_url,
            'large' => $this->large_url,
            'medium' => $this->medium_url,
            'thumb' => $this->thumb_url,
        ];
    }

    // ============================================
    // РАБОТА С ФАЙЛАМИ
    // ============================================

    /**
     * Удалить все файлы фото
     */
    public function deleteFiles(): bool
    {
        $files = [
            $this->path,
            $this->path_original,
            $this->path_large,
            $this->path_medium,
            $this->path_thumb,
        ];

        $deleted = true;
        foreach (array_filter($files) as $file) {
            if (Storage::disk('public')->exists($file)) {
                $deleted = $deleted && Storage::disk('public')->delete($file);
            }
        }

        return $deleted;
    }

    /**
     * Проверить, существуют ли все файлы
     */
    public function filesExist(): bool
    {
        $files = [
            $this->path,
            $this->path_medium,
            $this->path_thumb,
        ];

        foreach (array_filter($files) as $file) {
            if (!Storage::disk('public')->exists($file)) {
                return false;
            }
        }

        return true;
    }

    // ============================================
    // СКОПЫ
    // ============================================

    /**
     * Только одобренные фото
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Только на модерации
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Только отклоненные
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Только основные фото
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Только не интимные (для публичного показа)
     */
    public function scopePublic($query)
    {
        return $query->where('is_intimate', false);
    }

    // ============================================
    // СОБЫТИЯ МОДЕЛИ
    // ============================================

    protected static function booted()
    {
        // При удалении модели удаляем файлы
        static::deleting(function ($photo) {
            $photo->deleteFiles();
        });
    }
}


// ====================================================================================================================
// КАК ИСПОЬЗОВАТЬ В Blade?
// ====================================================================================================================

// Превью (Thumb)

// <img src="{{ $photo->thumb_url }}" 
//      alt="Фото" 
//      class="w-16 h-16 object-cover rounded-full" />

// Анкета (Medium)

// <img src="{{ $photo->medium_url }}" 
//      alt="Фото пользователя" 
//      class="w-full max-w-2xl rounded-lg shadow-lg" 
//      loading="lazy" />

// С увеличение по клику (Large)

// <a href="{{ $photo->large_url }}" 
//    target="_blank" 
//    class="block cursor-zoom-in">
//     <img src="{{ $photo->medium_url }}" 
//          alt="Фото" 
//          class="w-full rounded-lg hover:opacity-95 transition" />
// </a>

// srcset (самый крутой способ)

// <img src="{{ $photo->medium_url }}" 
//      srcset="{{ $photo->thumb_url }} 200w,
//              {{ $photo->medium_url }} 820w,
//              {{ $photo->large_url }} 1600w"
//      sizes="(max-width: 640px) 200px,
//             (max-width: 1024px) 820px,
//             1600px"
//      alt="Фото" 
//      class="w-full rounded-lg" 
//      loading="lazy" />

// Все версии

// <div class="space-y-2">
//     <p>Оригинал: <a href="{{ $photo->original_url }}" target="_blank">Скачать</a></p>
//     <p>Large: <a href="{{ $photo->large_url }}" target="_blank">Открыть</a></p>
//     <p>Medium: <a href="{{ $photo->medium_url }}" target="_blank">Открыть</a></p>
//     <p>Thumb: <a href="{{ $photo->thumb_url }}" target="_blank">Открыть</a></p>
// </div>
