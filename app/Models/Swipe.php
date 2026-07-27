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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
        /**
     * Локальный скоуп: Исключает свайпы, где админ выступает инициатором или целью.
     */
    public function scopeExcludeAdmins($query)
    {
        return $query->whereHas('user', fn($q) => $q->where('is_admin', false))
                     ->whereHas('targetUser', fn($q) => $q->where('is_admin', false));
    }
}