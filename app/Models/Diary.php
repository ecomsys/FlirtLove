<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Diary extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'rubric_id',
        'title',
        'body',
        'status',
        'reject_reason',
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

        // Все комментарии поста
    public function comments(): HasMany
    {
        return $this->hasMany(DiaryComment::class);
    }

    // Только корневые (одобренные) комментарии для вывода под постом на фронте
    public function approvedComments(): HasMany
    {
        return $this->hasMany(DiaryComment::class)->root()->approved()->latest();
    }

    // ============================================
    // СКОПЫ
    // ============================================

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    // ============================================
    // ХЕЛПЕРЫ
    // ============================================

    /**
     * Отправить пост на модерацию (или опубликовать сразу, если премодерация отключена)
     */
    public function publish(): bool
    {
        $status = config('diary.premoderation', true) ? 'pending' : 'published';
        
        return $this->update([
            'status' => $status,
            'published_at' => $this->published_at ?? now(),
        ]);
    }

    /**
     * Увеличить счетчик просмотров поста (без обновления updated_at)
     */
    public function incrementViews(): void
    {
        static::where('id', $this->id)->increment('views_count');
        $this->views_count++;
    }
}