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

    // Родительский комментарий (на который ответили/процитировали)
    public function parent(): BelongsTo
    {
        return $this->belongsTo(DiaryComment::class, 'parent_id');
    }

    // Ответы на этот комментарий
    public function replies(): HasMany
    {
        // По умолчанию тянем только одобренные ответы
        return $this->hasMany(DiaryComment::class, 'parent_id')->where('status', 'approved')->latest();
    }

    // Все ответы (для админки)
    public function allReplies(): HasMany
    {
        return $this->hasMany(DiaryComment::class, 'parent_id')->withoutGlobalScopes();
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
        return $query->where('status', 'approved');
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
            'status' => 'approved',
            'moderated_by' => $adminId,
            'moderated_at' => now(),
            'reject_reason' => null,
        ]);
    }

    public function reject(int $adminId, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'moderated_by' => $adminId,
            'moderated_at' => now(),
            'reject_reason' => $reason,
        ]);
    }
}