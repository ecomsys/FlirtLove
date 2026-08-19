<?php

namespace App\Observers;

use App\Models\UserSubscription;
use App\Models\User;
use App\Jobs\SendVipExpiredNotification;

class UserSubscriptionObserver
{
    public function saved(UserSubscription $subscription): void
    {
        $this->syncUserPremiumCache($subscription->user_id);
    }

    public function deleted(UserSubscription $subscription): void
    {
        $this->syncUserPremiumCache($subscription->user_id);
    }

    private function syncUserPremiumCache(int $userId): void
    {
        $activeSubscription = UserSubscription::where('user_id', $userId)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->orderByDesc('ends_at')
            ->first();

        if ($activeSubscription) {
            // VIP есть — обновляем кэш
            User::where('id', $userId)->update([
                'is_premium' => true,
                'premium_expires_at' => $activeSubscription->ends_at,
            ]);
        } else {
            // VIP нет. Проверяем, был ли он только что снят?
            // Используем direct update, чтобы избежать лишнего SELECT
            $affectedRows = User::where('id', $userId)
                ->where('is_premium', true) // Если был VIP
                ->update([
                    'is_premium' => false,
                    'premium_expires_at' => null,
                ]);

            // Если мы обновили строку (сняли VIP), значит юзер только что его потерял. Шлем FOMO-пуш
            if ($affectedRows > 0) {
                $user = User::find($userId);
                if ($user) {
                    // ИСПРАВЛЕНО: Вызываем правильную джобу для ИСТЕКШЕЙ подписки!
                    SendVipExpiredNotification::dispatch($user, 'VIP');
                }
            }
        }
    }
}