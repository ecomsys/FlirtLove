<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Diary extends Model
{
    use SoftDeletes;

    // КОНСТАНТЫ СТАТУСОВ
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'diary_rubric_id',
        'title',
        'body',
        'status',
        'reject_reason',
        'published_at',
        'is_comments_enabled',
        'is_quote_enabled',
        'views_count',
        'comments_count',
        'likes_count',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_comments_enabled' => 'boolean',
        'is_quote_enabled' => 'boolean',
        'views_count' => 'integer',
        'comments_count' => 'integer',
        'likes_count' => 'integer',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function diaryRubric(): BelongsTo
    {
        return $this->belongsTo(DiaryRubric::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(DiaryComment::class);
    }

    public function approvedComments(): HasMany
    {
        return $this->hasMany(DiaryComment::class)->root()->approved()->latest();
    }

    public function likes(): HasMany
    {
        return $this->hasMany(DiaryLike::class);
    }

    // ============================================
    // СКОПЫ
    // ============================================

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    // ============================================
    // ХЕЛПЕРЫ
    // ============================================

    public function publish(): bool
    {
        $status = config('diary.premoderation', true) ? self::STATUS_PENDING : self::STATUS_PUBLISHED;
        
        return $this->update([
            'status' => $status,
            'published_at' => $this->published_at ?? now(),
        ]);
    }

    public function incrementViews(): void
    {
        // Используем newQuery для безопасного апдейта без загрузки событий модели
        $this->newQuery()->where('id', $this->id)->increment('views_count');
        $this->views_count++;
    }

    public function incrementLikes(): void
    {
        $this->newQuery()->where('id', $this->id)->increment('likes_count');
        $this->likes_count++;
    }

    public function decrementLikes(): void
    {
        $this->newQuery()->where('id', $this->id)->where('likes_count', '>', 0)->decrement('likes_count');
        if ($this->likes_count > 0) {
            $this->likes_count--;
        }
    }
}