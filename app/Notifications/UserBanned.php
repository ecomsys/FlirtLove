<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Support\Facades\Log;

class UserBanned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected bool $isBanned
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

        // Проверяем глобальный тумблер Email И категорию "Новые события" (on_event)
        if ($notifiable->email_enabled && ($notifiable->email_settings['on_event'] ?? true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        if ($this->isBanned) {
            return (new MailMessage)
                ->subject('Ваш аккаунт был заблокирован')
                ->greeting('Здравствуйте, ' . $notifiable->name . '!')
                ->line('Ваш аккаунт был заблокирован администрацией сайта.')
                ->line('Если вы считаете, что это ошибка, пожалуйста, свяжитесь с поддержкой.')
                ->action('Связаться с поддержкой', url('/support'));
        }

        return (new MailMessage)
            ->subject('Ваш аккаунт был разблокирован')
            ->greeting('Здравствуйте, ' . $notifiable->name . '!')
            ->line('Ваш аккаунт был успешно разблокирован.')
            ->line('Теперь вы снова можете пользоваться всеми функциями сайта.')
            ->action('Перейти на сайт', url('/'));
    }

    public function toDatabase($notifiable): array
    {
        // Подготавливаем данные в зависимости от статуса
        if ($this->isBanned) {
            $payload = [
                'type' => 'user_banned',
                'title' => '🔒 Аккаунт заблокирован',
                'message' => 'Ваш аккаунт был заблокирован администрацией. Обратитесь в поддержку.',
                'action_url' => url('/support'),
            ];
        } else {
            $payload = [
                'type' => 'user_unbanned',
                'title' => '✅ Аккаунт разблокирован',
                'message' => 'Ваш аккаунт был успешно разблокирован. Добро пожаловать!',
                'action_url' => url('/'),
            ];
        }

        return [
            // === УНИФИЦИРОВАННАЯ СТРУКТУРА ===
            'type' => $payload['type'],
            'title' => $payload['title'],
            'message' => $payload['message'],
            'action_url' => $payload['action_url'],
            
            // === СПЕЦИФИЧНЫЕ ДАННЫЕ ===
            'data' => [
                'is_banned' => $this->isBanned,
            ]
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        // Используем те же данные, что и для БД (Твой крутой DRY-подход)
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
        $status = $this->isBanned ? 'Banned' : 'Unbanned';
        Log::error("Не удалось отправить UserBanned (Status: {$status}): " . $exception->getMessage());
    }
}