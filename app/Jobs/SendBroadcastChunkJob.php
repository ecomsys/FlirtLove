<?php

namespace App\Jobs;

use App\Models\Broadcast;
use App\Models\User;
use App\Notifications\BroadcastNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendBroadcastChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120; // 2 минут хватит для 100 уведомлений

    public function __construct(
        public int $broadcastId,
        public array $userIds
    ) {
        $this->onQueue('broadcasts');
    }

    public function handle(): void
    {
        $broadcast = Broadcast::find($this->broadcastId);
        if (!$broadcast) return;

        $users = User::whereIn('id', $this->userIds)->get();

        foreach ($users as $user) {
            try {
                // notifyNow отправляет синхронно, но так как юзеров мало (100), это безопасно
                $user->notifyNow(new BroadcastNotification($broadcast));
                $broadcast->incrementSent();
            } catch (\Exception $e) {
                Log::error("Ошибка отправки юзеру в чанке", [
                    'broadcast_id' => $broadcast->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
                $broadcast->incrementFailed();
            }
        }

        // ПРОВЕРКА ЗАВЕРШЕНИЯ РАССЫЛКИ
        // Делаем refresh, чтобы получить свежие счетчики из БД от других параллельных джобов
        $broadcast->refresh();
        
        if ($broadcast->sent_count + $broadcast->failed_count >= $broadcast->total_recipients) {
            // Атомарно меняем статус на 'sent', только если он еще 'sending'
            // Это гарантирует, что markAsSent вызовется ровно 1 раз последним завершившимся джобом
            Broadcast::where('id', $broadcast->id)
                ->where('status', 'sending')
                ->update(['status' => 'sent', 'sent_at' => now()]);
            
            Log::info("Рассылка #{$broadcast->id} успешно завершена.", [
                'sent' => $broadcast->sent_count,
                'failed' => $broadcast->failed_count
            ]);
        }
    }
}