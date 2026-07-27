<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use App\Models\ChatParticipant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ChatService
{
    /**
     * Основной метод отправки сообщения.
     * Возвращает массив с результатом (успех/провал, тип сообщения).
     */
    public function sendMessage(User $sender, Chat $chat, string $body): array
    {
        $recipient = $chat->other_user;

        if (!$recipient) {
            return ['success' => false, 'message' => 'Собеседник не найден.'];
        }

        // ✅ АВТОПРОХОД ДЛЯ ЧАТОВ ПОДДЕРЖКИ
        if ($chat->type === 'support') {
            $message = $this->createMessage($chat, $sender, $body);
            return ['success' => true, 'type' => 'text', 'message' => $message];
        }

        if (!$this->passesFilter($sender, $recipient)) {
            return ['success' => false, 'message' => 'Пользователь ограничил круг лиц, которые могут ему писать.'];
        }

        $hasMatch = $chat->match()->exists();
        $freeMessagesLimit = $hasMatch ? 3 : 0;

        $userMessagesCount = Message::where('chat_id', $chat->id)
            ->where('sender_id', $sender->id)
            ->where('type', 'text')
            ->count();

        if (!$sender->is_premium && $userMessagesCount >= $freeMessagesLimit) {
            // ✅ ПЕРЕДАЕМ $sender В МЕТОД
            $systemMessage = $this->createSystemMessage($chat, 'Вы исчерпали лимит бесплатных сообщений. Для продолжения переписки необходима подписка Premium.', $sender);

            return ['success' => true, 'type' => 'system', 'message' => $systemMessage];
        }

        $message = $this->createMessage($chat, $sender, $body);
        return ['success' => true, 'type' => 'text', 'message' => $message];
    }

    /**
     * Проверка, соответствует ли отправитель фильтрам получателя.
     */
    private function passesFilter(User $sender, User $recipient): bool
    {
        if (!$recipient->chat_filter_enabled) {
            return true;
        }

        $filters = $recipient->chat_filter_settings;

        if (!$filters || !is_array($filters)) {
            return true;
        }

        if (isset($filters['gender']) && $filters['gender'] !== 'any') {
            if ($sender->gender !== $filters['gender']) return false;
        }

        if (isset($filters['city']) && !empty($filters['city'])) {
            if ($sender->city !== $filters['city']) return false;
        }

        if ($sender->birth_date) {
            $senderAge = $sender->birth_date->age;
            if (isset($filters['age_from']) && $senderAge < (int)$filters['age_from']) return false;
            if (isset($filters['age_to']) && $senderAge > (int)$filters['age_to']) return false;
        } else {
            if (isset($filters['age_from']) || isset($filters['age_to'])) return false;
        }

        return true;
    }

    /**
     * Создать текстовое сообщение и обновить счетчики
     */
    private function createMessage(Chat $chat, User $sender, string $body): Message
    {
        return DB::transaction(function () use ($chat, $sender, $body) {
            $message = Message::create([
                'chat_id' => $chat->id,
                'sender_id' => $sender->id,
                'type' => 'text',
                'body' => $body,
            ]);

            $this->updateChatTimestamps($chat, $sender, $message->created_at);

            return $message;
        });
    }

    /**
     * Создать системное сообщение (от бота)
     */
    private function createSystemMessage(Chat $chat, string $body, User $sender): Message
    {
        return DB::transaction(function () use ($chat, $body, $sender) {
            $message = Message::create([
                'chat_id' => $chat->id,
                'sender_id' => null, // null = Системный бот
                'type' => 'system',
                'body' => $body,
            ]);

            // ✅ ИСПОЛЬЗУЕМ ПЕРЕДАННЫЙ $sender, А НЕ Auth::user()
            $this->updateChatTimestamps($chat, $sender, $message->created_at);

            return $message;
        });
    }

    /**
     * Обновить время последнего сообщения в чате и счетчик непрочитанных
     */
    private function updateChatTimestamps(Chat $chat, User $sender, $time): void
    {
        $chat->update(['last_message_at' => $time]);

        ChatParticipant::where('chat_id', $chat->id)
            ->where('user_id', '!=', $sender->id)
            ->increment('unread_count');
    }
}