<?php

namespace App\Notifications;

use App\Models\DiaryComment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Support\Facades\Log;

class DiaryCommentModerated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected DiaryComment $comment,
        protected string $status // approved, rejected, spam, deleted, restored
    ) {}

    /**
     * Каналы доставки с учетом глобальных тумблеров и категорий
     */
    public function via($notifiable): array
    {
        // 1. В кабинет (БД) отправляем ВСЕГДА
        $channels = ['database'];

        // 2. Email: проверяем глобальный тумблер И категорию "Новые события" (on_event).
        // Не отправляем при "spam" и "restored" по нашей бизнес-логике.
        if ($notifiable->email_enabled 
            && ($notifiable->email_settings['on_event'] ?? true) 
            && !in_array($this->status, ['spam', 'restored'])) {
            $channels[] = 'mail';
        }

        // 3. Push (Broadcast): отправляем ТОЛЬКО при "approved" и "rejected", 
        // И ЕСЛИ ВКЛЮЧЕН ГЛОБАЛЬНЫЙ ТУМБЛЕР push_enabled
        if ($notifiable->push_enabled && in_array($this->status, ['approved', 'rejected'])) {
            $channels[] = 'broadcast';
        }
       
        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        $messages = $this->getMessages();
        
        return (new MailMessage)
            ->subject($messages['subject'])
            ->greeting("Здравствуйте, {$notifiable->name}!")
            ->line($messages['body'])
            ->line("Комментарий: \"{$this->comment->content}\"")
            ->when($this->status === 'approved', function ($message) {
                return $message->line('Теперь он виден всем пользователям под записью в дневнике.');
            })
            ->when($this->status === 'rejected', function ($message) {
                return $message->line('Вы можете отредактировать комментарий и отправить его снова.');
            })
            ->when($this->status === 'deleted', function ($message) {
                return $message->line('Если вы считаете, что это ошибка, свяжитесь с поддержкой.');
            });
    }

    public function toDatabase($notifiable): array
    {
        $messages = $this->getMessages();
        
        return [
            'type' => 'diary_comment_moderated',
            'title' => $messages['title'],
            'message' => $messages['message'],
            'action_url' => url('/diaries/' . $this->comment->diary_id), // Ссылка на пост          
            'data' => [
                'comment_id' => $this->comment->id,
                'diary_id' => $this->comment->diary_id,
                'status' => $this->status,
                'content' => $this->comment->content,
            ]
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        $messages = $this->getMessages();
        
        return new BroadcastMessage([
            'type' => 'diary_comment_moderated',
            'title' => $messages['title'],
            'message' => $messages['message'],
            'action_url' => url('/diaries/' . $this->comment->diary_id),
            'timestamp' => now()->toDateTimeString(),
            'data' => [
                'comment_id' => $this->comment->id,
                'diary_id' => $this->comment->diary_id,
                'status' => $this->status,
                'content' => $this->comment->content,
            ]
        ]);
    }

    private function getMessages(): array
    {
        return match ($this->status) {
            'approved' => [
                'subject' => 'Ваш комментарий к дневнику одобрен',
                'title' => '✅ Комментарий одобрен',
                'body' => 'Ваш комментарий к записи в дневнике был одобрен модератором.',
                'message' => 'Ваш комментарий к записи в дневнике был одобрен модератором и теперь виден всем.',
            ],
            'rejected' => [
                'subject' => 'Ваш комментарий к дневнику отклонен',
                'title' => '❌ Комментарий отклонен',
                'body' => 'Ваш комментарий к записи в дневнике был отклонен модератором.',
                'message' => 'Ваш комментарий был отклонен модератором. Вы можете отредактировать его и отправить снова.',
            ],
            'spam' => [
                'subject' => 'Ваш комментарий помечен как спам',
                'title' => '🚫 Комментарий помечен как спам',
                'body' => 'Ваш комментарий к записи в дневнике был помечен как спам.',
                'message' => 'Ваш комментарий был помечен как спам. Пожалуйста, ознакомьтесь с правилами сообщества.',
            ],
            'deleted' => [
                'subject' => 'Ваш комментарий удален',
                'title' => '🗑️ Комментарий удален',
                'body' => 'Ваш комментарий к записи в дневнике был удален модератором.',
                'message' => 'Ваш комментарий был удален модератором. Если вы считаете, что это ошибка, свяжитесь с поддержкой.',
            ],
            'restored' => [
                'subject' => 'Ваш комментарий восстановлен',
                'title' => '🔄 Комментарий восстановлен',
                'body' => 'Ваш комментарий к записи в дневнике был восстановлен модератором.',
                'message' => 'Ваш комментарий был восстановлен модератором и снова виден всем.',
            ],
            default => [
                'subject' => 'Статус комментария изменен',
                'title' => 'Статус комментария изменен',
                'body' => 'Статус вашего комментария был изменен.',
                'message' => 'Статус вашего комментария был изменен модератором.',
            ],
        };
    }

    /**
     * ЗАЩИТА ОЧЕРЕДИ:
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Не удалось отправить DiaryCommentModerated (ID: {$this->comment->id}): " . $exception->getMessage());
    }
}