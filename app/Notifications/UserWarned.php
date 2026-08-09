<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class UserWarned extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $reason;

    public function __construct(string $reason)
    {
        $this->reason = $reason;
    }

    public function via($notifiable): array
    {
        $channels = ['database'];
        if ($notifiable->email_enabled && ($notifiable->email_settings['on_event'] ?? true)) {
            $channels[] = 'mail';
        }
         // Если включены пуши (WebSockets)
        if ($notifiable->push_enabled) {
            $channels[] = 'broadcast';
        }
        return $channels;
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'moderation',
            'title' => 'Вам вынесено предупреждение',
            'message' => "Вы нарушили правила сервиса (Причина: {$this->reason}). Пожалуйста, будьте взаимовежливы. Повторное нарушение приведет к блокировке аккаунта.",
            'action_url' => url('/profile'),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Вам вынесено предупреждение')
            ->line('Модератор вынес вам предупреждение за нарушение правил сервиса.')
            ->line("Причина: {$this->reason}")
            ->line('Пожалуйста, ознакомьтесь с правилами сайта. Повторные нарушения приведут к блокировке.');
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'type' => 'moderation',
            'title' => 'Вам вынесено предупреждение',
            'message' => "Модератор вынес вам предупреждение за нарушение правил сервиса.",
            'action_url' => url('/profile'),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}