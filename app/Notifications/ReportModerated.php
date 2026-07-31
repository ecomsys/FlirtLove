<?php

namespace App\Notifications;

use App\Models\Report;
use App\Models\User;
use App\Models\Photo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Support\Facades\Log;

class ReportModerated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected ?Report $report = null,
        protected string $action = 'resolved',
        protected ?string $additionalInfo = null
    ) {}

    /**
     *  Каналы доставки с учетом глобальных тумблеров и категорий
     */
    public function via($notifiable): array
    {
        $channels = ['database']; // В базу (колокольчик) пишем ВСЕГДА

        // Проверяем глобальный тумблер Push
        if ($notifiable->push_enabled && in_array($this->action, ['resolved', 'rejected', 'user_banned', 'photo_deleted'])) {
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
        $messages = $this->getMessages();

        $mail = (new MailMessage)
            ->subject($messages['subject'])
            ->greeting('Здравствуйте, ' . $notifiable->name . '!')
            ->line($messages['body']);

        if ($this->report) {
            // Используем полиморфную связь reportable_type вместо старого поля type
            if ($this->report->reportable_type === User::class) {
                $reportedName = $this->report->reported ? $this->report->reported->name : 'Удален';
                $mail->line('Жалоба на пользователя: ' . $reportedName);
                $mail->line('Причина: "' . $this->report->reason . '"');
            } elseif ($this->report->reportable_type === Photo::class) {
                // Берем ID из полиморфной связи
                $mail->line('Жалоба на фото #' . $this->report->reportable_id);
                $mail->line('Причина: "' . $this->report->reason . '"');
            }
        }

        if ($this->action === 'resolved') {
            $mail->line('Модератор рассмотрел вашу жалобу и принял меры.');
        } elseif ($this->action === 'rejected') {
            $mail->line('К сожалению, ваша жалоба не была подтверждена.');
        } elseif ($this->action === 'user_banned') {
            $userName = $this->getReportedUserName();
            $mail->line('Пользователь ' . $userName . ' забанен.');
        } elseif ($this->action === 'photo_deleted') {
            $mail->line('Фото удалено с сайта.');
        }

        if ($this->additionalInfo) {
            $mail->line($this->additionalInfo);
        }

        return $mail;
    }

    public function toDatabase($notifiable): array
    {
        $messages = $this->getMessages();
        
        $finalMessage = $messages['message'];
        if ($this->additionalInfo) {
            $finalMessage .= ' ' . $this->additionalInfo;
        }

        return [
            'type' => 'report_moderated',
            'title' => $messages['title'],
            'message' => $finalMessage,
            'action_url' => url('/'), // Или url('/admin/reports') если это для админа
            'data' => [
                'report_id' => $this->report ? $this->report->id : null,
                'action' => $this->action,
                // Сохраняем тип сущности через полиморфную связь
                'report_type' => $this->report ? $this->report->reportable_type : null,
                'reason' => $this->report ? $this->report->reason : null,
                'additional_info' => $this->additionalInfo,
            ]
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        $messages = $this->getMessages();
        
        $finalMessage = $messages['message'];
        if ($this->additionalInfo) {
            $finalMessage .= ' ' . $this->additionalInfo;
        }

        return new BroadcastMessage([
            'type' => 'report_moderated',
            'title' => $messages['title'],
            'message' => $finalMessage,
            'action_url' => url('/'),
            'timestamp' => now()->toDateTimeString(),
            'data' => [
                'report_id' => $this->report ? $this->report->id : null,
                'action' => $this->action,
                'report_type' => $this->report ? $this->report->reportable_type : null,
                'reason' => $this->report ? $this->report->reason : null,
                'additional_info' => $this->additionalInfo,
            ]
        ]);
    }

    private function getReportedUserName(): string
    {
        // Используем связь reported() из нашей новой модели
        if ($this->report && $this->report->reported) {
            return $this->report->reported->name;
        }

        return 'нарушитель';
    }

    private function getMessages(): array
    {
        $userName = $this->getReportedUserName();

        return match ($this->action) {
            'resolved' => [
                'subject' => 'Ваша жалоба решена',
                'title' => '✅ Жалоба решена',
                'body' => 'Ваша жалоба была рассмотрена и решена модератором.',
                'message' => 'Модератор рассмотрел вашу жалобу и принял соответствующие меры.',
            ],
            'rejected' => [
                'subject' => 'Ваша жалоба отклонена',
                'title' => '❌ Жалоба отклонена',
                'body' => 'Ваша жалоба была отклонена модератором.',
                'message' => 'Модератор не нашел оснований для удовлетворения вашей жалобы.',
            ],
            'user_banned' => [
                'subject' => 'Пользователь забанен по вашей жалобе',
                'title' => '🔒 Пользователь забанен',
                'body' => 'По вашей жалобе пользователь был забанен.',
                'message' => 'Пользователь ' . $userName . ' был забанен на основании жалобы.',
            ],
            'photo_deleted' => [
                'subject' => 'Фото удалено по вашей жалобе',
                'title' => '📸 Фото удалено',
                'body' => 'Фото, на которое вы пожаловались, было удалено.',
                'message' => 'Фото было удалено с сайта на основании вашей жалобы.',
            ],
            default => [
                'subject' => 'Статус жалобы изменен',
                'title' => 'Статус жалобы изменен',
                'body' => 'Статус вашей жалобы был изменен модератором.',
                'message' => 'Статус вашей жалобы был изменен. Подробности в личном кабинете.',
            ],
        };
    }

    /**
     * ЗАЩИТА ОЧЕРЕДИ
     */
    public function failed(\Throwable $exception): void
    {
        $reportId = $this->report ? $this->report->id : 'N/A';
        Log::error("Не удалось отправить ReportModerated (Report ID: {$reportId}): " . $exception->getMessage());
    }
}