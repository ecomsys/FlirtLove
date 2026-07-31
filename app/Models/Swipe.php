<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Swipe extends Model
{
    protected $fillable = [
        'user_id',
        'target_user_id',
        'type',          // like, dislike, superlike
        'rewinded_at',   // Новое: метка отмены свайпа
    ];

    protected $casts = [
        'rewinded_at' => 'datetime',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    // ============================================
    // СКОПЫ
    // ============================================

    /**
     * Только лайки и суперлайки (для поиска мэтчей)
     */
    public function scopePositive($query)
    {
        return $query->whereIn('type', ['like', 'superlike']);
    }

    /**
     * Только активные (не отмененные) свайпы.
     * КРИТИЧЕСКИ ВАЖНО для сборки ленты: мы не должны показывать юзеров,
     * которых мы свайпнули, даже если потом отменили свайп (иначе вечный цикл).
     */
    public function scopeActive($query)
    {
        return $query->whereNull('rewinded_at');
    }

    // ============================================
    // ХЕЛПЕРЫ
    // ============================================

    /**
     * Был ли свайп лайком или суперлайком
     */
    public function isPositive(): bool
    {
        return in_array($this->type, ['like', 'superlike']);
    }

    /**
     * Отменить свайп (функция Rewind).
     * Не удаляем запись, а просто ставим метку времени.
     */
    public function rewind(): bool
    {
        if ($this->rewinded_at) {
            return true; // Уже отменен
        }
        return $this->update(['rewinded_at' => now()]);
    }
}