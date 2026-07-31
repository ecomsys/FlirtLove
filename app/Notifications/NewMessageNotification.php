<?php 

// Разбор архитектуры:

// Str::limit: Если юзер напишет в сообщении "Войну и мир" целиком, в превью уведомления (на почте и в колокольчике) 
// отобразится только первые 50 символов. Это защитит верстку письма и UI от расползания.
// Оператор ?-> (Nullsafe): $this->message->sender?->name. Это критически важно для очередей! 
// Если Вася отправил сообщение, и через 2 секунды удалил аккаунт, а воркер дошел до отправки письма только через минуту — 
// связь sender вернет null. Без ?-> код упадет. С ?-> он аккуратно напишет "Удаленный пользователь".
// Единый стандарт: Использованы те же проверки (email_enabled + on_message), failed() метод и DRY-подход для toBroadcast.

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NewMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Message $message
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

        // Проверяем глобальный тумблер Email И категорию "Новые сообщения" (on_message)
        if ($notifiable->email_enabled && ($notifiable->email_settings['on_message'] ?? true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     *  Отправка Email
     */
    public function toMail($notifiable): MailMessage
    {
        $senderName = $this->message->sender?->name ?? 'Удаленный пользователь';
        $preview = $this->getMessagePreview();

        return (new MailMessage)
            ->subject('Вам пришло новое сообщение')
            ->greeting("Здравствуйте, {$notifiable->name}!")
            ->line("Пользователь {$senderName} отправил вам сообщение:")
            ->line("\"{$preview}\"")
            ->action('Прочитать в чате', url('/chats/' . $this->message->chat_id));
    }

    /**
     *  Запись в БД (Колокольчик)
     */
    public function toDatabase($notifiable): array
    {
        $senderName = $this->message->sender?->name ?? 'Удаленный пользователь';
        $preview = $this->getMessagePreview();

        return [
            'type' => 'new_message',
            'title' => '💬 Новое сообщение',
            'message' => "{$senderName}: {$preview}",
            'action_url' => url('/chats/' . $this->message->chat_id),
            'data' => [
                'chat_id' => $this->message->chat_id,
                'message_id' => $this->message->id,
                'sender_id' => $this->message->sender_id,
                'sender_name' => $senderName,
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
     *  Хелпер для формирования превью сообщения (чтобы не дублировать код)
     */
    private function getMessagePreview(): string
    {
        // Если текст — обрезаем до 50 символов
        if ($this->message->type === 'text') {
            return Str::limit($this->message->body, 50);
        }
        
        // Если фото или подарок — возвращаем красивые заглушки
        return match ($this->message->type) {
            'image' => '📷 Фотография',
            'gift'  => '🎁 Подарок',
            default => 'Новое сообщение',
        };
    }

    /**
     * ЗАЩИТА ОЧЕРЕДИ
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Не удалось отправить NewMessageNotification (Message ID: {$this->message->id}): " . $exception->getMessage());
    }
}