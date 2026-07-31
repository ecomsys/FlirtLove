<?php 

// Обычный лайк (без мэтча) — это отличный повод соблазнить юзера купить VIP-подписку. В дейтингах часто делают так: 
// если ты без VIP, тебе приходит уведомление "Вам кто-то симпатизировал", но имя скрывается. А с VIP — показывается 
// имя и ссылка на анкету.

// Мы реализуем эту киллер-фичу прямо в уведомлении, используя наш хелпер $notifiable->hasActivePremium().

// Разбор архитектуры (Монетизация):

// Проверка VIP (hasActivePremium()): Мы используем хелпер, который написали в самом начале в модели User. 
// Если юзер без VIP, мы намеренно скрываем $this->liker->id и имя, меняя текст на "Кто-то симпатизировал".
// Редирект на Pricing: Если юзер без VIP, кнопка в письме и клик в колокольчике ведут его не на страницу лайков, 
// а на url('/pricing') (страница покупки тарифов). Это классический паттерн монетизации дейтинга.
// Суперлайки: Добавлен флаг $isSuperlike, чтобы выделять такие уведомления визуально (звездочкой ⭐), 
// так как суперлайков дается мало (5 в день), и они ценятся выше.

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Support\Facades\Log;

class NewLikeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected User $liker,
        protected bool $isSuperlike = false
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
        $mail = (new MailMessage)
            ->subject($this->isSuperlike ? 'Вам отправили Суперлайк! ⭐' : 'Вам кто-то симпатизировал! ❤️')
            ->greeting("Здравствуйте, {$notifiable->name}!");

        if ($notifiable->hasActivePremium()) {
            $likerName = $this->liker->name ?? 'Пользователь';
            $mail->line("Пользователь {$likerName} проявил к вам симпатию" . ($this->isSuperlike ? ' (Суперлайк)!' : '.'))
                 ->action('Посмотреть анкету', url('/profile/' . $this->liker->id));
        } else {
            $mail->line("Кто-то проявил к вам симпатию" . ($this->isSuperlike ? ' (Суперлайк)!' : '!'))
                 ->line('Откройте VIP-статус, чтобы узнать, кто именно оценил ваши фото.');
        }

        return $mail;
    }

    /**
     *  Запись в БД (Колокольчик)
     */
    public function toDatabase($notifiable): array
    {
        $isVip = $notifiable->hasActivePremium();
        
        $title = $this->isSuperlike ? '⭐ Суперлайк!' : '❤️ Новая симпатия';
        
        if ($isVip) {
            $likerName = $this->liker->name ?? 'Пользователь';
            $message = "{$likerName} симпатизировал(а) вам" . ($this->isSuperlike ? ' (Суперлайк)!' : '.');
            $actionUrl = url('/profile/' . $this->liker->id);
        } else {
            $message = 'Кто-то симпатизировал вам. Откройте VIP, чтобы увидеть!';
            $actionUrl = url('/pricing'); // Ведем на страницу покупки VIP
        }

        return [
            'type' => 'new_like',
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'data' => [
                'liker_id' => $isVip ? $this->liker->id : null, // Скрываем ID, если без VIP
                'is_superlike' => $this->isSuperlike,
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
        Log::error("Не удалось отправить NewLikeNotification (Liker ID: {$this->liker->id}): " . $exception->getMessage());
    }
}
