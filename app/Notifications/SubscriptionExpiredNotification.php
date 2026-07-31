<?php 

// Уведомление об истечении подписки — это последний шанс вернуть юзера в платную категорию.

// Здесь мы давим на упущенную возможность (FOMO — Fear of Missing Out). Текст должен четко давать понять, что фичи 
// больше не работают, и предлагать кнопку для немедленного продления.

// Я передал в конструктор только planName (название тарифа). Это сделано для того, чтобы крон-задаче не приходилось 
// делать лишних запросов к БД (загружать всю модель подписки и тарифа), когда она массово рассылает эти уведомления 
// тысячам юзеров, у которых истек VIP.

// Разбор архитектуры:

// Оптимизация крон-задачи: Крон раз в минуту ищет подписки со статусом overdue. Вместо того чтобы грузить 
// связи ($sub->plan->name), мы в самом кроне достаем имя тарифа одним запросом и передаем строку сюда, в конструктор. 
// Это экономит ресурсы сервера при массовых рассылках.
// Агрессивный Call-to-Action: И в письме, и в колокольчике мы четко указываем потерю фич ("лишились премиум-возможностей") 
// и ведем по ссылке url('/pricing'), чтобы юзер в один клик мог оплатить VIP заново.

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Support\Facades\Log;

class SubscriptionExpiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected ?string $planName = 'VIP'
    ) {}

    /**
     *  Каналы доставки с учетом глобальных тумблеров и категорий
     */
    public function via($notifiable): array
    {
        $channels = ['database']; // В базу (колокольчик) пишем ВСЕГДА

        // Проверяем глобальный тумблер Push
        if ($notifiable->push_enabled) {
            $channels[] = 'broadcast';
        }

        // Проверяем глобальный тумблер Email И категорию "Новые события" (on_event)
        if ($notifiable->email_enabled && ($notifiable->email_settings['on_event'] ?? true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     *  Отправка Email
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Ваш VIP-статус закончился ❌')
            ->greeting("Здравствуйте, {$notifiable->name}!")
            ->line("Срок действия вашей подписки «{$this->planName}» истек.")
            ->line('Вы лишились премиум-возможностей: невидимого режима, неограниченных лайков и других привилегий.')
            ->action('Вернуть VIP-статус', url('/pricing'));
    }

    /**
     *  Запись в БД (Колокольчик)
     */
    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'sub_expired',
            'title' => '❌ VIP-статус закончился',
            'message' => "Подписка «{$this->planName}» истекла. Продлите, чтобы вернуть премиум-возможности!",
            'action_url' => url('/pricing'), // Сразу ведем на кассу
            'data' => [
                'plan_name' => $this->planName,
            ]
        ];
    }

    /**
     *  Realtime push через WebSockets (DRY-подход)
     */
    public function toBroadcast($notifiable): BroadcastMessage
    {
        $dbData = $this->toDatabase($notifiable);

        return new BroadcastMessage(array_merge($dbData, [
            'timestamp' => now()->toDateTimeString(),
        ]));
    }

    /**
     * ЗАЩИТА ОЧЕРЕДИ
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Не удалось отправить SubscriptionExpiredNotification (Plan: {$this->planName}): " . $exception->getMessage());
    }
}