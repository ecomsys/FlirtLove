<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class ChatDeletedByAdmin extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $reason;

    public function __construct(string $reason = 'нарушение правил сайта')
    {
        $this->reason = $reason;
    }

    /**
     *  Обновляем каналы доставки с учетом настроек юзера
     */
    public function via($notifiable): array
    {
        $channels = ['database']; // В базу (колокольчик) пишем ВСЕГДА

        // Проверяем глобальный тумблер Push
        if ($notifiable->push_enabled) {
            $channels[] = 'broadcast';
        }

        // Проверяем настройку Email для модерации (используем on_report ?? true)
        if ($notifiable->email_settings['on_report'] ?? true) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     *  Раскомментировали и обновили метод toMail
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Ваш чат был удален модерацией')
            ->greeting("Здравствуйте, {$notifiable->name}!")
            ->line("Ваша переписка с другим пользователем была удалена модератором.")
            ->line("**Причина:** {$this->reason}.")
            ->line('Пожалуйста, соблюдайте правила нашего сообщества, чтобы избежать блокировки аккаунта.')
            ->action('Перейти в приложение', url('/'))
            ->line('Спасибо за понимание!');
    }

    public function toDatabase($notifiable): array
    {
        return [
            // === УНИФИЦИРОВАННАЯ СТРУКТУРА ===
            'type' => 'chat_deleted',
            'title' => '🗑️ Чат удален',
            'message' => "Ваша переписка была удалена модератором. Причина: {$this->reason}.",
            'action_url' => url('/'),
            
            // === СПЕЦИФИЧНЫЕ ДАННЫЕ ===
            'data' => [
                'reason' => $this->reason,
            ]
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            // === УНИФИЦИРОВАННАЯ СТРУКТУРА ===
            'type' => 'chat_deleted',
            'title' => '🗑️ Чат удален',
            'message' => "Ваша переписка была удалена модератором. Причина: {$this->reason}.",
            'action_url' => url('/'),
            'timestamp' => now()->toDateTimeString(),
            
            // === СПЕЦИФИЧНЫЕ ДАННЫЕ ===
            'data' => [
                'reason' => $this->reason,
            ]
        ]);
    }
}