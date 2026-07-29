<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Swipe extends Model
{
    protected $fillable = [
        'user_id',
        'target_user_id',
        'type',
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

    // Убрали scopeExcludeAdmins, так как это тяжелый whereHas.
    // Админов мы фильтруем на уровне выбора юзеров для ленты, 
    // в свайпы они просто не попадут.

    /**
     * Только лайки и суперлайки (для поиска мэтчей)
     */
    public function scopePositive($query)
    {
        return $query->whereIn('type', ['like', 'superlike']);
    }
}