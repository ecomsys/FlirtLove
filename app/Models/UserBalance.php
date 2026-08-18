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
        'boosts_remaining', 'boosts_reset_at'
    ];

    protected $casts = [
        'credits' => 'integer',
        'superlikes_remaining' => 'integer',
        'superlikes_reset_at' => 'datetime',
        'boosts_remaining' => 'integer',
        'boosts_reset_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ============================================
    // ХЕЛПЕРЫ ДЛЯ ВАЛЮТЫ И ЛИМИТОВ
    // ============================================

    /**
     * Начислить кредиты (с защитой от гонки)
     */
    public function addCredits(int $amount): bool
    {
        if ($amount <= 0) return false;

        $affected = DB::table('user_balances')
            ->where('id', $this->id)
            ->increment('credits', $amount);

        if ($affected) {
            $this->credits += $amount; // Обновляем в памяти
        }

        return (bool) $affected;
    }

    /**
     * Списать кредиты (с защитой от гонки и ухода в минус)
     */
    public function spendCredits(int $amount): bool
    {
        if ($amount <= 0) return false;

        $affected = DB::table('user_balances')
            ->where('id', $this->id)
            ->where('credits', '>=', $amount)
            ->decrement('credits', $amount);

        if ($affected) {
            $this->credits -= $amount; // Обновляем в памяти
        }

        return (bool) $affected;
    }

    /**
     * Списать один суперлайк. Возвращает true, если лимит был, false - если закончился.
     */
    public function spendSuperlike(): bool
    {
        $affected = DB::table('user_balances')
            ->where('id', $this->id)
            ->where('superlikes_remaining', '>', 0)
            ->decrement('superlikes_remaining');

        if ($affected) {
            $this->superlikes_remaining -= 1;
        }

        return (bool) $affected;
    }

    /**
     * Сброс лимита суперлайков (вызывается кроном раз в сутки).
     * Лимит берется из активной подписки (features JSON).
     */
    public function resetSuperlikes(): void
    {
        $limit = 5; // Дефолт для бесплатных юзеров

        // Ищем активную подписку с жадной загрузкой тарифа
        $activeSub = $this->user?->subscriptions()
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->with('plan')
            ->first();

        // Если подписка есть и в тарифе прописан лимит суперлайков — берем его
        if ($activeSub && $activeSub->plan) {
            $planLimit = $activeSub->plan->getFeature('superlikes_per_day');
            if (!is_null($planLimit)) {
                $limit = (int) $planLimit;
            }
        }

        $this->update([
            'superlikes_remaining' => $limit,
            'superlikes_reset_at' => now()->addDay(),
        ]);
    }
}