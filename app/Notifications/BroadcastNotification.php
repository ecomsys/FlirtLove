<?php

namespace App\Notifications;

use App\Models\Broadcast;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class BroadcastNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Broadcast $broadcast;

    public function __construct(Broadcast $broadcast)
    {
        $this->broadcast = $broadcast;
    }

    /**
     * Определяем каналы доставки в зависимости от типа оповещения
     */
    public function via($notifiable): array
    {
        $channels = [];

        // Для system – только база данных
        if ($this->broadcast->type === 'system') {
            $channels[] = 'database';
        }

        // Для email – база данных + email
        if ($this->broadcast->type === 'email') {
            $channels[] = 'database';
            $channels[] = 'mail';
        }

        // Для push – база данных + broadcast (WebSocket) 
        if ($this->broadcast->type === 'push') {
            $channels[] = 'database';
            $channels[] = 'broadcast';
        }

        return $channels;
    }

    /**
     * Сохраняем уведомление в БД (таблица notifications)
     */
    public function toDatabase($notifiable): array
    {
        return [
            'broadcast_id' => $this->broadcast->id,
            'title' => $this->broadcast->title,
            'message' => $this->broadcast->message,
            'type' => $this->broadcast->type,
        ];
    }

    /**
     * Отправляем email (если нужно)
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->broadcast->title)
            ->greeting("Здравствуйте, {$notifiable->name}!")
            ->line($this->broadcast->message)
            ->action('Перейти в профиль', url('/profile'));
    }

    /**
     * Отправляем push через WebSocket (если нужно)
     */
    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'broadcast_id' => $this->broadcast->id,
            'title' => $this->broadcast->title,
            'message' => $this->broadcast->message,
            'type' => $this->broadcast->type,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}