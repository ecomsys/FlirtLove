<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFavorite extends Model
{
    protected $fillable = [
        'user_id',
        'favorite_user_id',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function favorite(): BelongsTo
    {
        return $this->belongsTo(User::class, 'favorite_user_id');
    }

    // ============================================
    // ХЕЛПЕРЫ
    // ============================================

    /**
     * Проверка, находится ли юзер Б в избранном у юзера А.
     * Используется перед добавлением, чтобы не ловить ошибки уникального ключа.
     */
    public static function isFavorite(int $userId, int $favoriteUserId): bool
    {
        return static::where('user_id', $userId)
            ->where('favorite_user_id', $favoriteUserId)
            ->exists();
    }
}