<?php

namespace App\Observers;

use App\Models\UserSubscription;
use App\Models\User;
use App\Jobs\SendSubscribeExpiredNotification;
use Illuminate\Support\Facades\Log;

class UserSubscriptionObserver
{
    public function saved(UserSubscription $subscription): void
    {
        // Синхронизируем оба типа подписок независимо друг от друга
        $this->syncUserCache($subscription->user_id, 'premium');
        $this->syncUserCache($subscription->user_id, 'vip');
    }

    public function deleted(UserSubscription $subscription): void
    {
        $this->syncUserCache($subscription->user_id, 'premium');
        $this->syncUserCache($subscription->user_id, 'vip');
    }

    private function syncUserCache(int $userId, string $tier): void
    {
        // Ищем самую дальнюю активную подписку конкретного типа (tier)
        $activeSubscription = UserSubscription::where('user_id', $userId)
            ->where('tier', $tier)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->orderByDesc('ends_at')
            ->first();

        $isField = $tier === 'premium' ? 'is_premium' : 'is_vip';
        $expiresField = $tier === 'premium' ? 'premium_expires_at' : 'vip_expires_at';

        if ($activeSubscription) {
            // Подписка есть — обновляем кэш
            User::where('id', $userId)->update([
                $isField => true,
                $expiresField => $activeSubscription->ends_at,
            ]);
        } else {
            // Подписки нет. Проверяем, был ли статус активен до этого?
            $user = User::find($userId);
            
            if ($user && $user->{$isField}) {
                // Статус был активен, а теперь нет. Снимаем его
                User::where('id', $userId)->update([
                    $isField => false,
                    $expiresField => null,
                ]);

                // Отправляем уведомление (FOMO-пуш)
                $planName = ucfirst($tier); // "Premium" или "Vip"
                SendSubscribeExpiredNotification::dispatch($user, $planName, $tier);
                Log::info("Статус {$tier} снят с юзера ID {$userId}. Отправлено уведомление.");
            }
        }
    }
}