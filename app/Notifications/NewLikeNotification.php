<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Support\Facades\Log;

// Как использовать ?
//  $targetUser->notify(new NewLikeNotification(
//     likerId: $liker->id,
//     likerName: $liker->name, // Заранее берем строку, чтобы воркер не делал N+1 запрос к базе
//     isSuperlike: $isSuperlike
// ));

class NewLikeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    // ФИКС: Передаем ТОЛЬКО скаляры! Никаких моделей в Redis.
    public function __construct(
        protected int $likerId,
        protected ?string $likerName, // Nullable на случай, если юзер удален
        protected bool $isSuperlike = false
    ) {}

    public function via($notifiable): array
    {
        $channels = ['database']; // В базу (колокольчик) пишем ВСЕГДА

        // ФИКС: Безопасная проверка настроек (защита от TypeError если email_settings = null)
        $emailSettings = $notifiable->email_settings ?? [];

        if ($notifiable->push_enabled) {
            $channels[] = 'broadcast';
        }

        // Проверяем глобальный тумблер Email И категорию "Новые симпатии" (on_like)
        if ($notifiable->email_enabled && ($emailSettings['on_like'] ?? true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->isSuperlike ? 'Вам отправили Суперлайк! ⭐' : 'Вам кто-то симпатизировал! ❤️')
            ->greeting("Здравствуйте, {$notifiable->name}!");

        if ($notifiable->hasActivePremium()) {
            // ФИКС: Используем скаляр
            $likerName = $this->likerName ?? 'Пользователь';
            $mail->line("Пользователь {$likerName} проявил к вам симпатию" . ($this->isSuperlike ? ' (Суперлайк)!' : '.'))
                 ->action('Посмотреть анкету', url('/profile/' . $this->likerId));
        } else {
            $mail->line("Кто-то проявил к вам симпатию" . ($this->isSuperlike ? ' (Суперлайк)!' : '!'))
                 ->line('Откройте VIP-статус, чтобы узнать, кто именно оценил ваши фото.');
        }

        return $mail;
    }

    public function toDatabase($notifiable): array
    {
        $isVip = $notifiable->hasActivePremium();
        
        $title = $this->isSuperlike ? '⭐ Суперлайк!' : '❤️ Новая симпатия';
        
        if ($isVip) {
            // ФИКС: Используем скаляр
            $likerName = $this->likerName ?? 'Пользователь';
            $message = "{$likerName} симпатизировал(а) вам" . ($this->isSuperlike ? ' (Суперлайк)!' : '.');
            $actionUrl = url('/profile/' . $this->likerId);
        } else {
            $message = 'Кто-то симпатизировал вам. Откройте VIP, чтобы увидеть!';
            $actionUrl = url('/pricing'); // Ведем на страницу покупки VIP
        }

        return [
            'type' => 'new_like',
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'data' => [
                'liker_id' => $isVip ? $this->likerId : null, // Скрываем ID, если без VIP
                'is_superlike' => $this->isSuperlike,
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

    /**
     * ЗАЩИТА ОЧЕРЕДИ
     */
    public function failed(\Throwable $exception): void
    {
        // ФИКС: Логируем по ID, так как самой модели тут нет
        Log::error("Не удалось отправить NewLikeNotification (Liker ID: {$this->likerId}): " . $exception->getMessage());
    }
}