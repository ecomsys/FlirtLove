<?php 

// Я передаю в конструктор модель User (того, с кем сматчились) и ID чата, чтобы юзер по клику сразу попадал в диалог.
//  Также используем Null-safe оператор ?-> на случай, если юзер удалит аккаунт, пока письмо висит в очереди.

//  Фишки этой реализации:
// Связка с чатом: При создании мэтча в MatchService мы сразу создаем чат. Поэтому сюда мы передаем $chatId.
//  Юзер кликает на пуш или письмо — и сразу открывается переписка. Идеальный UX!
// Категория on_like: Уведомление прилетит только если в настройках юзера включена галка "Новые симпатии".
// Защита failed(): В логе при ошибке увидим ID обоих юзеров, чтобы легко дебажить.


namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Support\Facades\Log;

class NewMatchNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected User $partner,
        protected int $chatId
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

        // Проверяем глобальный тумблер Email И категорию "Новые симпатии" (on_like)
        if ($notifiable->email_enabled && ($notifiable->email_settings['on_like'] ?? true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     *  Отправка Email
     */
    public function toMail($notifiable): MailMessage
    {
        $partnerName = $this->partner->name ?? 'Пользователь';

        return (new MailMessage)
            ->subject('У вас взаимная симпатия! ❤️')
            ->greeting("Здравствуйте, {$notifiable->name}!")
            ->line("Вы понравились друг другу! Вы и {$partnerName} выразили взаимную симпатию.")
            ->line('Не упустите шанс начать общение!')
            ->action('Написать сообщение', url('/chats/' . $this->chatId));
    }

    /**
     *  Запись в БД (Колокольчик)
     */
    public function toDatabase($notifiable): array
    {
        $partnerName = $this->partner->name ?? 'Пользователь';

        return [
            'type' => 'new_match',
            'title' => '❤️ Взаимная симпатия!',
            'message' => "Вы понравились друг другу! Начните общение с {$partnerName}.",
            'action_url' => url('/chats/' . $this->chatId),
            'data' => [
                'partner_id' => $this->partner->id,
                'partner_name' => $partnerName,
                'chat_id' => $this->chatId,
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
        // $notifiable тут недоступен, логируем только ID партнера
        Log::error("Не удалось отправить NewMatchNotification (Partner ID: {$this->partner->id}): " . $exception->getMessage());
    }
}