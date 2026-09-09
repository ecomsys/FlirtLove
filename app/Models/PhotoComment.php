<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PhotoComment extends Model
{
    use SoftDeletes; 

    // КОНСТАНТЫ СТАТУСОВ
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SPAM = 'spam';

    protected $fillable = [
        'photo_id',
        'user_id',
        'content',
        'status',
        'reject_reason',
        'moderated_by',
        'moderated_at',
        'parent_id',
        'likes_count',
        'reports_count',
        'replies_count',
        'is_pinned',
        'edited_at',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'likes_count' => 'integer',
        'reports_count' => 'integer',
        'replies_count' => 'integer',
        'moderated_at' => 'datetime',
        'edited_at' => 'datetime',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(PhotoComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        // Ответы по умолчанию тянем только одобренные
        return $this->hasMany(PhotoComment::class, 'parent_id')->where('status', self::STATUS_APPROVED);
    }

    public function allReplies(): HasMany
    {
        return $this->hasMany(PhotoComment::class, 'parent_id');
    }

    // ============================================
    // СКОПЫ
    // ============================================

    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeSpam(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SPAM);
    }

    // ============================================
    // МЕТОДЫ И ХЕЛПЕРЫ
    // ============================================

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            self::STATUS_PENDING  => ['variant' => 'warning', 'label' => 'Ожидает'],
            self::STATUS_APPROVED => ['variant' => 'success', 'label' => 'Одобрен'],
            self::STATUS_REJECTED => ['variant' => 'destructive', 'label' => 'Отклонен'],
            self::STATUS_SPAM     => ['variant' => 'destructive', 'label' => 'Спам'],
            default               => ['variant' => 'secondary', 'label' => 'Неизвестно'],
        };
    }

    public function approve(int $adminId): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'moderated_by' => $adminId,
            'moderated_at' => now(),
            'reject_reason' => null,
        ]);
    }

    public function reject(int $adminId, string $reason): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'moderated_by' => $adminId,
            'moderated_at' => now(),
            'reject_reason' => $reason,
        ]);
    }

    /**
     * Пометить как спам (Исправлено: ставим правильный статус!)
     */
    public function markAsSpam(int $adminId): void
    {
        $this->update([
            'status' => self::STATUS_SPAM,          
            'moderated_by' => $adminId,
            'moderated_at' => now(),
            'reject_reason' => 'spam', // Дублируем для удобства фильтров
        ]);
    }
}