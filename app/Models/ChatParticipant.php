<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatParticipant extends Model
{
    protected $fillable = [
        'chat_id',
        'user_id',
        'unread_count',
        'last_read_at',
    ];

    protected $casts = [
        'last_read_at' => 'datetime',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ============================================
    // ХЕЛПЕРЫ
    // ============================================

    /**
     * Пометить сообщения в чате как прочитанные.
     * Вызываем, когда юзер открывает переписку.
     */
    public function markAsRead(): void
    {
        if ($this->unread_count > 0) {
            $this->update([
                'unread_count' => 0,
                'last_read_at' => now(),
            ]);
        }
    }
}