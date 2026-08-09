<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class ProfileFieldCleared extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $field;

    public function __construct(string $field)
    {
        $this->field = $field;
    }

    public function via($notifiable): array
    {
        $channels = ['database']; // Пишем в колокольчик
        if ($notifiable->email_enabled && ($notifiable->email_settings['on_event'] ?? true)) {
            $channels[] = 'mail'; // Дублируем на почту, если юзер включил уведомления о событиях
        }
         // Если включены пуши (WebSockets)
        if ($notifiable->push_enabled) {
            $channels[] = 'broadcast';
        }
        return $channels;
    }

    public function toDatabase($notifiable): array
    {
        $fieldNames = [
            'headline' => 'Заголовок анкеты',
            'bio' => 'Поле «О себе»',
            'looking_for' => 'Поле «Кого я ищу»'
        ];
        $fieldName = $fieldNames[$this->field] ?? 'Поле анкеты';

        return [
            'type' => 'moderation',
            'title' => 'Анкета отредактирована модератором',
            'message' => "Ваше поле «{$fieldName}» было очищено модератором за нарушение правил сервиса. Пожалуйста, заполните его корректно.",
            'action_url' => url('/profile/edit'),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Ваш профиль был отредактирован')
            ->line('Модератор очистил одно из полей вашей анкеты за нарушение правил.')
            ->line('Пожалуйста, заполните его корректно.')
            ->action('Редактировать анкету', url('/profile/edit'));
    }
    
    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'type' => 'moderation',
            'title' => 'Анкета отредактирована модератором',
            'message' => "Одно из полей вашей анкеты было очищено модератором.",
            'action_url' => url('/profile/edit'),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}