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

    public function via($notifiable): array
    {
        // Добавьте 'mail', чтобы отправлять еще и на email
        return ['database', 'broadcast'];
    }

    // public function toMail($notifiable): MailMessage
    // {
    //     return (new MailMessage)
    //         ->subject('Ваш чат был удален модерацией')
    //         ->greeting("Здравствуйте, {$notifiable->name}!")
    //         ->line("Ваша переписка с другим пользователем была удалена модератором.")
    //         ->line("**Причина:** {$this->reason}.")
    //         ->line('Пожалуйста, соблюдайте правила нашего сообщества, чтобы избежать блокировки аккаунта.')
    //         ->action('Перейти в приложение', url('/'))
    //         ->line('Спасибо за понимание!');
    // }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'chat_deleted',
            'title' => '🗑️ Чат удален',
            'message' => "Ваша переписка была удалена модератором. Причина: {$this->reason}.",
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'type' => 'chat_deleted',
            'title' => '🗑️ Чат удален',
            'message' => "Ваша переписка была удалена модератором. Причина: {$this->reason}.",
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}