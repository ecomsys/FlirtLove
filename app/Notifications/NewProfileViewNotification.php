<?php 

// Просмотр анкеты — это уведомление, которое генерирует огромный трафик на сайте, но оно же может сильно спамить почту, 
// если анкета популярная.

// Поэтому мы делаем два важных шага:

// Категория on_view: По умолчанию в наших настройках она выключена ('on_view' => false). Если юзер не захочет, 
// он не получит ни одного письма о просмотрах.
// Монетизация: Как и с лайками, скрываем имя того, кто посмотрел, если юзер без VIP. "Кто-то заинтересовался вами... 
// купите VIP, чтобы узнать кто".

// Разбор архитектуры:

// Дефолт ?? false: Обрати внимание на ($notifiable->email_settings['on_view'] ?? false). В UserPreference мы договорились, 
// что просмотры по умолчанию выключены. Если мы тут по ошибке напишем ?? true, популярные девушки будут получать по 100
// писем в день и удалят аккаунт.
// Монетизация просмотров: В LovePlanet функция "Кто смотрел анкету" — это одна из главных причин купить VIP. Поэтому мы 
// жестко скрываем имя и аватарку (через is_hidden во фронтенде), если подписки нет.
// Идемпотентность БД: Даже если почта выключена, в колокольчик (database) запись упадет. Юзер, зайдя на сайт, увидит 
// счетчик и захочет купить VIP, чтобы кликнуть на эти уведомления.

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Support\Facades\Log;

class NewProfileViewNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected User $viewer
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

        // Проверяем глобальный тумблер Email И категорию "Новые просмотры" (on_view)
        // По умолчанию on_view = false, чтобы не спамить популярных юзеров
        if ($notifiable->email_enabled && ($notifiable->email_settings['on_view'] ?? false)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     *  Отправка Email
     */
    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Вашу анкету посмотрели 👀')
            ->greeting("Здравствуйте, {$notifiable->name}!");

        if ($notifiable->hasActivePremium()) {
            $viewerName = $this->viewer->name ?? 'Пользователь';
            $mail->line("Пользователь {$viewerName} посмотрел вашу анкету.")
                 ->action('Посмотреть анкету', url('/profile/' . $this->viewer->id));
        } else {
            $mail->line("Кто-то из пользователей заинтересовался вашей анкетой.")
                 ->line('Откройте VIP-статус, чтобы видеть всех, кто проявляет к вам интерес.');
        }

        return $mail;
    }

    /**
     *  Запись в БД (Колокольчик)
     */
    public function toDatabase($notifiable): array
    {
        $isVip = $notifiable->hasActivePremium();
        
        if ($isVip) {
            $viewerName = $this->viewer->name ?? 'Пользователь';
            $message = "{$viewerName} посмотрел(а) вашу анкету.";
            $actionUrl = url('/profile/' . $this->viewer->id);
        } else {
            $message = 'Кто-то посмотрел вашу анкету. Откройте VIP, чтобы узнать кто!';
            $actionUrl = url('/pricing'); // Ведем на страницу покупки VIP
        }

        return [
            'type' => 'profile_view',
            'title' => '👀 Новый просмотр',
            'message' => $message,
            'action_url' => $actionUrl,
            'data' => [
                'viewer_id' => $isVip ? $this->viewer->id : null, // Скрываем ID, если без VIP
                'is_hidden' => !$isVip,
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
        Log::error("Не удалось отправить NewProfileViewNotification (Viewer ID: {$this->viewer->id}): " . $exception->getMessage());
    }
}