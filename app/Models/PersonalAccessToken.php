<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    // Явно указываем касты (Sanctum делает это под капотом, но для IDE полезно)
    protected $casts = [
        'abilities' => 'json',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    public function tokenable(): MorphTo
    {
        return $this->morphTo('tokenable');
    }

    // ============================================
    // СКОПЫ ДЛЯ КРОНА И АДМИНКИ
    // ============================================

    /**
     * Скоуп для крона: найти все протухшие токены, чтобы удалить их.
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')->where('expires_at', '<=', now());
    }

    // ============================================
    // ХЕЛПЕРЫ
    // ============================================

    /**
     * Проверка, протух ли токен (полезно для middleware).
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}