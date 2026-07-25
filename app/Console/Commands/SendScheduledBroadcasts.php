<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Broadcast;
use App\Notifications\BroadcastNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

// Запусти обаботчик очередей 
// php artisan schedule:work

// Настроить cron на сервере
// На продакшене (или локальном сервере с Linux) нужно добавить cron-задачу:

// bash
// * * * * * cd /путь-к-проекту && php artisan schedule:run >> /dev/null 2>&1

#[Signature('broadcasts:send-scheduled')]
#[Description('Отправляет запланированные оповещения')]
class SendScheduledBroadcasts extends Command
{
public function handle()
{
    $now = now();

    $broadcasts = Broadcast::where('status', 'scheduled')
        ->where('scheduled_at', '<=', $now)
        ->get();

    if ($broadcasts->isEmpty()) {
        $this->info('Нет запланированных оповещений для отправки.');
        return 0;
    }

    $sentCount = 0;

    foreach ($broadcasts as $broadcast) {
        try {
            // 1. Сначала отправляем
            $this->sendBroadcastToUsers($broadcast);

            // 2. Только если успешно, меняем статус
            $broadcast->update([
                'status' => 'sent',
                'sent_at' => $now,
            ]);

            $sentCount++;
            $this->info("Оповещение #{$broadcast->id} успешно отправлено.");
        } catch (\Exception $e) {
            $this->error("Ошибка при отправке #{$broadcast->id}: " . $e->getMessage());
            Log::error("Сбой отправки запланированного оповещения #{$broadcast->id}: " . $e->getMessage());
        }
    }

    $this->info("Отправлено {$sentCount} запланированных оповещений.");
    return 0;
}

    /**
     * Отправка оповещения пользователям (копия sendRealBroadcast)
     */
    private function sendBroadcastToUsers($broadcast): void
    {
        $users = $broadcast->user_id === null 
            ? User::where('is_banned', false)->get(['id', 'name', 'email'])
            : User::where('id', $broadcast->user_id)->get(['id', 'name', 'email']);

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new BroadcastNotification($broadcast));

        Log::info('Запланированное оповещение отправлено', [
            'broadcast_id' => $broadcast->id,
            'type' => $broadcast->type,
            'sent_count' => $users->count(),
        ]);
    }
}
