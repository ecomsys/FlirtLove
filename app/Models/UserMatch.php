<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMatch  extends Model
{
    protected $table = 'user_matches'; 
    
    protected $fillable = [
        'user1_id',
        'user2_id',
    ];

    public function user1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user1_id');
    }

    public function user2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user2_id');
    }

    /**
     * Локальный скоуп: Исключает чаты, где замешаны админы.
     * Используется только для private чатов (знакомства).
     */
    public function scopeExcludeAdmins($query)
    {
        return $query->whereHas('user1', fn($q) => $q->where('is_admin', false))
                     ->whereHas('user2', fn($q) => $q->where('is_admin', false));
    }
}