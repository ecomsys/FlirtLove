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
            ->subject('Ваш чат был удален модерацией')
            ->greeting("Здравствуйте, {$notifiable->name}!")
            ->line("Ваша переписка с другим пользователем была удалена модератором.")
            ->line("**Причина:** {$this->reason}.")
            ->line('Пожалуйста, соблюдайте правила нашего сообщества, чтобы избежать блокировки аккаунта.')
            ->action('Перейти в приложение', url('/'))
            ->line('Спасибо за понимание!');
    }

    /**
     *  Запись в БД (Колокольчик)
     */
    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'chat_deleted',
            'title' => '🗑️ Чат удален',
            'message' => "Ваша переписка была удалена модератором. Причина: {$this->reason}.",
            'action_url' => url('/'),
            'data' => [
                'reason' => $this->reason,
            ]
        ];
    }

    /**
     *  Realtime push через WebSockets
     */
    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'type' => 'chat_deleted',
            'title' => '🗑️ Чат удален',
            'message' => "Ваша переписка была удалена модератором. Причина: {$this->reason}.",
            'action_url' => url('/'),
            'timestamp' => now()->toDateTimeString(),
            'data' => [
                'reason' => $this->reason,
            ]
        ]);
    }
}