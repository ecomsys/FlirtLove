<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiaryComment extends Model
{
    use SoftDeletes;

    // КОНСТАНТЫ СТАТУСОВ
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PENDING = 'pending';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SPAM = 'spam';

    protected $fillable = [
        'diary_id',
        'user_id',
        'content',
        'parent_id',
        'status',
        'reject_reason',
        'moderated_by',
        'moderated_at',
        'likes_count',
        'replies_count',
    ];

    protected $casts = [
        'moderated_at' => 'datetime',
        'likes_count' => 'integer',
        'replies_count' => 'integer',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================
    
    public function likes(): HasMany
    {
        return $this->hasMany(DiaryCommentLike::class);
    }

    public function diary(): BelongsTo
    {
        return $this->belongsTo(Diary::class);
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
        return $this->belongsTo(DiaryComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(DiaryComment::class, 'parent_id')->where('status', self::STATUS_APPROVED)->latest();
    }

    public function allReplies(): HasMany
    {
        // Убрали withoutGlobalScopes(), просто тянем все ответы без фильтра по статусу
        return $this->hasMany(DiaryComment::class, 'parent_id')->latest();
    }

    // ============================================
    // СКОПЫ
    // ============================================

    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    // ============================================
    // ХЕЛПЕРЫ
    // ============================================

    public function isRoot(): bool
    {
        return $this->parent_id === null;
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
}