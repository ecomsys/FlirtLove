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
        $this->command->info('Начинаем генерацию чатов на основе матчей...');

        // PostgreSQL: очищаем дочерние таблицы, потом главную
        Message::truncate();
        ChatParticipant::truncate();
        Chat::truncate();

        $matches = UserMatch::excludeAdmins()->get();

        if ($matches->isEmpty()) {
            $this->command->warn('Нет матчей в базе! Сначала прогоните сидеры свайпов и матчей.');
            return;
        }

        $bar = $this->command->getOutput()->createProgressBar($matches->count());
        $bar->start();

        foreach ($matches as $match) {
            $user1 = User::find($match->user1_id);
            $user2 = User::find($match->user2_id);

            if (!$user1 || !$user2) {
                $bar->advance();
                continue;
            }

            // 1. Создаем чат
            $chat = Chat::getOrCreateBetween($user1, $user2);

            // 2. Создаем участников чата
            ChatParticipant::firstOrCreate(
                ['chat_id' => $chat->id, 'user_id' => $user1->id],
                ['unread_count' => rand(0, 5)]
            );
            ChatParticipant::firstOrCreate(
                ['chat_id' => $chat->id, 'user_id' => $user2->id],
                ['unread_count' => rand(0, 5)]
            );

            // 3. Генерируем переписку с учетом разных сценариев
            $this->generateConversation($chat, $user1, $user2);

            $bar->advance();
        }

        $bar->finish();
        $this->command->info("\nГенерация чатов успешно завершена!");
    }

    /**
     * Генерация реалистичной переписки с разными сценариями для теста пейвола
     */
    private function generateConversation(Chat $chat, User $user1, User $user2): void
    {
        $time = now()->subDays(rand(1, 10));

        $phrases = [
            'Привет! Как дела?',
            'Привет! Отлично, а у тебя?',
            'Чем занимаешься?',
            'Смотрю сериал, а ты?',
            'Какие планы на выходные?',
            'Может, сходим куда-нибудь?',
        ];

        // Рандомно решаем, кто начнет диалог
        $isUser1Turn = (bool) rand(0, 1);
        $sender = $isUser1Turn ? $user1 : $user2;
        $recipient = $isUser1Turn ? $user2 : $user1;

        // Если кто-то из них Премиум — генерируем обычный долгий диалог
        if ($sender->is_premium || $recipient->is_premium) {
            $messagesCount = rand(5, 8);
            $currentTime = $time->copy();

            for ($i = 0; $i < $messagesCount; $i++) {
                $currentSender = ($i % 2 === 0) ? $sender : $recipient;
                
                Message::create([
                    'chat_id' => $chat->id,
                    'sender_id' => $currentSender->id,
                    'type' => 'text',
                    'body' => $phrases[array_rand($phrases)],
                    'created_at' => $currentTime,
                ]);

                $currentTime->addMinutes(rand(1, 10));
            }
        } else {
            // Если оба бесплатны — используем сценарии "Дегустации"
            // 0: пустой чат, 1: одно сообщение, 2: два сообщения, 3: лимит исчерпан + пейвол
            $scenario = rand(0, 3);
            $currentTime = $time->copy();

            if ($scenario === 0) {
                // Сценарий 0: Чат пуст. Ничего не пишем, даем юзеру начать с чистого листа.
                return;
            }

            // Сценарии 1, 2 и 3: Пишем сообщения от имени бесплатного отправителя
            for ($i = 0; $i < $scenario; $i++) {
                Message::create([
                    'chat_id' => $chat->id,
                    'sender_id' => $sender->id,
                    'type' => 'text',
                    'body' => $phrases[array_rand($phrases)],
                    'created_at' => $currentTime,
                ]);
                $currentTime->addMinutes(2);

                // Иногда собеседник отвечает, чтобы диалог выглядел живым
                if (rand(0, 1)) {
                    Message::create([
                        'chat_id' => $chat->id,
                        'sender_id' => $recipient->id,
                        'type' => 'text',
                        'body' => $phrases[array_rand($phrases)],
                        'created_at' => $currentTime,
                    ]);
                    $currentTime->addMinutes(2);
                }
            }

            // Если сценарий 3 — добавляем системное сообщение о пейволе
            if ($scenario === 3) {
                Message::create([
                    'chat_id' => $chat->id,
                    'sender_id' => null, // Системный бот
                    'type' => 'system',
                    'body' => 'Вы исчерпали лимит бесплатных сообщений. Для продолжения переписки необходима подписка Premium.',
                    'created_at' => $currentTime,
                ]);
            }
        }

        // Обновляем время последнего сообщения в чате для сортировки
        $lastMessage = $chat->messages()->latest('created_at')->first();
        if ($lastMessage) {
            $chat->update(['last_message_at' => $lastMessage->created_at]);
        }
    }
}