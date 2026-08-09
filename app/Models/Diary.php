<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Diary extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'rubric_id',
        'title',
        'body',
        'status',
        'published_at',
        'is_comments_enabled',
        'views_count',
        'comments_count',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_comments_enabled' => 'boolean',
        'views_count' => 'integer',
        'comments_count' => 'integer',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function rubric()
    {
        return $this->belongsTo(Rubric::class);
    }

    // ============================================
    // СКОПЫ
    // ============================================

    /**
     * Только опубликованные посты (для ленты и профиля)
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Только черновики
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    // ============================================
    // ХЕЛПЕРЫ
    // ============================================

    /**
     * Опубликовать пост (снимает с черновика)
     */
    public function publish(): bool
    {
        return $this->update([
            'status' => 'published',
            'published_at' => $this->published_at ?? now(),
        ]);
    }

    /**
     * Увеличить счетчик просмотров поста
     */
    public function incrementViews(): void
    {
        $this->increment('views_count');
    }
}