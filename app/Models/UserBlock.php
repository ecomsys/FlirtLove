<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBlock extends Model
{
    protected $fillable = [
        'blocker_id',
        'blocked_id',
        'reason',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocker_id');
    }

    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }

    // ============================================
    // ХЕЛПЕРЫ
    // ============================================

    /**
     * Быстрая проверка, заблокировал ли Юзер А юзера Б.
     */
    public static function isBlocked(int $blockerId, int $blockedId): bool
    {
        return static::where('blocker_id', $blockerId)
            ->where('blocked_id', $blockedId)
            ->exists();
    }

    /**
     * КРИТИЧЕСКИ ВАЖНО для дейтинга: проверка, есть ли блокировка между ними в ЛЮБУЮ сторону.
     * Используется перед созданием мэтча или отправкой сообщения.
     */
    public static function isEitherBlocked(int $userA, int $userB): bool
    {
        return static::where(function ($query) use ($userA, $userB) {
            $query->where('blocker_id', $userA)->where('blocked_id', $userB);
        })->orWhere(function ($query) use ($userA, $userB) {
            $query->where('blocker_id', $userB)->where('blocked_id', $userA);
        })->exists();
    }
}