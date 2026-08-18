<?php

namespace App\Observers;

use App\Models\UserSubscription;
use App\Models\User;

class UserSubscriptionObserver
{
    /**
     * При создании/обновлении подписки — синхронизируем кэш в таблице users
     */
    public function saved(UserSubscription $subscription): void
    {
        if ($subscription->status === 'active' && $subscription->ends_at->isFuture()) {
            User::where('id', $subscription->user_id)->update([
                'is_premium' => true,
                'premium_expires_at' => $subscription->ends_at,
            ]);
        } else {
            // Если подписка истекла, отменена или ошибка — снимаем VIP
            User::where('id', $subscription->user_id)->update([
                'is_premium' => false,
                'premium_expires_at' => null,
            ]);
        }
    }
}