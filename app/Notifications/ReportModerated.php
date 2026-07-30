<?php

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class ReportModerated extends Notification implements ShouldQueue
{
    use Queueable;

    protected $report;
    protected string $action;
    protected $additionalInfo;

    public function __construct(
        ?Report $report = null,
        string $action = 'resolved',
        ?string $additionalInfo = null
    ) {
        $this->report = $report;
        $this->action = $action;
        $this->additionalInfo = $additionalInfo;
    }

    /**
     *  Обновляем каналы доставки с учетом настроек юзера
     */
    public function via($notifiable): array
    {
        $channels = ['database']; // В базу (колокольчик) пишем ВСЕГДА

        // Проверяем глобальный тумблер Push
        if ($notifiable->push_enabled && in_array($this->action, ['resolved', 'rejected'])) {
            $channels[] = 'broadcast';
        }

        // Проверяем настройку Email для жалоб (?? true — дефолт для старых юзеров)
        if ($notifiable->email_settings['on_report'] ?? true) {
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
            if ($this->report->type === 'user') {
                $reportedName = $this->report->reportedUser
                    ? $this->report->reportedUser->name
                    : 'Удален';

                $mail->line('Жалоба на пользователя: ' . $reportedName);
                $mail->line('Причина: "' . $this->report->reason . '"');
            } elseif ($this->report->type === 'photo') {
                $mail->line('Жалоба на фото #' . $this->report->photo_id);
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
        
        // Склеиваем основной текст и доп. информацию, чтобы фронтенду было проще
        $finalMessage = $messages['message'];
        if ($this->additionalInfo) {
            $finalMessage .= ' ' . $this->additionalInfo;
        }

        return [
            // === УНИФИЦИРОВАННАЯ СТРУКТУРА ===
            'type' => 'report_moderated',
            'title' => $messages['title'],
            'message' => $finalMessage,
            'action_url' => url('/'), // или url('/admin/reports') если это для админа
            
            // === СПЕЦИФИЧНЫЕ ДАННЫЕ ===
            'data' => [
                'report_id' => $this->report ? $this->report->id : null,
                'action' => $this->action,
                'report_type' => $this->report ? $this->report->type : null,
                'reason' => $this->report ? $this->report->reason : null,
                'additional_info' => $this->additionalInfo, // Оставляем на всякий случай
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
            // === УНИФИЦИРОВАННАЯ СТРУКТУРА ===
            'type' => 'report_moderated',
            'title' => $messages['title'],
            'message' => $finalMessage,
            'action_url' => url('/'),
            'timestamp' => now()->toDateTimeString(),
            
            // === СПЕЦИФИЧНЫЕ ДАННЫЕ ===
            'data' => [
                'report_id' => $this->report ? $this->report->id : null,
                'action' => $this->action,
                'report_type' => $this->report ? $this->report->type : null,
                'reason' => $this->report ? $this->report->reason : null,
                'additional_info' => $this->additionalInfo,
            ]
        ]);
    }

    private function getReportedUserName(): string
    {
        if ($this->report && $this->report->reportedUser) {
            return $this->report->reportedUser->name;
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
}