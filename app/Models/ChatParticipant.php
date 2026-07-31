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
        'is_hidden',   // Скрыл ли юзер чат у себя (архив)
        'is_muted',    // Отключил ли пуши от этого чата
        'is_blocked',  // Заблокировал ли собеседника
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
     * Пометить сообщения в чате как прочитанные (Твой код без изменений).
     * Оптимизация: обновляем БД только если действительно есть непрочитанные.
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
     * Увеличить счетчик непрочитанных (когда собеседник шлет сообщение).
     */
    public function incrementUnread(): void
    {
        $this->increment('unread_count');
    }

    /**
     * Скрыть чат (отправить в архив).
     */
    public function hide(): void
    {
        $this->update(['is_hidden' => true]);
    }

    /**
     * Показать чат (вернуть из архива).
     */
    public function unhide(): void
    {
        $this->update(['is_hidden' => false]);
    }

    /**
     * Замьютить чат (отключить уведомления).
     */
    public function mute(): void
    {
        $this->update(['is_muted' => true]);
    }

    /**
     * Размьютить чат.
     */
    public function unmute(): void
    {
        $this->update(['is_muted' => false]);
    }
}