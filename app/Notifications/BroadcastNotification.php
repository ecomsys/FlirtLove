<?php

namespace App\Notifications;

use App\Models\Broadcast;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Support\Facades\Log;

class BroadcastNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Broadcast $broadcast;

    public function __construct(Broadcast $broadcast)
    {
        $this->broadcast = $broadcast;
    }

    /**
     * Определяем каналы доставки в зависимости от типа оповещения и настроек юзера.
     * В нашей БД типы: in_app, push, email.
     */
    public function via($notifiable): array
    {
        $channels = [];
        $type = $this->broadcast->type;

        // 1. In-App (только запись в БД, для колокольчика). Никак не валидируется настройками,
        // т.к. это системное сообщение от админа.
        if ($type === 'in_app') {
            return ['database'];
        }

        // 2. Email рассылка (проверяем настройки юзера)
        if ($type === 'email' && $notifiable->email_enabled && ($notifiable->email_settings['on_broadcast'] ?? true)) {
            $channels[] = 'database'; // Дублируем в колокольчик
            $channels[] = 'mail';
        }

        // 3. Push рассылка (WebSockets на сайте). Проверяем глобальный тумблер.
        if ($type === 'push' && $notifiable->push_enabled) {
            $channels[] = 'database'; // Дублируем в колокольчик
            $channels[] = 'broadcast'; // Реалтайм через WebSockets
        }

        // Если ни одно условие не подошло (например, юзер выключил пуши) — отдаем пустой массив,
        // Laravel просто пропустит этого юзера.
        return $channels;
    }

    /**
     * Сохраняем уведомление в БД (таблица notifications)
     */
    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'broadcast',
            'title' => $this->broadcast->title,
            'message' => $this->broadcast->message,
            'action_url' => $this->broadcast->data['action_url'] ?? url('/profile'),          
            'data' => [
                'broadcast_id' => $this->broadcast->id,
                'broadcast_type' => $this->broadcast->type,
            ]
        ];
    }

    /**
     * Отправляем email
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->broadcast->title)
            ->greeting("Здравствуйте, {$notifiable->name}!")
            ->line($this->broadcast->message)
            ->action('Перейти на сайт', $this->broadcast->data['action_url'] ?? url('/profile'));
    }

    /**
     * Отправляем push через WebSocket (Realtime)
     */
    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'type' => 'broadcast',
            'title' => $this->broadcast->title,
            'message' => $this->broadcast->message,
            'action_url' => $this->broadcast->data['action_url'] ?? url('/profile'),
            'timestamp' => now()->toDateTimeString(),
            'data' => [
                'broadcast_id' => $this->broadcast->id,
                'broadcast_type' => $this->broadcast->type,
            ]
        ]);
    }

    /**
     * ЗАЩИТА ОЧЕРЕДИ:
     * Если рассылка была удалена админом, пока письмо висело в очереди,
     * воркер не упадет с ModelNotFoundException, а просто запишет лог и завершится.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Не удалось отправить BroadcastNotification (ID: {$this->broadcast->id}): " . $exception->getMessage());
    }
}