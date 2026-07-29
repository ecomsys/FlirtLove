<?php

namespace App\Actions;

use App\Models\Chat;
use App\Models\ChatParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SendMessageAction
{
    /**
     * Основной метод отправки сообщения.
     * Возвращает массив с результатом (успех/провал, тип сообщения).
     */
    public function execute(User $sender, Chat $chat, string $body): array
    {
        // 1. ЗАЩИТА: Юзер может писать только в свои чаты!
        $isParticipant = ChatParticipant::where('chat_id', $chat->id)
            ->where('user_id', $sender->id)
            ->exists();

        if (!$isParticipant) {
            return ['success' => false, 'message' => 'Вы не являетесь участником этого чата'];
        }

        // Получаем собеседника через наш чистый метод (без Auth::id())
        $recipient = $chat->getPartner($sender->id);

        if (!$recipient) {
            return ['success' => false, 'message' => 'Собеседник не найден.'];
        }

        // 2. АВТОПРОХОД ДЛЯ ЧАТОВ ПОДДЕРЖКИ
        if ($chat->type === 'support') {
            $message = $this->createMessage($chat, $sender, $body);
            return ['success' => true, 'type' => 'text', 'message' => $message];
        }

        // 3. ФИЛЬТРЫ ЧАТА (Кто может писать получателю)
        if (!$this->passesFilter($sender, $recipient)) {
            return ['success' => false, 'message' => 'Пользователь ограничил круг лиц, которые могут ему писать.'];
        }

        // 4. ЛИМИТ БЕСПЛАТНЫХ СООБЩЕНИЙ (PAYWALL)
        // Теперь мы проверяем has_active_premium (учитывает истечение срока подписки)
        $hasMatch = $chat->match()->exists();
        $freeMessagesLimit = $hasMatch ? 3 : 0;

        $userMessagesCount = Message::where('chat_id', $chat->id)
            ->where('sender_id', $sender->id)
            ->where('type', 'text')
            ->count();

        if (!$sender->has_active_premium && $userMessagesCount >= $freeMessagesLimit) {
            $systemMessage = $this->createSystemMessage($chat, 'Вы исчерпали лимит бесплатных сообщений. Для продолжения переписки необходима подписка Premium.', $sender);
            return ['success' => true, 'type' => 'system', 'message' => $systemMessage];
        }

        // 5. УСПЕШНАЯ ОТПРАВКА
        $message = $this->createMessage($chat, $sender, $body);
        return ['success' => true, 'type' => 'text', 'message' => $message];
    }

    /**
     * Проверка, соответствует ли отправитель фильтрам получателя.
     * ВАЖНО: Теперь читает данные из Profile и Preferences!
     */
    private function passesFilter(User $sender, User $recipient): bool
    {
        // Фильтр чата работает ТОЛЬКО если у получателя активен Premium!
        // Бесплатный юзер не может фильтровать сообщения.
        if (!$recipient->has_active_premium || !$recipient->preferences->chat_filter_enabled) {
            return true;
        }

        // Используем наш крутой аксессор chat_filters, который сливает дефолты с БД
        $filters = $recipient->preferences->chat_filters;

        if (!$filters || !is_array($filters)) {
            return true;
        }

        // Теперь мы берем gender, city и birth_date из Profiles!
        $senderProfile = $sender->profile;
        if (!$senderProfile) return true; // Если профиля нет, пропускаем

        if (isset($filters['gender']) && $filters['gender'] !== 'any') {
            if ($senderProfile->gender !== $filters['gender']) return false;
        }

        if (isset($filters['city']) && !empty($filters['city'])) {
            if ($senderProfile->city !== $filters['city']) return false;
        }

        if ($senderProfile->birth_date) {
            $senderAge = $senderProfile->birth_date->age; // Используем наш хелпер age
            if (isset($filters['age_from']) && $senderAge < (int)$filters['age_from']) return false;
            if (isset($filters['age_to']) && $senderAge > (int)$filters['age_to']) return false;
        } else {
            // Если возраст не указан, но фильтр строгий — не пропускаем
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

            $this->updateChatData($chat, $sender, $message->created_at);

            return $message;
        });
    }

    /**
     * Создать системное сообщение (от бота) и обновить счетчики
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

            $this->updateChatData($chat, $sender, $message->created_at);

            return $message;
        });
    }

    /**
     * Обновить время последнего сообщения в чате и счетчик непрочитанных у собеседника
     */
    private function updateChatData(Chat $chat, User $sender, $time): void
    {
        // Обновляем время чата (для сортировки списка диалогов)
        $chat->update(['last_message_at' => $time]);

        // Увеличиваем счетчик непрочитанных у того, кто НЕ отправлял сообщение
        ChatParticipant::where('chat_id', $chat->id)
            ->where('user_id', '!=', $sender->id)
            ->increment('unread_count');
    }
}