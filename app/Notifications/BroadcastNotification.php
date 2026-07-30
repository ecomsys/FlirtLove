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
     * Определяем каналы доставки в зависимости от типа оповещения и настроек юзера
     */
    public function via($notifiable): array
    {
        $channels = ['database']; // В базу (колокольчик) пишем ВСЕГДА

        // Если это email-рассылка, проверяем, включена ли у юзера настройка on_broadcast
        if ($this->broadcast->type === 'email' && ($notifiable->email_settings['on_broadcast'] ?? true)) {
            $channels[] = 'mail';
        }

        // Если это push-рассылка, проверяем глобальный тумблер push_enabled
        if ($this->broadcast->type === 'push' && $notifiable->push_enabled) {
            $channels[] = 'broadcast';
        }

        // Для 'system' мы ничего не добавляем, остается только 'database'
        return $channels;
    }

    /**
     * Сохраняем уведомление в БД (таблица notifications)
     */
    public function toDatabase($notifiable): array
    {
        return [
            // === УНИФИЦИРОВАННАЯ СТРУКТУРА ===
            'type' => 'broadcast',
            'title' => $this->broadcast->title,
            'message' => $this->broadcast->message,
            'action_url' => url('/profile'),          
            
            // === СПЕЦИФИЧНЫЕ ДАННЫЕ ===
            'data' => [
                'broadcast_id' => $this->broadcast->id,
                'broadcast_type' => $this->broadcast->type, // system, email, push
            ]
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
            // === УНИФИЦИРОВАННАЯ СТРУКТУРА ===
            'type' => 'broadcast',
            'title' => $this->broadcast->title,
            'message' => $this->broadcast->message,
            'action_url' => url('/profile'),
            'timestamp' => now()->toDateTimeString(),
            
            // === СПЕЦИФИЧНЫЕ ДАННЫЕ ===
            'data' => [
                'broadcast_id' => $this->broadcast->id,
                'broadcast_type' => $this->broadcast->type,
            ]
        ]);
    }
}