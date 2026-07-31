<?php

namespace Database\Seeders;

use App\Models\Chat;
use App\Models\User;
use App\Models\ChatParticipant;
use App\Models\Message;
use App\Models\UserMatch;
use Illuminate\Database\Seeder;

class ChatSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('💬 Начинаем генерацию чатов на основе матчей...');

        // ✅ Берем только матчи между обычными пользователями (role = 'user')
        $matches = UserMatch::whereHas('user1', function ($q) {
            $q->where('role', 'user');
        })->whereHas('user2', function ($q) {
            $q->where('role', 'user');
        })->get();

        if ($matches->isEmpty()) {
            $this->command->warn('⚠️ Нет матчей в базе! Сначала прогоните SwipeSeeder.');
            return;
        }

        $this->command->info("👥 Найдено {$matches->count()} матчей");

        // Безопасная очистка
        $oldMessages = Message::count();
        $oldParticipants = ChatParticipant::count();
        $oldChats = Chat::count();

        if ($oldMessages > 0 || $oldParticipants > 0 || $oldChats > 0) {
            $this->command->info("🗑️ Удаляем старые данные: {$oldChats} чатов, {$oldParticipants} участников, {$oldMessages} сообщений");
            Message::query()->delete();
            ChatParticipant::query()->delete();
            Chat::query()->delete();
        }

        $bar = $this->command->getOutput()->createProgressBar($matches->count());
        $bar->start();

        $createdChats = 0;
        $createdMessages = 0;

        foreach ($matches as $match) {
            $user1Id = $match->user1_id;
            $user2Id = $match->user2_id;

            // 1. Создаем или получаем чат (Передаем ID, а не объекты!)
            $chat = Chat::getOrCreateBetween($user1Id, $user2Id);
            $createdChats++;

            // 2. Генерируем переписку (только если сообщений еще нет)
            if ($chat->messages()->count() === 0) {
                $createdMessages += $this->generateConversation($chat, $user1Id, $user2Id);
            }

            // 3. Обновляем счетчики непрочитанных
            $this->updateUnreadCounts($chat);

            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine(2);

        // ============================================
        // СТАТИСТИКА
        // ============================================
        $stats = [
            'chats' => Chat::count(),
            'participants' => ChatParticipant::count(),
            'messages' => Message::count(),
            'private_chats' => Chat::where('type', 'private')->count(),
            'support_chats' => Chat::where('type', 'support')->count(),
            'system_messages' => Message::where('type', 'system')->count(),
            'text_messages' => Message::where('type', 'text')->count(),
        ];

        $this->command->info('✅ Генерация чатов завершена!');
        $this->command->info('');
        $this->command->info('📊 Статистика:');
        $this->command->info("   ┌─────────────────────────┬──────────┐");
        $this->command->info("   │ Тип                     │ Кол-во   │");
        $this->command->info("   ├─────────────────────────┼──────────┤");
        $this->command->info("   │ Всего чатов             │ {$stats['chats']}        │");
        $this->command->info("   │ Приватные               │ {$stats['private_chats']}        │");
        $this->command->info("   │ Поддержка               │ {$stats['support_chats']}        │");
        $this->command->info("   ├─────────────────────────┼──────────┤");
        $this->command->info("   │ Участников              │ {$stats['participants']}        │");
        $this->command->info("   │ Всего сообщений         │ {$stats['messages']}        │");
        $this->command->info("   │ Текстовых               │ {$stats['text_messages']}        │");
        $this->command->info("   │ Системных               │ {$stats['system_messages']}        │");
        $this->command->info("   └─────────────────────────┴──────────┘");
    }

    /**
     * Генерация реалистичной переписки с разными сценариями
     * (Передаем ID юзеров для оптимизации памяти)
     */
    private function generateConversation(Chat $chat, int $user1Id, int $user2Id): int
    {
        $time = now()->subDays(rand(1, 10));
        $messagesCount = 0;

        $phrases = [
            'Привет! Как дела?', 'Привет! Отлично, а у тебя?', 'Чем занимаешься?',
            'Смотрю сериал, а ты?', 'Какие планы на выходные?', 'Может, сходим куда-нибудь?',
            'Какая у тебя любимая музыка?', 'Я люблю путешествовать, а ты?', 'Как прошел день?',
            'У тебя красивое фото на аватарке!', 'Чем увлекаешься в свободное время?',
            'Какой твой любимый фильм?', 'Ты из какого города?', 'Давно на этом сайте?',
        ];

        $replies = [
            'Отлично! А у тебя?', 'Неплохо, спасибо)', 'Хорошо, работаю', 'Отдыхаю, а ты?',
            'Планирую поездку', 'Почему бы и нет!', 'Люблю рок, а ты?', 'Тоже люблю!',
            'Был обычный день', 'Спасибо!', 'Люблю читать и рисовать', 'Мой любимый - Интерстеллар',
            'Я из Москвы', 'Недавно, около месяца',
        ];

        // Рандомно решаем, кто начнет диалог
        $isUser1Turn = (bool) rand(0, 1);
        $senderId = $isUser1Turn ? $user1Id : $user2Id;
        $recipientId = $isUser1Turn ? $user2Id : $user1Id;

        // Проверяем, есть ли у кого-то премиум (через быстрый запрос)
        $hasPremium = User::whereIn('id', [$user1Id, $user2Id])->where('is_premium', true)->exists();

        // ============================================
        // СЦЕНАРИЙ 1: Премиум-чат (много сообщений)
        // ============================================
        if ($hasPremium) {
            $messagesCount = rand(5, 12);
            $currentTime = $time->copy();

            for ($i = 0; $i < $messagesCount; $i++) {
                $currentSenderId = ($i % 2 === 0) ? $senderId : $recipientId;
                $phrase = ($i % 2 === 0) ? $phrases[array_rand($phrases)] : $replies[array_rand($replies)];

                Message::create([
                    'chat_id' => $chat->id,
                    'sender_id' => $currentSenderId,
                    'type' => 'text',
                    'body' => $phrase,
                    'status' => 'approved', // Явно указываем статус
                    'created_at' => $currentTime,
                ]);

                $currentTime->addMinutes(rand(1, 10));
            }

            return $messagesCount;
        }

        // ============================================
        // СЦЕНАРИЙ 2: Бесплатный чат (разные сценарии)
        // ============================================
        $scenario = rand(0, 3);
        $currentTime = $time->copy();

        if ($scenario === 0) {
            return 0; // Чат пуст
        }

        for ($i = 0; $i < $scenario; $i++) {
            $phrase = ($i % 2 === 0) ? $phrases[array_rand($phrases)] : $replies[array_rand($replies)];

            Message::create([
                'chat_id' => $chat->id,
                'sender_id' => $senderId,
                'type' => 'text',
                'body' => $phrase,
                'status' => 'approved', // Явно указываем статус
                'created_at' => $currentTime,
            ]);
            $messagesCount++;
            $currentTime->addMinutes(2);

            if (rand(0, 1) && $i < $scenario - 1) {
                Message::create([
                    'chat_id' => $chat->id,
                    'sender_id' => $recipientId,
                    'type' => 'text',
                    'body' => $replies[array_rand($replies)],
                    'status' => 'approved', // Явно указываем статус
                    'created_at' => $currentTime,
                ]);
                $messagesCount++;
                $currentTime->addMinutes(2);
            }
        }

        // Сценарий 3: добавляем системное сообщение о пейволе
        if ($scenario === 3) {
            Message::create([
                'chat_id' => $chat->id,
                'sender_id' => null, // Системные сообщения без отправителя
                'type' => 'system',
                'body' => '⚠️ Вы исчерпали лимит бесплатных сообщений. Для продолжения переписки необходима подписка Premium.',
                'status' => 'approved', // Явно указываем статус
                'created_at' => $currentTime,
            ]);
            $messagesCount++;
        }

        return $messagesCount;
    }

    /**
     * Обновить счетчики непрочитанных сообщений
     */
    private function updateUnreadCounts(Chat $chat): void
    {
        $participants = $chat->participants;
        $lastMessage = $chat->messages()->latest('created_at')->first();

        if (!$lastMessage) {
            return;
        }

        foreach ($participants as $participant) {
            if ($participant->user_id !== $lastMessage->sender_id) {
                $unreadCount = $chat->messages()
                    ->where('sender_id', '!=', $participant->user_id)
                    ->where('created_at', '>', $participant->last_read_at ?? now()->subYear())
                    ->count();

                if ($unreadCount > 0) {
                    $participant->update(['unread_count' => $unreadCount]);
                }
            }
        }
    }
}