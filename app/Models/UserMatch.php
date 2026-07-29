<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMatch extends Model
{
    protected $table = 'user_matches'; 
    
    protected $fillable = [
        'user1_id',
        'user2_id',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    public function user1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user1_id');
    }

    public function user2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user2_id');
    }

    // ============================================
    // ХЕЛПЕРЫ
    // ============================================

    /**
     * Получить второго участника матча.
     * ВАЖНО: Эта функция загружает юзера из связи, если она уже загружена,
     * иначе делает запрос в БД.
     */
    public function getPartner(int $userId): ?User
    {
        if ($this->user1_id === $userId) {
            return $this->relationLoaded('user2') ? $this->user2 : $this->user2()->first();
        }
        
        return $this->relationLoaded('user1') ? $this->user1 : $this->user1()->first();
    }
}