<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlogPost extends Model
{
    use SoftDeletes;

    // УБРАЛИ 'published_at' из fillable
    protected $fillable = [
        'user_id',
        'cover_media_id',
        'title',
        'slug',
        'excerpt',
        'body',
        'category_id', 
        'status',
        'is_featured',
        'views_count',
    ];

    // УБРАЛИ 'published_at' из casts
    protected $casts = [
        'is_featured' => 'boolean',
        'views_count' => 'integer',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================
    
    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    // ============================================
    // СКОПЫ
    // ============================================

    public function scopePublished($query)
    {
        // Теперь просто проверяем статус, без заморочек с датами
        return $query->where('status', 'published');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeOfCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    // ============================================
    // ХЕЛПЕРЫ БИЗНЕС-ЛОГИКИ
    // ============================================

    public function isPublished(): bool
    {
        // Просто проверка статуса
        return $this->status === 'published';
    }

    /**
     * Опубликовать статью.
     */
    public function publish(): bool
    {
        return $this->update([
            'status' => 'published'
        ]);
    }

    /**
     * Снять с публикации (вернуть в черновики).
     */
    public function unpublish(): bool
    {
        return $this->update([
            'status' => 'draft',
        ]);
    }

    /**
     * Увеличить счетчик просмотров.
     */
    public function incrementViews(): void
    {
        $this->increment('views_count');
    }

    // ============================================
    // АКСЕССОРЫ ДЛЯ UI
    // ============================================

    public function getCoverUrlAttribute(): string
    {
        return $this->cover?->getVariantUrl('lg') ?? asset('images/default-blog-cover.jpg');
    }

    public function getOgImageUrlAttribute(): string
    {
        return $this->cover?->getVariantUrl('og') ?? $this->cover_url;
    }

    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            'draft'     => ['variant' => 'warning', 'label' => 'Черновик'],
            'published' => ['variant' => 'success', 'label' => 'Опубликована'],
            'archived'  => ['variant' => 'secondary', 'label' => 'В архиве'],
            default     => ['variant' => 'secondary', 'label' => 'Неизвестно'],
        };
    }
}