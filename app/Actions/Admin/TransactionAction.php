<?php

namespace App\Actions\Admin;

use App\Enums\RefundReason;
use App\Jobs\ProcessRefundJob;
use App\Models\AdminLog;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\UserSubscription;
use App\Notifications\PaymentSuccessful;
use App\Notifications\PaymentFailed;
use App\Services\Payments\MockAcquiringService;
use Illuminate\Support\Facades\Auth;

class TransactionAction
{
    public function syncWithBank(Transaction $transaction, MockAcquiringService $bank): array
    {
        if (!$transaction->relationLoaded('user')) {
            $transaction->load('user');
        }

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
                        $user = $transaction->user;
                        
                        $expiresField = $plan->tier === 'premium' ? 'premium_expires_at' : 'vip_expires_at';
                        $currentExpiry = $user->{$expiresField};
                        
                        $startFrom = ($currentExpiry && $currentExpiry->isFuture()) ? $currentExpiry : now();
                        $endsAt = $startFrom->copy()->addDays($plan->duration_days);
                        
                        UserSubscription::create([
                            'user_id' => $user->id,
                            'plan_id' => $plan->id,
                            'transaction_id' => $transaction->id,
                            'tier' => $plan->tier,
                            'starts_at' => now(),
                            'ends_at' => $endsAt,
                            'status' => 'active',
                        ]);

                        $user->update([$expiresField => $endsAt]);
                    }
                }

                // ОТПРАВКА УВЕДОМЛЕНИЯ: Успешный платеж
                $transaction->user->notify(new PaymentSuccessful($transaction->id, (float)$transaction->amount, $transaction->type));
            }

            $before = ['status' => $transaction->getOriginal('status')];
            $after = [
                'status' => 'success', 
                'synced_by' => Auth::id(),
                'context' => [
                    'transaction_id' => $transaction->id,
                    'user_id' => $transaction->user_id,
                    'amount' => $transaction->amount,
                    'type' => $transaction->type,
                    'provider_id' => $bankResponse['provider_transaction_id'] ?? null
                ]
            ];

            AdminLog::record('transaction.sync_success', $transaction, Auth::user(), $before, $after, participants: [$transaction->user_id]);
            
            return ['success' => true, 'message' => 'Синхронизация успешна! Платеж подтвержден.'];
        }

        $transaction->markAsFailed($bankResponse['message']);
        
        // ОТПРАВКА УВЕДОМЛЕНИЯ: Ошибка платежа
        if ($transaction->user) {
            $transaction->user->notify(new PaymentFailed($transaction->id, (float)$transaction->amount, $bankResponse['message']));
        }

        $before = ['status' => $transaction->getOriginal('status')];
        $after = [
            'status' => 'failed', 
            'bank_message' => $bankResponse['message'],
            'context' => [
                'transaction_id' => $transaction->id,
                'user_id' => $transaction->user_id,
                'amount' => $transaction->amount,
                'type' => $transaction->type
            ]
        ];

        AdminLog::record('transaction.sync_failed', $transaction, Auth::user(), $before, $after, participants: [$transaction->user_id]);
        
        return ['success' => false, 'message' => 'Банк отклонил платеж: ' . $bankResponse['message']];
    }

    public function processRefund(Transaction $transaction, RefundReason $reason, ?string $comment = null): void
    {
        $before = ['status' => $transaction->getOriginal('status')];

        $transaction->update([
            'meta' => array_merge($transaction->meta ?? [], [
                'refund_reason' => $reason->label(),
                'refund_comment' => $comment,
                'refund_initiated_by' => Auth::user()->name,
            ])
        ]);

        $after = [
            'status' => 'pending_refund', 
            'reason' => $reason->label(),
            'comment' => $comment,
            'context' => [
                'transaction_id' => $transaction->id,
                'user_id' => $transaction->user_id,
                'amount' => $transaction->amount,
                'type' => $transaction->type,
                'initiated_by' => Auth::id()
            ]
        ];

        AdminLog::record('transaction.refund', $transaction, Auth::user(), $before, $after, participants: [$transaction->user_id]);

        // Уведомление отсюда убрано! Оно вызовется в ProcessRefundJob, когда банк подтвердит возврат.
        ProcessRefundJob::dispatch($transaction->id);
    }
}