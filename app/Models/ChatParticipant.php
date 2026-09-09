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
        'is_hidden',
        'is_muted',
        'is_blocked',
    ];

    protected $casts = [
        'last_read_at' => 'datetime',
        'unread_count' => 'integer',
        'is_hidden' => 'boolean',
        'is_muted' => 'boolean',
        'is_blocked' => 'boolean',
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
    // ХЕЛПЕРЫ ДЛЯ СТАТУСОВ ЧАТА
    // ============================================

    /**
     * Пометить сообщения в чате как прочитанные.
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

    /**
     * Увеличить счетчик непрочитанных.
     */
    public function incrementUnread(): void
    {
        // Атомарный инкремент в БД + обновление модели в памяти
        $this->increment('unread_count');
    }

    public function hide(): void
    {
        $this->update(['is_hidden' => true]);
    }

    public function unhide(): void
    {
        $this->update(['is_hidden' => false]);
    }

    public function mute(): void
    {
        $this->update(['is_muted' => true]);
    }

    public function unmute(): void
    {
        $this->update(['is_muted' => false]);
    }

    // НОВЫЕ ХЕЛПЕРЫ ДЛЯ БЛОКИРОВКИ (filld gap)
    public function block(): void
    {
        $this->update(['is_blocked' => true]);
    }

    public function unblock(): void
    {
        $this->update(['is_blocked' => false]);
    }
}