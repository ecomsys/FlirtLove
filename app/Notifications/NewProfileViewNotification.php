<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Support\Facades\Log;

// Как вызывать в вебе ?
//  $viewedUser->notify(new NewProfileViewNotification(
//     viewerId: $viewer->id,
//     viewerName: $viewer->name
// ));

class NewProfileViewNotification extends Notification implements ShouldQueue
{
    use Queueable;

    // ФИКС: Передаем ТОЛЬКО скаляры! Никаких моделей в Redis.
    public function __construct(
        protected int $viewerId,
        protected string $viewerName
    ) {}

    public function via($notifiable): array
    {
        $channels = ['database']; // В базу (колокольчик) пишем ВСЕГДА

        if ($notifiable->push_enabled) {
            $channels[] = 'broadcast';
        }

        // ФИКС: Безопасная проверка настроек (защита от TypeError если email_settings = null)
        $emailSettings = $notifiable->email_settings ?? [];
        
        // По умолчанию on_view = false, чтобы не спамить популярных юзеров
        if ($notifiable->email_enabled && ($emailSettings['on_view'] ?? false)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Вашу анкету посмотрели 👀')
            ->greeting("Здравствуйте, {$notifiable->name}!");

        if ($notifiable->hasActivePremium()) {
            // ФИКС: Используем скаляр $this->viewerName
            $mail->line("Пользователь {$this->viewerName} посмотрел вашу анкету.")
                 ->action('Посмотреть анкету', url('/profile/' . $this->viewerId));
        } else {
            $mail->line("Кто-то из пользователей заинтересовался вашей анкетой.")
                 ->line('Откройте VIP-статус, чтобы видеть всех, кто проявляет к вам интерес.');
        }

        return $mail;
    }

    public function toDatabase($notifiable): array
    {
        $isVip = $notifiable->hasActivePremium();
        
        if ($isVip) {
            $message = "{$this->viewerName} посмотрел(а) вашу анкету.";
            $actionUrl = url('/profile/' . $this->viewerId);
        } else {
            $message = 'Кто-то посмотрел вашу анкету. Откройте VIP, чтобы узнать кто!';
            $actionUrl = url('/pricing'); // Ведем на страницу покупки VIP
        }

        return [
            'type' => 'profile_view',
            'title' => '👀 Новый просмотр',
            'message' => $message,
            'action_url' => $actionUrl,
            'data' => [
                'viewer_id' => $isVip ? $this->viewerId : null, // Скрываем ID, если без VIP
                'is_hidden' => !$isVip,
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
        // ФИКС: Логируем по ID, так как самой модели тут нет
        Log::error("Не удалось отправить NewProfileViewNotification (Viewer ID: {$this->viewerId}): " . $exception->getMessage());
    }
}