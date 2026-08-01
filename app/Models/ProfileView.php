<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileView extends Model
{
    protected $fillable = [
        'viewer_id',
        'viewed_id',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    /**
     * Кто смотрел анкету
     */
    public function viewer()
    {
        return $this->belongsTo(User::class, 'viewer_id');
    }

    /**
     * Чью анкету смотрели
     */
    public function viewed()
    {
        return $this->belongsTo(User::class, 'viewed_id');
    }

    // ============================================
    // ХЕЛПЕРЫ
    // ============================================

    /**
     * Записать просмотр профиля.
     * Используем updateOrCreate: если юзер уже смотрел этот профиль сегодня,
     * мы просто обновим updated_at (время последнего просмотра), не раздувая таблицу.
     */
    public static function recordView(int $viewerId, int $viewedId): void
    {
        // Себя не смотрим
        if ($viewerId === $viewedId) {
            return;
        }

        static::updateOrCreate(
            ['viewer_id' => $viewerId, 'viewed_id' => $viewedId],
            // Пустой массив, потому что нам нужно только обновить updated_at при существующей записи
        );
    }
}