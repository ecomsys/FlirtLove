<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class PhotoComment extends Model
{
    protected $fillable = [
        'photo_id',
        'user_id',
        'content',
        'status',
        'parent_id',
        'likes_count',
        'reports_count',
        'is_edited',
        'is_pinned',
        'edited_at',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'is_edited' => 'boolean',
        'is_pinned' => 'boolean',
        'likes_count' => 'integer',
        'reports_count' => 'integer',
        'edited_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    /**
     * Фото, к которому относится комментарий
     */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    /**
     * Автор комментария
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Родительский комментарий (для ответов)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(PhotoComment::class, 'parent_id');
    }

    /**
     * Ответы на комментарий
     */
    public function replies(): HasMany
    {
        return $this->hasMany(PhotoComment::class, 'parent_id');
    }

    // ============================================
    // СКОПЫ
    // ============================================

        /**
     * Локальный скоуп: Исключает комментарии, оставленные администраторами.
     */
    public function scopeExcludeAdmins($query)
    {
        return $query->whereHas('user', fn($q) => $q->where('is_admin', false));
    }
    /**
     * Только корневые комментарии (не ответы)
     */
    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Только ответы
     */
    public function scopeReplies(Builder $query): Builder
    {
        return $query->whereNotNull('parent_id');
    }

    /**
     * Ожидают модерации
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Одобренные
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /**
     * Отклоненные
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Спам
     */
    public function scopeSpam(Builder $query): Builder
    {
        return $query->where('status', 'spam');
    }

    /**
     * Не спам
     */
    public function scopeNotSpam(Builder $query): Builder
    {
        return $query->where('status', '!=', 'spam');
    }

    // ============================================
    // МЕТОДЫ
    // ============================================

    /**
     * Является ли комментарий корневым
     */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Является ли комментарий ответом
     */
    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * Одобрен ли комментарий
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * На модерации ли комментарий
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Отклонен ли комментарий
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Спам ли комментарий
     */
    public function isSpam(): bool
    {
        return $this->status === 'spam';
    }

    /**
     * Получить статус для бейджа
     */
    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            'pending'  => ['variant' => 'warning', 'label' => 'Ожидает'],
            'approved' => ['variant' => 'success', 'label' => 'Одобрен'],
            'rejected' => ['variant' => 'destructive', 'label' => 'Отклонен'],
            'spam'     => ['variant' => 'destructive', 'label' => 'Спам'],
            default    => ['variant' => 'secondary', 'label' => 'Неизвестно'],
        };
    }

    /**
     * Одобрить комментарий
     */
    public function approve(): void
    {
        $this->update([
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }

    /**
     * Отклонить комментарий
     */
    public function reject(): void
    {
        $this->update([
            'status' => 'rejected',
            'rejected_at' => now(),
        ]);
    }

    
    /**
     * уведомление автору при модерации комментария к фото
     */
    public function notifyAuthor(string $status): void
    {
        $user = $this->user;
        if ($user) {
            $user->notify(new \App\Notifications\CommentModerated($this, $status));
        }
    }

    /**
     * Пометить как спам
     */
    public function markAsSpam(): void
    {
        $this->update(['status' => 'spam']);
    }

    /**
     * Получить все ответы с их статусами
     */
    public function getRepliesWithStatus(string $status = 'approved'): HasMany
    {
        return $this->replies()->where('status', $status);
    }
}