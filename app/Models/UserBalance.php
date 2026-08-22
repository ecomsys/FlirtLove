<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class UserBalance extends Model
{
    protected $fillable = [
        'user_id', 'credits', 
        'superlikes_remaining', 'superlikes_reset_at',
    ];

    protected $casts = [
        'credits' => 'integer',
        'superlikes_remaining' => 'integer',
        'superlikes_reset_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ============================================
    // ХЕЛПЕРЫ ДЛЯ ВАЛЮТЫ (С защитой от Race Condition)
    // ============================================

    public function addCredits(int $amount): bool
    {
        if ($amount <= 0) return false;

        $affected = DB::table('user_balances')
            ->where('id', $this->id)
            ->increment('credits', $amount);

        if ($affected) $this->credits += $amount;

        return (bool) $affected;
    }

    public function spendCredits(int $amount): bool
    {
        if ($amount <= 0 || $this->credits < $amount) return false;

        $affected = DB::table('user_balances')
            ->where('id', $this->id)
            ->where('credits', '>=', $amount)
            ->decrement('credits', $amount);

        if ($affected) $this->credits -= $amount;

        return (bool) $affected;
    }

    // ============================================
    // ХЕЛПЕРЫ ДЛЯ СУПЕРЛАЙКОВ
    // ============================================

    public function spendSuperlike(): bool
    {
        if ($this->superlikes_remaining <= 0) return false;

        $affected = DB::table('user_balances')
            ->where('id', $this->id)
            ->where('superlikes_remaining', '>', 0)
            ->decrement('superlikes_remaining');

        if ($affected) $this->superlikes_remaining -= 1;

        return (bool) $affected;
    }

    public function resetSuperlikes(): void
    {
        $limit = 1; 

        if ($this->user && $this->user->hasActivePremium) {
            $limit = 5; 
        }

        $this->update([
            'superlikes_remaining' => $limit,
            'superlikes_reset_at' => now()->addDay(),
        ]);
    }
}