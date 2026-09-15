<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Photo extends Model
{
    use SoftDeletes; 

    // КОНСТАНТЫ ТИПОВ
    public const TYPE_PROFILE = 'profile';
    public const TYPE_VERIFICATION = 'verification';

    // КОНСТАНТЫ СТАТУСОВ
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'album_id', 
        'type',              
        'path_original',
        'path_large', 
        'path_medium',
        'path_thumb',
        'status',            
        'reject_reason',     
        'moderated_by',      
        'moderated_at',      
        'phash',
        'is_primary',
        'is_intimate',
        'position',
         'title',        
        'description'    
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
        return $this->hasMany(PhotoComment::class)->where('status', PhotoComment::STATUS_APPROVED);
    }

    public function pendingComments(): HasMany
    {
        return $this->hasMany(PhotoComment::class)->where('status', PhotoComment::STATUS_PENDING);
    }

    // ============================================
    // ХЕЛПЕР ДЛЯ URL
    // ============================================

    private function getUrl(?string $path, ?string $fallbackPath = null): string
    {
        if (!empty($path)) {
            return filter_var($path, FILTER_VALIDATE_URL) ? $path : Storage::url($path);
        }
        
        if (!empty($fallbackPath)) {
            return filter_var($fallbackPath, FILTER_VALIDATE_URL) ? $fallbackPath : Storage::url($fallbackPath);
        }
        
        return ''; 
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
        return $this->getUrl($this->path_medium, $this->path_original);
    }

    public function getThumbUrlAttribute(): string
    {
        return $this->getUrl($this->path_thumb, $this->path_medium ?? $this->path_original);
    }

    // ============================================
    // РАБОТА С ПУТЯМИ (ХЭШ-ПАПКИ)
    // ============================================

    public static function generatePath(int $userId, string $fileId, string $type): string
    {
        // Берем 3 символа хэша (от 000 до fff = 4096 папок).
        $hash = substr(md5($userId), 0, 3);
        
        return "photos/{$type}/{$hash}/{$userId}/{$fileId}.webp";
    }

    // ============================================
    // СКОПЫ
    // ============================================

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_intimate', false);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    // ============================================
    // ХЕЛПЕРЫ МОДЕРАЦИИ
    // ============================================

    public function markAsApproved(int $adminId): bool
    {
        return $this->update([
            'status' => self::STATUS_APPROVED,
            'moderated_by' => $adminId,
            'moderated_at' => now(),
            'reject_reason' => null,
        ]);
    }

    public function markAsRejected(int $adminId, string $reason): bool
    {
        return $this->update([
            'status' => self::STATUS_REJECTED,
            'moderated_by' => $adminId,
            'moderated_at' => now(),
            'reject_reason' => $reason,
        ]);
    }

    // ============================================
    // СОБЫТИЯ МОДЕЛИ
    // ============================================

    protected static function booted()
    {
        static::forceDeleting(function ($photo) {
            $photo->deleteFiles();
        });      
    }

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