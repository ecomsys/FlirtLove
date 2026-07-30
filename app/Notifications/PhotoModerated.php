<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;


// ТАБЛИЦА - КАК ОТСЫЛАЮТЬСЯ УВЕДОМЛЕНИЯ ?
// Действие	    В кабинете БД   Email	      Push	       Почему
// Одобрение	   ✅	       ✅        	✅	        Пользователь должен знать
// Отклонение	   ✅	       ✅	        ✅	        Пользователь должен знать
// Удаление        ✅	       ✅        	❌        	Важно, но не критично


// ЗАУПУСК ВОРКЕРА ОЧЕРЕДИ
// php artisan queue:work - обычный запуск
// php artisan queue:restart - рестар сигнал
// php artisan queue:listen - с автоматическим перезапуском при изменеии в проекте
// php artisan queue:flush - очистка очереди

class PhotoModerated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected ?int $photoId,      // <-- Только ID (или null)
        protected ?int $userId,       // <-- ID пользователя (для надежности)
        protected string $status,
        protected int $count = 1
    ) {}

    /**
     *  Обновляем каналы доставки с учетом настроек юзера
     */
    public function via($notifiable): array
    {
        $channels = ['database']; // В базу (колокольчик) пишем ВСЕГДА

        // Проверяем настройку Email для модерации фото (?? true — дефолт для старых юзеров)
        if (($notifiable->email_settings['on_photo_moderated'] ?? true) && in_array($this->status, ['approved', 'rejected', 'deleted'])) {
            $channels[] = 'mail';
        }

        // Проверяем глобальный тумблер Push (только для approved/rejected)
        if ($notifiable->push_enabled && in_array($this->status, ['approved', 'rejected'])) {
            $channels[] = 'broadcast';
        }

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        $messages = $this->getMessages();
        $photoCountText = $this->count > 1 ? " ({$this->count} шт.)" : '';

        return (new MailMessage)
            ->subject($messages['subject'])
            ->greeting("Здравствуйте, {$notifiable->name}!")
            ->line($messages['body'] . $photoCountText)
            ->when($this->status === 'approved', function ($message) {
                return $message->line('Теперь ваше фото видно всем пользователям.');
            })
            ->when($this->status === 'rejected', function ($message) {
                return $message->line('Пожалуйста, ознакомьтесь с правилами публикации фотографий и попробуйте загрузить другое.');
            })
            ->action('Перейти в профиль', url('/profile'));
    }

    public function toDatabase($notifiable): array
    {
        $messages = $this->getMessages();
        
        return [
            // === УНИФИЦИРОВАННАЯ СТРУКТУРА ===
            'type' => 'photo_moderated',
            'title' => $messages['title'],
            'message' => $messages['message'],
            'action_url' => url('/profile'),
            
            // === СПЕЦИФИЧНЫЕ ДАННЫЕ ===
            'data' => [
                'photo_id' => $this->photoId,
                'user_id' => $this->userId,
                'status' => $this->status,
                'count' => $this->count,
            ]
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        $messages = $this->getMessages();
        
        return new BroadcastMessage([
            // === УНИФИЦИРОВАННАЯ СТРУКТУРА ===
            'type' => 'photo_moderated',
            'title' => $messages['title'],
            'message' => $messages['message'],
            'action_url' => url('/profile'),
            'timestamp' => now()->toDateTimeString(),
            
            // === СПЕЦИФИЧНЫЕ ДАННЫЕ ===
            'data' => [
                'photo_id' => $this->photoId,
                'user_id' => $this->userId,
                'status' => $this->status,
                'count' => $this->count,
            ]
        ]);
    }

    private function getMessages(): array
    {
        $isMass = $this->count > 1;
        
        return match ($this->status) {
            'approved' => [
                'subject' => $isMass ? 'Ваши фотографии одобрены' : 'Ваша фотография одобрена',
                'title' => '✅ ' . ($isMass ? 'Фотографии одобрены' : 'Фотография одобрена'),
                'body' => $isMass ? "Модератор одобрил {$this->count} ваших фотографий." : "Модератор одобрил вашу фотографию.",
                'message' => $isMass ? "Ваши фотографии ({$this->count} шт.) прошли модерацию и теперь видны всем." : "Ваша фотография прошла модерацию и теперь видна всем.",
            ],
            'rejected' => [
                'subject' => $isMass ? 'Ваши фотографии отклонены' : 'Ваша фотография отклонена',
                'title' => '❌ ' . ($isMass ? 'Фотографии отклонены' : 'Фотография отклонена'),
                'body' => $isMass ? "Модератор отклонил {$this->count} ваших фотографий." : "Модератор отклонил вашу фотографию.",
                'message' => $isMass ? "Ваши фотографии не соответствуют правилам сообщества и были удалены." : "Ваша фотография не соответствует правилам сообщества и была удалена.",
            ],
            'deleted' => [
                'subject' => 'Ваша фотография удалена',
                'title' => '🗑️ Фотография удалена',
                'body' => 'Администратор удалил вашу фотографию.',
                'message' => 'Ваша фотография была удалена. Если вы считаете это ошибкой, свяжитесь с поддержкой.',
            ],
            default => [
                'subject' => 'Статус фотографии изменен',
                'title' => 'Статус изменен',
                'body' => 'Статус вашей фотографии был изменен.',
                'message' => 'Статус вашей фотографии был изменен модератором.',
            ],
        };
    }
}