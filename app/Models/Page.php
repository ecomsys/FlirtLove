<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'body',
        'meta_description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ============================================
    // СКОПЫ
    // =================================6==========

    /**
     * Только опубликованные страницы (для фронтенда)
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ============================================
    // ХЕЛПЕРЫ
    // ============================================

    /**
     * Быстрый поиск по слагу (для PageController)
     */
    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }
}
