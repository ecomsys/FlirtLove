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

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(PhotoComment::class, 'parent_id');
    }

    public function replies(): HasMany
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
        return $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }

    public function scopeSpam(Builder $query): Builder
    {
        return $query->where('status', 'spam');
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
        return $this->status === 'approved';
    }

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
     * Пометить как спам
     */
    public function markAsSpam(): void
    {
        $this->update(['status' => 'spam']);
    }
}