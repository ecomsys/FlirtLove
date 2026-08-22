<?php

namespace App\Listeners;

use App\Events\TransactionRefunded;
use App\Models\UserSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class RevokePurchasedItems implements ShouldQueue
{
    /**
     * Отбираем у юзера то, что он купил (Premium, VIP или кредиты).
     */
    public function handle(TransactionRefunded $event): void
    {
        $transaction = $event->transaction;
        $user = $transaction->user;

        if (!$user) {
            return;
        }

        // ============================================
        // 1. ОТКАТ ПОДПИСКИ (Premium или VIP)
        // ============================================
        if ($transaction->type === 'subscription') {
            
            // Ищем подписку, которую оплатила эта транзакция
            $subscription = UserSubscription::where('transaction_id', $transaction->id)->first();

            if ($subscription) {
                // Аннулируем подписку
                $subscription->update([
                    'status' => 'canceled',
                    'ends_at' => now(), // Резко обрываем срок
                    'canceled_at' => now(),
                    'is_auto_renew' => false,
                ]);
                Log::info("Подписка #{$subscription->id} ({$subscription->tier}) аннулирована из-за возврата #{$transaction->id}");
            }

            // ПЕРЕСЧЕТ КЭША в таблице users
            // Проверяем, есть ли у юзера ДРУГИЕ активные подписки ЭТОГО ЖЕ ТИПА
            $tier = $subscription->tier ?? 'premium';
            
            $hasOtherActiveSubs = UserSubscription::where('user_id', $user->id)
                ->where('tier', $tier)
                ->where('status', 'active')
                ->where('ends_at', '>', now())
                ->exists();

            if (!$hasOtherActiveSubs) {
                // Если других подписок нет — сносим статус полностью
                if ($tier === 'premium') {
                    $user->update(['is_premium' => false, 'premium_expires_at' => null]);
                } elseif ($tier === 'vip') {
                    $user->update(['is_vip' => false, 'vip_expires_at' => null]);
                }
                Log::info("Статус {$tier} полностью снят с юзера ID {$user->id}");
            } else {
                // Если другая подписка есть, обновляем дату окончания на её срок
                $latestSub = UserSubscription::where('user_id', $user->id)
                    ->where('tier', $tier)
                    ->where('status', 'active')
                    ->where('ends_at', '>', now())
                    ->latest('ends_at')
                    ->first();
                    
                if ($latestSub) {
                    if ($tier === 'premium') {
                        $user->update(['is_premium' => true, 'premium_expires_at' => $latestSub->ends_at]);
                    } elseif ($tier === 'vip') {
                        $user->update(['is_vip' => true, 'vip_expires_at' => $latestSub->ends_at]);
                    }
                    Log::info("Срок {$tier} юзера ID {$user->id} скорректирован. Действует другая подписка до {$latestSub->ends_at}");
                }
            }
        } 
        
        // ============================================
        // 2. ОТКАТ КРЕДИТОВ
        // ============================================
        elseif ($transaction->type === 'credits' && $transaction->credits_amount) {
            $balance = $user->balance;
            if ($balance) {
                // Используем decrement, чтобы списать ровно ту сумму, что была начислена.
                // Если юзер их уже потратил, баланс уйдет в минус (это норма для фин. систем, долг придется покрывать)
                $balance->decrement('credits', $transaction->credits_amount);
                Log::info("Списано {$transaction->credits_amount} кредитов у юзера ID {$user->id} по возврату #{$transaction->id}");
            }
        }
    }
}