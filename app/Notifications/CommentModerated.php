<?php

namespace App\Notifications;

use App\Models\PhotoComment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

// В кабинете	toDatabase()	Сохраняется в БД
// Email    	toMail()    	Отправляется через SMTP (в будующем сейчас в логи)
// Push	        toBroadcast()	Отправляется через WebSockets (в будующем сейчас в логи)

// ТАБЛИЦА - КАК ОТСЫЛАЮТЬСЯ УВЕДОМЛЕНИЯ ?
// Действие	    В кабинете БД   Email	      Push	       Почему
// Одобрение	   ✅	       ✅        	✅	        Пользователь должен знать
// Отклонение	   ✅	       ✅	        ✅	        Пользователь должен знать
// Спам	           ✅           ❌        	❌	        Не спамим спамера
// Удаление        ✅	       ✅        	❌        	Важно, но не критично
// Восстановление  ✅	       ❌	        ❌	        Внутреннее действие

// ЗАУПУСК ВОРКЕРА ОЧЕРЕДИ
// php artisan queue:work - обычный запуск
// php artisan queue:restart - рестар сигнал
// php artisan queue:listen - с автоматическим перезапуском при изменеии в проекте
// php artisan queue:flush - очистка очереди

class CommentModerated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected PhotoComment $comment,
        protected string $status // approved, rejected, spam, deleted, restored
    ) {}

    /**
     *  Обновляем каналы доставки с учетом настроек юзера
     */
    public function via($notifiable): array
    {
        // 1. В кабинет (БД) отправляем ВСЕГДА
        $channels = ['database'];

        // 2. Email: отправляем ВСЕГДА, КРОМЕ "spam" и "restored", И ЕСЛИ ВКЛЮЧЕНА НАСТРОЙКА on_photo_moderated
        if (($notifiable->email_settings['on_photo_moderated'] ?? true) && !in_array($this->status, ['spam', 'restored'])) {
            $channels[] = 'mail';
        }

        // 3. Push (Broadcast): отправляем ТОЛЬКО при "approved" и "rejected", И ЕСЛИ ВКЛЮЧЕН ГЛОБАЛЬНЫЙ ТУМБЛЕР push_enabled
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
                return $message->line('Теперь он виден всем пользователям.');
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
            'type' => 'comment_moderated',
            'comment_id' => $this->comment->id,
            'photo_id' => $this->comment->photo_id,
            'status' => $this->status,
            'title' => $messages['title'],
            'message' => $messages['message'],
            'content' => $this->comment->content,
        ];
    }

    /*
    * Добавляем Push уведомления    
    */    
    public function toBroadcast($notifiable): BroadcastMessage
    {
        $messages = $this->getMessages();
        
        return new BroadcastMessage([
            'type' => 'comment_moderated',
            'comment_id' => $this->comment->id,
            'photo_id' => $this->comment->photo_id,
            'status' => $this->status,
            'title' => $messages['title'],
            'message' => $messages['message'],
            'content' => $this->comment->content,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    private function getMessages(): array
    {
        return match ($this->status) {
            'approved' => [
                'subject' => 'Ваш комментарий одобрен',
                'title' => '✅ Комментарий одобрен',
                'body' => 'Ваш комментарий был одобрен модератором.',
                'message' => 'Ваш комментарий был одобрен модератором и теперь виден всем.',
            ],
            'rejected' => [
                'subject' => 'Ваш комментарий отклонен',
                'title' => '❌ Комментарий отклонен',
                'body' => 'Ваш комментарий был отклонен модератором.',
                'message' => 'Ваш комментарий был отклонен модератором. Вы можете отредактировать его и отправить снова.',
            ],
            'spam' => [
                'subject' => 'Ваш комментарий помечен как спам',
                'title' => '🚫 Комментарий помечен как спам',
                'body' => 'Ваш комментарий был помечен как спам.',
                'message' => 'Ваш комментарий был помечен как спам. Пожалуйста, ознакомьтесь с правилами сообщества.',
            ],
            'deleted' => [
                'subject' => 'Ваш комментарий удален',
                'title' => '🗑️ Комментарий удален',
                'body' => 'Ваш комментарий был удален модератором.',
                'message' => 'Ваш комментарий был удален модератором. Если вы считаете, что это ошибка, свяжитесь с поддержкой.',
            ],
            'restored' => [
                'subject' => 'Ваш комментарий восстановлен',
                'title' => '🔄 Комментарий восстановлен',
                'body' => 'Ваш комментарий был восстановлен модератором.',
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
}