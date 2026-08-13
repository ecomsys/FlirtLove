<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Photo extends Model
{
    use SoftDeletes; // Обязательно для сохранения улик!

    protected $fillable = [
        'user_id',
        'album_id', 
        'type',              // Новое: profile или verification
        'path_original',
        'path_large',
        'path_medium',
        'path_thumb',
        'status',            // pending, approved, rejected
        'reject_reason',     // Новое: причина отклонения
        'moderated_by',      // Новое: ID админа
        'moderated_at',      // Новое: Время проверки
        'is_primary',
        'is_intimate',
        'position'
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_intimate' => 'boolean',
        'position' => 'integer',
        'moderated_at' => 'datetime',
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

    // Модератор, проверивший фото
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
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
    // ХЕЛПЕР ДЛЯ URL (Твой код - оставляем без изменений)
    // ============================================

    /**
     *  Универсальный метод: возвращает URL или полную ссылку
     */
    private function getUrl(?string $path, ?string $fallbackPath = null): string
    {
        if (!empty($path)) {
            return filter_var($path, FILTER_VALIDATE_URL) ? $path : Storage::url($path);
        }
        
        // Если запрошенного размера нет, пробуем отдать фоллбэк (например, оригинал)
        if (!empty($fallbackPath)) {
            return filter_var($fallbackPath, FILTER_VALIDATE_URL) ? $fallbackPath : Storage::url($fallbackPath);
        }
        
        return ''; // Или возвращай URL дефолтной заглушки (placeholder)
    }


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
        // Если нет medium, отдаст original
        return $this->getUrl($this->path_medium, $this->path_original);
    }

    public function getThumbUrlAttribute(): string
    {
        // Если нет thumb, отдаст medium, а если нет medium — original
        return $this->getUrl($this->path_thumb, $this->path_medium ?? $this->path_original);
    }

    // ============================================
    // РАБОТА С ПУТЯМИ (ХЭШ-ПАПКИ)
    // ============================================

    public static function generatePath(int $userId, string $fileId, string $type): string
    {
        // Берем 3 символа хэша (от 000 до fff = 4096 папок).
        // Это идеальный баланс для масштабирования до миллионов юзеров без тормозов ФС.
        $hash = substr(md5($userId), 0, 3);
        
        // Пример пути: photos/profile/a3f/105/large_12345.webp
        return "photos/{$type}/{$hash}/{$userId}/{$fileId}.webp";
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

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // ============================================
    // ХЕЛПЕРЫ МОДЕРАЦИИ
    // ============================================

    public function markAsApproved(int $adminId): bool
    {
        return $this->update([
            'status' => 'approved',
            'moderated_by' => $adminId,
            'moderated_at' => now(),
            'reject_reason' => null,
        ]);
    }

    public function markAsRejected(int $adminId, string $reason): bool
    {
        return $this->update([
            'status' => 'rejected',
            'moderated_by' => $adminId,
            'moderated_at' => now(),
            'reject_reason' => $reason,
        ]);
    }

    // ============================================
    // СОБЫТИЯ МОДЕЛИ (ИСПРАВЛЕНО ПОД SOFT DELETES)
    // ============================================

    protected static function booted()
    {
        // Файлы удаляем ТОЛЬКО при жестком удалении (forceDelete)!
        // При мягком удалении (delete) файлы остаются на диске для СБ.
        static::forceDeleting(function ($photo) {
            $photo->deleteFiles();
        });      
    }

    /**
     * Удалить все файлы фото с диска (Твой код)
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
            if (!filter_var($path, FILTER_VALIDATE_URL) && Storage::exists($path)) {
                $deleted = $deleted && Storage::delete($path);
            }
        }

        return $deleted;
    }
}