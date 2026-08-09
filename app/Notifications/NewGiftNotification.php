<?php 

// Получение подарка — это всегда приятное событие, которое повышает вовлеченность.

// Мы передаем в конструктор модель UserGift. Вспоминаем нашу архитектуру: в UserGift есть sender, gift 
// (ссылка на каталог) и snapshot_name (снапшот названия на момент отправки). Чтобы уведомление работало 
// железобетонно даже если подарок удалят из каталога, мы берем название из snapshot_name.

// Разбор архитектуры:

// Снапшоты (snapshot_name): Как мы и проектировали в БД, уведомление берет название подарка из снапшота. 
// Если админ через год изменит "Мишка" на "Плюшевый Медведь", старые уведомления в колокольчике останутся с 
// оригинальным названием.
// Аксессор image_url: В массиве data мы отдаем $this->userGift->image_url. Этот аксессор мы писали в модели UserGift — 
// он сам проверит снапшот и вернет правильную ссылку на картинку. На фронте (в колокольчике) ты сможешь сразу отрисовать 
// иконку подарка: <img src="{{ $notification->data['gift_image'] }}">.
// Приватность: Подарок отправляется в ленту уведомлений (колокольчик). Если подарок is_private, фронтенд сможет скрыть
//  его из публичной ленты активности юзера, но в личном колокольчике он будет виден.

namespace App\Notifications;

use App\Models\UserGift;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Support\Facades\Log;

class NewGiftNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected UserGift $userGift
    ) {}

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

        // Проверяем глобальный тумблер Email И категорию "Новые подарки" (on_gift)
        if ($notifiable->email_enabled && ($notifiable->email_settings['on_gift'] ?? true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     *  Отправка Email
     */
    public function toMail($notifiable): MailMessage
    {
        $senderName = $this->userGift->sender?->name ?? 'Пользователь';
        // Берем название из снапшота, чтобы не сломать письмо, если подарок удалят из каталога
        $giftName = $this->userGift->snapshot_name ?? 'Подарок';

        return (new MailMessage)
            ->subject('Вам подарили подарок! 🎁')
            ->greeting("Здравствуйте, {$notifiable->name}!")
            ->line("Пользователь {$senderName} отправил вам подарок: {$giftName}.")
            ->action('Посмотреть подарок', url('/gifts'));
    }

    /**
     *  Запись в БД (Колокольчик)
     */
    public function toDatabase($notifiable): array
    {
        $senderName = $this->userGift->sender?->name ?? 'Пользователь';
        $giftName = $this->userGift->snapshot_name ?? 'Подарок';

        return [
            'type' => 'new_gift',
            'title' => '🎁 Вам подарок!',
            'message' => "{$senderName} отправил(а) вам подарок: «{$giftName}».",
            'action_url' => url('/gifts'),
            'data' => [
                'user_gift_id' => $this->userGift->id,
                'sender_id' => $this->userGift->sender_id,
                'sender_name' => $senderName,
                'gift_name' => $giftName,
                'gift_image' => $this->userGift->image_url, // Используем наш аксессор из модели UserGift
            ]
        ];
    }

    /**
     *  Realtime push через WebSockets (DRY-подход)
     */
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
        Log::error("Не удалось отправить NewGiftNotification (UserGift ID: {$this->userGift->id}): " . $exception->getMessage());
    }
}