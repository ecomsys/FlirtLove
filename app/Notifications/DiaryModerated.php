<?php

namespace App\Notifications;

use App\Models\Diary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class DiaryModerated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Diary $diary,
        protected string $status, // approved, rejected, unpublished
        protected ?string $reason = null
    ) {}

    /**
     * Каналы доставки с учетом глобальных тумблеров и категорий
     */
    public function via($notifiable): array
    {
        $channels = ['database'];

        // Email: отправляем всегда, кроме случая, когда пост снят с публикации (незачем спамить)
        if ($notifiable->email_enabled 
            && ($notifiable->email_settings['on_event'] ?? true) 
            && !in_array($this->status, ['unpublished'])) {
            $channels[] = 'mail';
        }

        // Push (Broadcast): отправляем при всех важных статусах
        if ($notifiable->push_enabled && in_array($this->status, ['approved', 'rejected', 'unpublished'])) {
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
            ->line("Заголовок записи: {$this->diary->title}")
            ->when($this->status === 'rejected', function ($message) {
                return $message->line('Причина: ' . $this->getReasonText())
                               ->line('Вы можете отредактировать запись и отправить ее снова.');
            })
            ->when($this->status === 'unpublished', function ($message) {
                return $message->line('Возможно, запись требует доработки. Она снова доступна для редактирования.');
            });
    }

    public function toDatabase($notifiable): array
    {
        $messages = $this->getMessages();
        
        return [
            'type' => 'diary_moderated',
            'title' => $messages['title'],
            'message' => $messages['message'],
            'action_url' => url('/diaries/' . $this->diary->id),          
            'data' => [
                'diary_id' => $this->diary->id,
                'status' => $this->status,
                'reason' => $this->reason,
            ]
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }

    private function getReasonText(): string
    {
        if (!$this->reason) return 'Нарушение правил сервиса';
        $enum = \App\Enums\DiaryRejectReason::tryFrom($this->reason);
        return $enum ? $enum->label() : 'Нарушение правил сервиса';
    }

    private function getMessages(): array
    {
        return match ($this->status) {
            'approved' => [
                'subject' => 'Ваша запись в дневнике одобрена',
                'title' => '✅ Запись одобрена',
                'body' => 'Ваша запись в дневнике была одобрена модератором.',
                'message' => "Ваша запись «{$this->diary->title}» опубликована.",
            ],
            'rejected' => [
                'subject' => 'Ваша запись в дневнике отклонена',
                'title' => '❌ Запись отклонена',
                'body' => 'Ваша запись в дневнике была отклонена модератором.',
                'message' => "Запись «{$this->diary->title}» отклонена. Причина: {$this->getReasonText()}",
            ],
            'unpublished' => [
                'subject' => 'Ваша запись снята с публикации',
                'title' => '⏸️ Запись снята с публикации',
                'body' => 'Ваша запись в дневнике была снята с публикации администратором.',
                'message' => "Запись «{$this->diary->title}» снята с публикации и возвращена в черновики.",
            ],
            default => [
                'subject' => 'Статус записи изменен',
                'title' => 'Статус записи изменен',
                'body' => 'Статус вашей записи был изменен.',
                'message' => 'Статус вашей записи был изменен модератором.',
            ],
        };
    }

    /**
     * ЗАЩИТА ОЧЕРЕДИ:
     */
    public function failed(\Throwable $exception): void
    {
        \Illuminate\Support\Facades\Log::error("Не удалось отправить DiaryModerated (ID: {$this->diary->id}): " . $exception->getMessage());
    }
}