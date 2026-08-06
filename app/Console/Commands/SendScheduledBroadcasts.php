<?php

namespace App\Console\Commands;

use App\Jobs\SendBroadcastJob;
use App\Models\Broadcast;
use App\Models\AdminLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

// запускаем 2 терминала обработки очередей чтобы действия на сайте не задерживали отправку уведомлений

// php artisan queue:work --queue=default
// php artisan queue:work --queue=broadcasts

class SendScheduledBroadcasts extends Command
{
    protected $signature = 'broadcasts:send-scheduled';
    protected $description = 'Отправляет запланированные оповещения (рассылки)';

       public function handle(): int
    {
        $broadcasts = Broadcast::dueForDispatch()->get();

        if ($broadcasts->isEmpty()) {
            $this->info('Нет запланированных оповещений для отправки.');
            return 0;
        }

        foreach ($broadcasts as $broadcast) {
            try {
                // Сохраняем состояние ДО (для диффа в логах)
                $before = $broadcast->only(['status', 'started_at']);

                $updated = Broadcast::where('id', $broadcast->id)
                    ->where('status', 'scheduled')
                    ->update([
                        'status' => 'sending', 
                        'started_at' => now()
                    ]);
                
                if ($updated) {
                    // Обновляем модель в памяти, чтобы получить точное время started_at
                    $broadcast->refresh();
                    $after = $broadcast->only(['status', 'started_at']);

                    // ПИШЕМ В ЖУРНАЛ! (Передаем null вместо админа, так как это система)
                    AdminLog::record('broadcast.send_scheduled', $broadcast, null, $before, $after);
                    Log::info("Крон запустил рассылку по расписанию", ['broadcast_id' => $broadcast->id]);

                    SendBroadcastJob::dispatch($broadcast->id, $broadcast->target_audience)->onQueue('broadcasts');
                    
                    $this->info("Оповещение #{$broadcast->id} передано в очередь на отправку.");
                } else {
                    $this->warn("Оповещение #{$broadcast->id} уже запущено или изменено, пропуск.");
                }
            } catch (\Exception $e) {
                $this->error("Критическая ошибка при запуске #{$broadcast->id}: " . $e->getMessage());
                Log::error("Сбой отправки оповещения #{$broadcast->id}: " . $e->getMessage());
                $broadcast->markAsFailed();
            }
        }

        return 0;
    }
}