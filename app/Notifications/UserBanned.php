<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class UserBanned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected bool $isBanned
    ) {}

    public function via($notifiable): array
    {
        // Отправляем в БД, на почту и пушем (и при бане, и при разбане это важно)
        return ['database', 'mail', 'broadcast'];
    }

    public function toMail($notifiable): MailMessage
    {
        if ($this->isBanned) {
            return (new MailMessage)
                ->subject('Ваш аккаунт был заблокирован')
                ->greeting('Здравствуйте, ' . $notifiable->name . '!')
                ->line('Ваш аккаунт был заблокирован администрацией сайта.')
                ->line('Если вы считаете, что это ошибка, пожалуйста, свяжитесь с поддержкой.')
                ->action('Связаться с поддержкой', url('/support'));
        }

        return (new MailMessage)
            ->subject('Ваш аккаунт был разблокирован')
            ->greeting('Здравствуйте, ' . $notifiable->name . '!')
            ->line('Ваш аккаунт был успешно разблокирован.')
            ->line('Теперь вы снова можете пользоваться всеми функциями сайта.')
            ->action('Перейти на сайт', url('/'));
    }

    public function toDatabase($notifiable): array
    {
        if ($this->isBanned) {
            return [
                'type' => 'user_banned',
                'title' => '🔒 Аккаунт заблокирован',
                'message' => 'Ваш аккаунт был заблокирован администрацией. Обратитесь в поддержку.',
            ];
        }

        return [
            'type' => 'user_unbanned',
            'title' => '✅ Аккаунт разблокирован',
            'message' => 'Ваш аккаунт был успешно разблокирован. Добро пожаловать!',
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        if ($this->isBanned) {
            return new BroadcastMessage([
                'type' => 'user_banned',
                'title' => '🔒 Аккаунт заблокирован',
                'message' => 'Ваш аккаунт был заблокирован администрацией.',
                'timestamp' => now()->toDateTimeString(),
            ]);
        }

        return new BroadcastMessage([
            'type' => 'user_unbanned',
            'title' => '✅ Аккаунт разблокирован',
            'message' => 'Ваш аккаунт был успешно разблокирован.',
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}