<?php

namespace App\Listeners;

use App\Events\TransactionRefunded;
use App\Models\UserSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class RevokePurchasedItems implements ShouldQueue
{
    /**
     * Отбираем у юзера то, что он купил (VIP или кредиты).
     */
    public function handle(TransactionRefunded $event): void
    {
        $transaction = $event->transaction;
        $user = $transaction->user;

        if (!$user) {
            return;
        }

        // ============================================
        // 1. ОТКАТ ПОДПИСКИ (VIP)
        // ============================================
        if ($transaction->type === 'subscription') {
            
            // Ищем подписку, которую оплатила эта транзакция
            $subscription = UserSubscription::where('transaction_id', $transaction->id)->first();

            if ($subscription) {
                // Аннулируем подписку (срок заканчивается прямо сейчас)
                $subscription->update([
                    'status' => 'canceled',
                    'ends_at' => now(), // Резко обрываем срок
                    'canceled_at' => now(),
                    'is_auto_renew' => false,
                ]);
                Log::info("Подписка #{$subscription->id} аннулирована из-за возврата по транзакции #{$transaction->id}");
            }

            // ПЕРЕСЧЕТ КЭША (is_premium в таблице users)
            // Проверяем, есть ли у юзера ДРУГИЕ активные подписки (вдруг он купил два тарифа подряд)
            $hasOtherActiveSubs = UserSubscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->where('ends_at', '>', now())
                ->exists();

            if (!$hasOtherActiveSubs) {
                // Если других активных подписок нет — сносим VIP-статус полностью
                $user->update([
                    'is_premium' => false,
                    'premium_expires_at' => null,
                ]);
                Log::info("VIP-статус полностью снят с юзера ID {$user->id}");
            } else {
                // Если другая подписка есть, обновляем дату окончания VIP на её срок
                $latestSub = UserSubscription::where('user_id', $user->id)
                    ->where('status', 'active')
                    ->where('ends_at', '>', now())
                    ->latest('ends_at')
                    ->first();
                    
                $user->update([
                    'is_premium' => true,
                    'premium_expires_at' => $latestSub->ends_at,
                ]);
                Log::info("Срок VIP юзера ID {$user->id} скорректирован. Действует другая подписка до {$latestSub->ends_at}");
            }
        } 
        
        // ============================================
        // 2. ОТКАТ КРЕДИТОВ
        // ============================================
        elseif ($transaction->type === 'credits' && $transaction->credits_amount) {
            $pref = $user->preferences;
            if ($pref) {
                // Не даем балансу уйти в минус
                $pref->where('credits', '>=', $transaction->credits_amount)
                     ->decrement('credits', $transaction->credits_amount);
                Log::info("Списано {$transaction->credits_amount} кредитов у юзера ID {$user->id} по транзакции #{$transaction->id}");
            }
        }
    }
}