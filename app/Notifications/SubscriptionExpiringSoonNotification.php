<?php

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

    public function via($notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->push_enabled) {
            $channels[] = 'broadcast';
        }

        if ($notifiable->email_enabled && ($notifiable->email_settings['on_event'] ?? true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        $planName = $this->subscription->plan?->name ?? 'Подписка';
        $price = $this->subscription->plan?->price ? number_format($this->subscription->plan->price, 2) . ' ' . $this->subscription->plan->currency : '';

        $mail = (new MailMessage)
            ->subject('Ваша подписка скоро истекает ⏳')
            ->greeting("Здравствуйте, {$notifiable->name}!")
            ->line("Срок действия вашей подписки «{$planName}» истекает {$this->subscription->ends_at->format('d.m.Y H:i')}.");

        if ($this->subscription->is_auto_renew) {
            $mail->line("Автопродление включено. В ближайшее время с вашего счета будет списано {$price} для продления подписки.")
                 ->action('Управление подпиской', url('/settings/subscriptions'));
        } else {
            $mail->line("Автопродление отключено. Чтобы не потерять привилегии (безлимит лайков, приоритет в выдаче и др.), продлите подписку.")
                 ->action('Продлить подписку', url('/pricing'));
        }

        return $mail;
    }

    public function toDatabase($notifiable): array
    {
        $planName = $this->subscription->plan?->name ?? 'Подписка';
        $endDate = $this->subscription->ends_at->format('d.m.y');

        if ($this->subscription->is_auto_renew) {
            $message = "Подписка «{$planName}» истекает {$endDate}. Списание произойдет автоматически.";
            $actionUrl = url('/settings/subscriptions');
        } else {
            $message = "Подписка «{$planName}» истекает {$endDate}. Не забудьте продлить!";
            $actionUrl = url('/pricing');
        }

        return [
            'type' => 'sub_expiring',
            'title' => '⏳ Подписка истекает',
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

    public function toBroadcast($notifiable): BroadcastMessage
    {
        $dbData = $this->toDatabase($notifiable);

        return new BroadcastMessage(array_merge($dbData, [
            'timestamp' => now()->toDateTimeString(),
        ]));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Не удалось отправить SubscriptionExpiringSoonNotification (Sub ID: {$this->subscription->id}): " . $exception->getMessage());
    }
}