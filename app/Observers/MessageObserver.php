<?php

namespace App\Observers;

use App\Models\Message;
use Illuminate\Support\Facades\DB;

class MessageObserver
{
    /**
     * Handle the Message "created" event.
     */
    public function created(Message $message): void
    {
        // 1. Обновляем время последнего сообщения в чате (для сортировки списка диалогов)
        // Используем прямой запрос, чтобы не загружать всю модель Chat
        DB::table('chats')->where('id', $message->chat_id)->update([
            'last_message_at' => now()
        ]);

        // 2. Инкрементим счетчик непрочитанных у получателя (СБОРЩИК МУСОРА ОТМЕНЯЕТСЯ!)
        // Важно: делаем атомарный инкремент через DB::raw, чтобы при параллельной отправке 
        // не потерять счетчик (классический $participant->unread_count + 1 может перетереться).
        
        // Также: если получатель архивировал чат (is_hidden = true), 
        // новое сообщение должно вытащить чат из архива обратно в активные.
        DB::table('chat_participants')
            ->where('chat_id', $message->chat_id)
            ->where('user_id', '!=', $message->sender_id) // Получатель — это НЕ отправитель
            ->update([
                'unread_count' => DB::raw('unread_count + 1'),
                'is_hidden' => false // Вытаскиваем из архива при новом сообщении
            ]);
    }
}