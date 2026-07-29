<?php

namespace Database\Seeders;

use App\Models\Chat;
use App\Models\ChatParticipant;
use App\Models\Message;
use App\Models\User;
use App\Models\UserMatch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChatSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('💬 Начинаем генерацию чатов на основе матчей...');

        // ✅ Берем только матчи между обычными пользователями
        $matches = UserMatch::whereHas('user1', function ($q) {
            $q->where('is_admin', false);
        })->whereHas('user2', function ($q) {
            $q->where('is_admin', false);
        })->get();

        if ($matches->isEmpty()) {
            $this->command->warn('⚠️ Нет матчей в базе! Сначала прогоните SwipeSeeder.');
            return;
        }

        $this->command->info("👥 Найдено {$matches->count()} матчей");

        // ✅ Безопасная очистка (без truncate)
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
            $user1 = User::find($match->user1_id);
            $user2 = User::find($match->user2_id);

            if (!$user1 || !$user2) {
                $bar->advance();
                continue;
            }

            // 1. Создаем или получаем чат
            $chat = Chat::getOrCreateBetween($user1, $user2);
            $createdChats++;

            // 2. Генерируем переписку (только если сообщений еще нет)
            if ($chat->messages()->count() === 0) {
                $messagesGenerated = $this->generateConversation($chat, $user1, $user2);
                $createdMessages += $messagesGenerated;
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
        $this->command->info("   │ Тип                     │ Количество │");
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
     */
    private function generateConversation(Chat $chat, User $user1, User $user2): int
    {
        $time = now()->subDays(rand(1, 10));
        $messagesCount = 0;

        $phrases = [
            'Привет! Как дела?',
            'Привет! Отлично, а у тебя?',
            'Чем занимаешься?',
            'Смотрю сериал, а ты?',
            'Какие планы на выходные?',
            'Может, сходим куда-нибудь?',
            'Какая у тебя любимая музыка?',
            'Я люблю путешествовать, а ты?',
            'Как прошел день?',
            'У тебя красивое фото на аватарке!',
            'Чем увлекаешься в свободное время?',
            'Какой твой любимый фильм?',
            'Ты из какого города?',
            'Давно на этом сайте?',
        ];

        $replies = [
            'Отлично! А у тебя?',
            'Неплохо, спасибо)',
            'Хорошо, работаю',
            'Отдыхаю, а ты?',
            'Планирую поездку',
            'Почему бы и нет!',
            'Люблю рок, а ты?',
            'Тоже люблю!',
            'Был обычный день',
            'Спасибо!',
            'Люблю читать и рисовать',
            'Мой любимый - Интерстеллар',
            'Я из Москвы',
            'Недавно, около месяца',
        ];

        // Рандомно решаем, кто начнет диалог
        $isUser1Turn = (bool) rand(0, 1);
        $sender = $isUser1Turn ? $user1 : $user2;
        $recipient = $isUser1Turn ? $user2 : $user1;

        // Проверяем, есть ли у кого-то премиум
        $hasPremium = $user1->is_premium || $user2->is_premium;

        // ============================================
        // СЦЕНАРИЙ 1: Премиум-чат (много сообщений)
        // ============================================
        if ($hasPremium) {
            $messagesCount = rand(5, 12);
            $currentTime = $time->copy();

            for ($i = 0; $i < $messagesCount; $i++) {
                $currentSender = ($i % 2 === 0) ? $sender : $recipient;
                $phrase = ($i % 2 === 0) 
                    ? $phrases[array_rand($phrases)] 
                    : $replies[array_rand($replies)];

                Message::create([
                    'chat_id' => $chat->id,
                    'sender_id' => $currentSender->id,
                    'type' => 'text',
                    'body' => $phrase,
                    'created_at' => $currentTime,
                ]);

                $currentTime->addMinutes(rand(1, 10));
            }

            return $messagesCount;
        }

        // ============================================
        // СЦЕНАРИЙ 2: Бесплатный чат (разные сценарии)
        // ============================================
        // 0: пустой чат
        // 1: одно сообщение
        // 2: два сообщения
        // 3: лимит исчерпан + пейвол
        $scenario = rand(0, 3);
        $currentTime = $time->copy();

        if ($scenario === 0) {
            // Чат пуст
            return 0;
        }

        // Сценарии 1, 2, 3: Пишем сообщения
        for ($i = 0; $i < $scenario; $i++) {
            $phrase = ($i % 2 === 0) 
                ? $phrases[array_rand($phrases)] 
                : $replies[array_rand($replies)];

            Message::create([
                'chat_id' => $chat->id,
                'sender_id' => $sender->id,
                'type' => 'text',
                'body' => $phrase,
                'created_at' => $currentTime,
            ]);
            $messagesCount++;
            $currentTime->addMinutes(2);

            // Иногда собеседник отвечает
            if (rand(0, 1) && $i < $scenario - 1) {
                Message::create([
                    'chat_id' => $chat->id,
                    'sender_id' => $recipient->id,
                    'type' => 'text',
                    'body' => $replies[array_rand($replies)],
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
                'sender_id' => null,
                'type' => 'system',
                'body' => '⚠️ Вы исчерпали лимит бесплатных сообщений. Для продолжения переписки необходима подписка Premium.',
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
            // Если юзер не отправлял последнее сообщение, у него есть непрочитанные
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