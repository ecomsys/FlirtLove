<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Support\Facades\Log;

class DailyRecommendationsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    // ФИКС: Принимаем массив скаляров!
    public function __construct(
        protected array $recommendedUsers
    ) {}

    /**
     * Каналы доставки с учетом наших НОВЫХ настроек
     */
    public function via($notifiable): array
    {
        $channels = ['database'];

        // ФИКС: Безопасно грузим настройки в воркере
        $prefs = $notifiable->loadMissing('preferences')->preferences;

        if ($prefs && $prefs->email_enabled && $prefs->email_auto_recommendations) {
            $channels[] = 'mail';
        }

        if ($prefs && $prefs->push_enabled && $prefs->push_auto_recommendations) {
            $channels[] = 'broadcast';
        }
       
        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        $mailMessage = (new MailMessage)
            ->subject('💌 У нас есть для вас новые анкеты!')
            ->greeting("Здравствуйте, {$notifiable->name}!")
            ->line('На основе ваших настроек поиска, мы подобрали для вас несколько интересных профилей:');

        // ФИКС: Выводим список из готового массива скаляров (без N+1 к базе)
        foreach ($this->recommendedUsers as $user) {
            $mailMessage->line("• **{$user['name']}**, {$user['age']} лет, {$user['city']}");
        }
        
        return $mailMessage->action('Посмотреть анкеты', url('/search?from=recommendation'));
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'auto_recommendations',
            'title' => '🌟 Новые рекомендации',
            'message' => 'Мы подобрали для вас ' . count($this->recommendedUsers) . ' новых анкет. Заходи смотреть!',
            'action_url' => url('/search?from=recommendation'),          
            'data' => [
                'users' => $this->recommendedUsers
            ]
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'type' => 'auto_recommendations',
            'title' => '🌟 Новые рекомендации',
            'message' => 'Мы подобрали для вас новые анкеты. Заходи смотреть!',
            'action_url' => url('/search?from=recommendation'),
            'timestamp' => now()->toDateTimeString(),
            'data' => [
                // ФИКС: Для пуша достаточно только ID
                'users' => array_column($this->recommendedUsers, 'id')
            ]
        ]);
    }

    /**
     * ЗАЩИТА ОЧЕРЕДИ:
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Не удалось отправить DailyRecommendationsNotification: " . $exception->getMessage());
    }
}