<?php

namespace App\Jobs;

use App\Events\TransactionRefunded;
use App\Models\AdminLog;
use App\Models\Transaction;
use App\Services\Payments\MockAcquiringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

// Создаем Job (Очередь) для возврата средств
// Джоба улетает в Redis/БД и делает тяжелую работу вне запроса пользователя.

class ProcessRefundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $transactionId
    ) {}

    public function handle(MockAcquiringService $bank): void
    {
        $transaction = Transaction::lockForUpdate()->find($this->transactionId);

        if (!$transaction || $transaction->status !== 'success') {
            Log::warning("RefundJob: Транзакция #{$this->transactionId} не найдена, уже возвращена или не успешна.");
            return;
        }

        // 1. Отправляем запрос в банк
        $bankResponse = $bank->refund($transaction);

        // 2. Если банк отклонил
        if (!$bankResponse['success']) {
            // Пишем ошибку в meta, статус НЕ меняем (остается success)
            $transaction->update([
                'meta' => array_merge($transaction->meta ?? [], [
                    'refund_error' => $bankResponse['message'],
                    'raw_error' => $bankResponse['raw_response']
                ])
            ]);
            Log::error("RefundJob: Банк отклонил возврат #{$transaction->id}. Причина: {$bankResponse['message']}");
            return; // Завершаем джобу
        }

        // 3. Банк одобрил! Меняем статус на refunded
        $metaData = [
            'bank_response' => $bankResponse['raw_response'],
            'bank_refund_id' => $bankResponse['provider_refund_id'],
        ];
        
        \Illuminate\Support\Facades\DB::transaction(function () use ($transaction, $metaData) {
            $transaction->markAsRefunded($metaData);
        });

        Log::info("RefundJob: Возврат #{$transaction->id} успешно обработан банком.");

        // 5. Запускаем событие для списания бонусов
        TransactionRefunded::dispatch($transaction->fresh());
    }
}