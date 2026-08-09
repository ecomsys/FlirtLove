<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rubric extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    /**
     * Посты (дневники), принадлежащие этой рубрике
     */
    public function diaries()
    {
        return $this->hasMany(Diary::class);
    }

    // ============================================
    // СКОПЫ
    // ============================================

    /**
     * Только активные рубрики (для вывода в меню сайта)
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Сортировка по умолчанию (для вывода в меню)
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    // ============================================
    // ХЕЛПЕРЫ
    // ============================================

    /**
     * Поиск рубрики по слагу
     */
    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }
}