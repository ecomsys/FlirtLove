<?php

namespace App\Actions\Admin;

use App\Enums\RefundReason;
use App\Jobs\ProcessRefundJob;
use App\Models\AdminLog;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\UserSubscription;
use App\Services\Payments\MockAcquiringService;
use Illuminate\Support\Facades\Auth;

class TransactionAction
{
    /**
     * Ручная синхронизация статуса платежа с банком (для pending транзакций).
     */
    public function syncWithBank(Transaction $transaction, MockAcquiringService $bank): array
    {
        $bankResponse = $bank->checkStatus($transaction);

        if ($bankResponse['status'] === 'success') {
            $transaction->markAsSuccess([
                'synced_by' => Auth::user()->name,
                'bank_message' => $bankResponse['message'],
                'provider_transaction_id' => $bankResponse['provider_transaction_id']
            ]);

            if ($transaction->user) {
                if ($transaction->type === 'credits' && $transaction->credits_amount) {
                    $balance = $transaction->user->balance()->firstOrCreate([]);
                    $balance->addCredits($transaction->credits_amount);
                } 
                elseif ($transaction->type === 'subscription' && isset($transaction->meta['plan_id'])) {
                    $plan = SubscriptionPlan::find($transaction->meta['plan_id']);
                    if ($plan) {
                        $endsAt = now()->addDays($plan->duration_days);
                        
                        UserSubscription::create([
                            'user_id' => $transaction->user->id,
                            'plan_id' => $plan->id,
                            'transaction_id' => $transaction->id,
                            'tier' => $plan->tier,
                            'starts_at' => now(),
                            'ends_at' => $endsAt,
                            'status' => 'active',
                        ]);

                        if ($plan->tier === 'premium') {
                            $transaction->user->update(['is_premium' => true, 'premium_expires_at' => $endsAt]);
                        } elseif ($plan->tier === 'vip') {
                            $transaction->user->update(['is_vip' => true, 'vip_expires_at' => $endsAt]);
                        }
                    }
                }
            }

            AdminLog::record('transaction.sync_success', $transaction, Auth::user(), ['status' => 'pending'], ['status' => 'success']);
            
            return ['success' => true, 'message' => 'Синхронизация успешна! Платеж подтвержден.'];
        }

        $transaction->markAsFailed($bankResponse['message']);
        AdminLog::record('transaction.sync_failed', $transaction, Auth::user(), ['status' => 'pending'], ['status' => 'failed']);
        
        return ['success' => false, 'message' => 'Банк отклонил платеж: ' . $bankResponse['message']];
    }

    /**
     * Обработка возврата (Refund) - отправка в очередь.
     */
    public function processRefund(Transaction $transaction, RefundReason $reason, ?string $comment = null): void
    {
        $transaction->update([
            'meta' => array_merge($transaction->meta ?? [], [
                'refund_reason' => $reason->label(),
                'refund_comment' => $comment,
                'refund_initiated_by' => Auth::user()->name,
            ])
        ]);

        AdminLog::record('transaction.refund', $transaction, Auth::user(), ['status' => 'success'], ['status' => 'pending_refund', 'reason' => $reason->label()]);

        ProcessRefundJob::dispatch($transaction->id);
    }
}