<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Models\UserSubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPaymentGateway implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
  

    public function __construct(public int $transactionId) {}

    public function handle(): void
    {
        $transaction = Transaction::find($this->transactionId);
        if (!$transaction || $transaction->status !== Transaction::STATUS_PENDING) return;

        // Симулируем успешный ответ от банка (с вероятностью 90%)
        $isSuccess = rand(1, 10) > 1;

        if ($isSuccess) {
            $transaction->markAsSuccess();
            
            $plan = SubscriptionPlan::find($transaction->meta['plan_id'] ?? null);
            if ($plan) {
                $startsAt = now();
                $endsAt = $startsAt->copy()->addDays($plan->duration_days);

                UserSubscription::create([
                    'user_id' => $transaction->user_id,
                    'plan_id' => $plan->id,
                    'transaction_id' => $transaction->id,
                    'tier' => $plan->tier,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'is_auto_renew' => $transaction->meta['auto_renew'] ?? false,
                    'status' => UserSubscription::STATUS_ACTIVE,
                ]);

                $user = $transaction->user;
                if ($plan->tier === SubscriptionPlan::TIER_PREMIUM) {
                    $user->premium_expires_at = $endsAt;
                    $user->save();
                } elseif ($plan->tier === SubscriptionPlan::TIER_VIP) {
                    $user->vip_expires_at = $endsAt;
                    $user->save();
                }
            }
        } else {
            $transaction->markAsFailed("Ошибка: Недостаточно средств (Симуляция)");
        }
    }
}