<?php

// Безопасность памяти (chunkById(1000, ...)): Теперь, даже если рассылка идет 1 миллиону юзеров, 
// из БД будут выбираться по 1000 человек за раз. Память будет очищаться на каждой итерации.
// Сегментация (target_audience): Теперь команда читает JSON с фильтрами, которые админ настроил в админке.
// Если админ выбрал "только мужчин из Москвы", target_audience будет {"gender": "male", "city": "Москва"}, 
// и скрипт применит эти условия через whereHas('profile').
// Защита от двойного запуска: Перед началом цикла вызывается $broadcast->markAsSending($totalRecipients). 
// Это меняет статус на sending. Если крон запустится через минуту (пока идет отправка), скоуп dueForDispatch() не найдет 
// эту рассылку (так как она уже не scheduled).
// Точечная статистика: Метод incrementSent() вызывается после каждой 1000 юзеров. Если ты зайдешь в админку во время 
// рассылки, ты увидишь живой прогресс-бар (благодаря нашему аксессору getProgressAttribute).

namespace App\Console\Commands;

use App\Models\Broadcast;
use App\Models\User;
use App\Notifications\BroadcastNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendScheduledBroadcasts extends Command
{
    protected $signature = 'broadcasts:send-scheduled';
    protected $description = 'Отправляет запланированные оповещения (рассылки)';

    public function handle(): int
    {
        // 1. Получаем рассылки, время которых пришло. Используем наш скоуп!
        $broadcasts = Broadcast::dueForDispatch()->get();

        if ($broadcasts->isEmpty()) {
            $this->info('Нет запланированных оповещений для отправки.');
            return 0;
        }

        foreach ($broadcasts as $broadcast) {
            try {
                $this->processBroadcast($broadcast);
            } catch (\Exception $e) {
                $this->error("Критическая ошибка при отправке #{$broadcast->id}: " . $e->getMessage());
                Log::error("Сбой отправки оповещения #{$broadcast->id}: " . $e->getMessage());
                $broadcast->markAsFailed();
            }
        }

        return 0;
    }

    /**
     * Обработка одной рассылки
     */
    private function processBroadcast(Broadcast $broadcast): void
    {
        // 2. Формируем базовый запрос к активным юзерам (is_banned больше нет, есть status)
        $query = User::query()->where('status', 'active');

        // 3. Применяем фильтры сегментации из JSON target_audience
        $filters = $broadcast->target_audience ?? [];
        
        if (!empty($filters['gender']) && $filters['gender'] !== 'any') {
            $query->whereHas('profile', fn($q) => $q->where('gender', $filters['gender']));
        }
        if (!empty($filters['city'])) {
            $query->whereHas('profile', fn($q) => $q->where('city', $filters['city']));
        }
        if (isset($filters['is_premium'])) {
            $query->where('is_premium', $filters['is_premium']);
        }

        // 4. Подсчитываем общее количество получателей ДО отправки
        $totalRecipients = $query->count();
        if ($totalRecipients === 0) {
            $this->info("Оповещение #{$broadcast->id}: нет подходящих получателей.");
            $broadcast->markAsSent(); // Закрываем как отправленное (некому)
            return;
        }

        // 5. Блокируем рассылку от повторного запуска кроном
        $broadcast->markAsSending($totalRecipients);
        $this->info("Оповещение #{$broadcast->id} запущено. Получателей: {$totalRecipients}.");

        // 6. Отправляем чанками по 1000 юзеров (чтобы не съесть всю память)
        $query->chunkById(1000, function ($users) use ($broadcast) {
            // Notification::send автоматически распределит задачу в очередь (Queue), 
            // если в BroadcastNotification реализован интерфейс ShouldQueue.
            Notification::send($users, new BroadcastNotification($broadcast));
            
            // Обновляем счетчик успешно отправленных
            $broadcast->incrementSent();
        });

        // 7. Завершаем рассылку
        $broadcast->markAsSent();
        $this->info("Оповещение #{$broadcast->id} успешно завершено.");
    }
}