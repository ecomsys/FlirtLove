<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Support\Facades\Log;

// Как использовать ?
// // Убеждаемся, что связи загружены (это делается в Action перед вызовом notify)
//  $userGift->loadMissing(['sender', 'gift']);

//  $receiver->notify(new NewGiftNotification(
//     userGiftId: $userGift->id,
//     senderId: $userGift->sender_id,
//     senderName: $userGift->sender?->name, // Заранее берем строку
//     giftName: $userGift->snapshot_name ?? $userGift->gift?->name ?? 'Подарок',
//     giftImageUrl: $userGift->image_url // Вызываем аксессор, который соберет URL из снапшота или связи
// ));

class NewGiftNotification extends Notification implements ShouldQueue
{
    use Queueable;

    // ФИКС: Передаем ТОЛЬКО скаляры! Никаких моделей в Redis.
    public function __construct(
        protected int $userGiftId,
        protected ?int $senderId,
        protected ?string $senderName,
        protected string $giftName,
        protected ?string $giftImageUrl
    ) {}

    public function via($notifiable): array
    {
        $channels = ['database']; // В базу (колокольчик) пишем ВСЕГДА

        // ФИКС: Безопасная проверка настроек (защита от TypeError)
        $emailSettings = $notifiable->email_settings ?? [];

        if ($notifiable->push_enabled) {
            $channels[] = 'broadcast';
        }

        if ($notifiable->email_enabled && ($emailSettings['on_gift'] ?? true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        // ФИКС: Используем скаляры
        $senderName = $this->senderName ?? 'Пользователь';
        $giftName = $this->giftName ?: 'Подарок';

        return (new MailMessage)
            ->subject('Вам подарили подарок! 🎁')
            ->greeting("Здравствуйте, {$notifiable->name}!")
            ->line("Пользователь {$senderName} отправил вам подарок: {$giftName}.")
            ->action('Посмотреть подарок', url('/gifts'));
    }

    public function toDatabase($notifiable): array
    {
        $senderName = $this->senderName ?? 'Пользователь';
        $giftName = $this->giftName ?: 'Подарок';

        return [
            'type' => 'new_gift',
            'title' => '🎁 Вам подарок!',
            'message' => "{$senderName} отправил(а) вам подарок: «{$giftName}».",
            'action_url' => url('/gifts'),
            'data' => [
                'user_gift_id' => $this->userGiftId,
                'sender_id' => $this->senderId,
                'sender_name' => $senderName,
                'gift_name' => $giftName,
                'gift_image' => $this->giftImageUrl, // Передаем готовый URL
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
        Log::error("Не удалось отправить NewGiftNotification (UserGift ID: {$this->userGiftId}): " . $exception->getMessage());
    }
}