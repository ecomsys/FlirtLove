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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendBroadcastChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120; 

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

        // ФИКС: Eager Load preferences, чтобы не было N+1 при проверке email_settings в уведомлении
        $users = User::with('preferences')->whereIn('id', $this->userIds)->get();

        $successCount = 0;
        $failCount = 0;

        foreach ($users as $user) {
            try {
                // ФИКС: Вызов с новыми скалярами
                $user->notifyNow(new BroadcastNotification(
                    broadcastId: $broadcast->id,
                    type: $broadcast->type,
                    title: $broadcast->title,
                    message: $broadcast->message,
                    emailBody: $broadcast->email_body,
                    actionUrl: $broadcast->data['action_url'] ?? null
                ));
                
                $successCount++;
            } catch (\Exception $e) {
                $failCount++;
                Log::error("Ошибка отправки юзеру в чанке", [
                    'broadcast_id' => $broadcast->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // ФИКС: 2 Bulk-запроса вместо 200 UPDATE в цикле!
        if ($successCount > 0) {
            DB::table('broadcasts')->where('id', $broadcast->id)->increment('sent_count', $successCount);
        }
        if ($failCount > 0) {
            DB::table('broadcasts')->where('id', $broadcast->id)->increment('failed_count', $failCount);
        }

        // ФИКС: Проверка завершения через легкий SELECT без загрузки модели
        $stats = DB::table('broadcasts')->where('id', $this->broadcastId)
            ->select(['sent_count', 'failed_count', 'total_recipients', 'status'])
            ->first();
        
        if ($stats && $stats->status === 'sending' && $stats->total_recipients > 0) {
            if ($stats->sent_count + $stats->failed_count >= $stats->total_recipients) {
                // Атомарно меняем статус на 'sent', только если он еще 'sending'
                DB::table('broadcasts')
                    ->where('id', $this->broadcastId)
                    ->where('status', 'sending')
                    ->update(['status' => 'sent', 'sent_at' => now()]);
                
                Log::info("Рассылка #{$this->broadcastId} успешно завершена.", [
                    'sent' => $stats->sent_count,
                    'failed' => $stats->failed_count
                ]);
            }
        }
    }
}