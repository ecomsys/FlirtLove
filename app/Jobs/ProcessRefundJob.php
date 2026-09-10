<?php

namespace App\Jobs;

use App\Events\TransactionRefunded;
use App\Models\Transaction;
use App\Notifications\RefundProcessed;
use App\Notifications\PaymentFailed;
use App\Services\Payments\MockAcquiringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessRefundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $transactionId
    ) {}

    public function handle(MockAcquiringService $bank): void
    {
        DB::transaction(function () use ($bank) {
            
            $transaction = Transaction::lockForUpdate()->find($this->transactionId);

            if (!$transaction || $transaction->status !== 'success') {
                Log::warning("RefundJob: Транзакция #{$this->transactionId} не найдена, уже возвращена или не успешна.");
                return;
            }

            // Подгружаем юзера, если вдруг не загружен, для уведомлений
            if (!$transaction->relationLoaded('user')) {
                $transaction->load('user');
            }

            $bankResponse = $bank->refund($transaction);

            if (!$bankResponse['success']) {
                $transaction->update([
                    'meta' => array_merge($transaction->meta ?? [], [
                        'refund_error' => $bankResponse['message'],
                        'raw_error' => $bankResponse['raw_response']
                    ])
                ]);
                
                // ФИКС: Оповещаем юзера, что банк отклонил возврат
                if ($transaction->user) {
                    $transaction->user->notify(new PaymentFailed($transaction->id, (float)$transaction->amount, 'Банк отклонил возврат: ' . $bankResponse['message']));
                }

                Log::error("RefundJob: Банк отклонил возврат #{$transaction->id}. Причина: {$bankResponse['message']}");
                return;
            }

            $metaData = [
                'bank_response' => $bankResponse['raw_response'],
                'bank_refund_id' => $bankResponse['provider_refund_id'],
            ];
            
            // Модель Transaction сама вызовет событие TransactionRefunded внутри markAsRefunded
            $transaction->markAsRefunded($metaData);

            // ФИКС: Отправляем уведомление об успешном возврате!
            if ($transaction->user) {
                $reason = $transaction->meta['refund_reason'] ?? 'По решению администрации';
                $transaction->user->notify(new RefundProcessed($transaction->id, (float)$transaction->amount, $reason));
            }

            Log::info("RefundJob: Возврат #{$transaction->id} успешно обработан банком.");
        });
    }
}