<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    /**
     * Кто инициировал блокировку
     */
    public function blocker()
    {
        return $this->belongsTo(User::class, 'blocker_id');
    }

    /**
     * Кого заблокировали
     */
    public function blocked()
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }

    // ============================================
    // ХЕЛПЕРЫ
    // ============================================

    /**
     * Быстрая проверка, заблокирован ли юзер А юзером Б.
     * Используется в FeedService и ChatService перед показом анкеты или созданием сообщения.
     */
    public static function isBlocked(int $blockerId, int $blockedId): bool
    {
        return static::where('blocker_id', $blockerId)
            ->where('blocked_id', $blockedId)
            ->exists();
    }
}