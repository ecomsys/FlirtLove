<?php

//  Это уведомление — требование Apple App Store и Google Play. Если у тебя включено автопродление, ты обязан уведомить 
//  юзера за 24 часа до списания денег. Иначе можешь получить бан приложения в сторе или жалобы в поддержку.

// Здесь мы передаем модель UserSubscription. Важно, что текст уведомления должен меняться в зависимости от того, 
// включено ли автопродление (is_auto_renew). Если включено — мы предупреждаем: "с вашего счета спишется X руб.". 
// Если выключено — "у вас заканчивается VIP".

// Разбор архитектуры (Комплаенс сторов):

// Цена в письме: Если автопродление включено, мы обязаны указать точную сумму и валюту, которая будет списана. 
// Мы берем их из связи $this->subscription->plan.
// Разные Call-to-Action: Если автопродление включено, кнопка в письме ведет на /settings/subscriptions (чтобы юзер 
// мог его отключить, если передумал — это его право). Если автопродление выключено, кнопка ведет на /pricing, чтобы 
// соблазнить его купить заново.
// Null-safe план (plan?->name): Если админ удалил тариф из каталога, мы не падаем с ошибкой, а используем дефолтное
//  имя "VIP".

namespace App\Notifications;

use App\Models\UserSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Support\Facades\Log;

class SubscriptionExpiringSoonNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected UserSubscription $subscription
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
        $planName = $this->subscription->plan?->name ?? 'VIP';
        $price = $this->subscription->plan?->price ? number_format($this->subscription->plan->price, 2) . ' ' . $this->subscription->plan->currency : '';

        $mail = (new MailMessage)
            ->subject('Ваш VIP-статус скоро истекает ⏳')
            ->greeting("Здравствуйте, {$notifiable->name}!")
            ->line("Срок действия вашей подписки «{$planName}» истекает {$this->subscription->ends_at->format('d.m.Y H:i')}.");

        if ($this->subscription->is_auto_renew) {
            // Требование Apple/Google: четкое уведомление о списании
            $mail->line("Автопродление включено. В ближайшее время с вашего счета будет списано {$price} для продления подписки.")
                 ->action('Управление подпиской', url('/settings/subscriptions'));
        } else {
            $mail->line("Автопродление отключено. Чтобы не потерять премиум-возможности (невидимка, лимиты лайков и др.), продлите подписку.")
                 ->action('Продлить VIP', url('/pricing'));
        }

        return $mail;
    }

    /**
     *  Запись в БД (Колокольчик)
     */
    public function toDatabase($notifiable): array
    {
        $planName = $this->subscription->plan?->name ?? 'VIP';
        $endDate = $this->subscription->ends_at->format('d.m.y');

        if ($this->subscription->is_auto_renew) {
            $message = "Подписка «{$planName}» истекает {$endDate}. Списание произойдет автоматически.";
            $actionUrl = url('/settings/subscriptions');
        } else {
            $message = "Подписка «{$planName}» истекает {$endDate}. Не забудьте продлить VIP!";
            $actionUrl = url('/pricing');
        }

        return [
            'type' => 'sub_expiring',
            'title' => '⏳ VIP истекает',
            'message' => $message,
            'action_url' => $actionUrl,
            'data' => [
                'subscription_id' => $this->subscription->id,
                'plan_name' => $planName,
                'ends_at' => $this->subscription->ends_at->toDateTimeString(),
                'is_auto_renew' => $this->subscription->is_auto_renew,
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
        Log::error("Не удалось отправить SubscriptionExpiringSoonNotification (Sub ID: {$this->subscription->id}): " . $exception->getMessage());
    }
}