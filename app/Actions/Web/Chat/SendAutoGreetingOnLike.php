<?php

namespace App\Actions\Admin\Web\Chat;

use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Интегрируем в контроллер свайпов/симпатий
// Теперь тебе нужно найти в коде место, где обрабатывается лайк (кнопка "Симпатия"). Обычно это SwipeController или LikeController.

// Вот как там должен выглядеть вызов нашего Action:

// php
// В SwipeController (или там, где ты сохраняешь лайк)

// public function like(Request $request)
// {
//     $targetUserId = $request->target_user_id;
//     $user = auth()->user();
    
//     // Проверяем, не лайкали ли мы этого юзера РАНЬШЕ.
//     // Это важно: мы хотим отправить авто-приветствие ТОЛЬКО при ПЕРВОМ лайке.
//     $alreadyLiked = Swipe::where('user_id', $user->id)
//                          ->where('target_user_id', $targetUserId)
//                          ->where('action', 'like')
//                          ->exists();

//     // Сохраняем свайп
//     Swipe::updateOrCreate(
//         ['user_id' => $user->id, 'target_user_id' => $targetUserId],
//         ['action' => 'like']
//     );

//     // Если это первый лайк этому юзеру -> запускаем авто-приветствие
//     if (!$alreadyLiked) {
//         $targetUser = User::find($targetUserId);
//         if ($targetUser) {
//             app(SendAutoGreetingOnLike::class)->execute($user, $targetUser);
//         }
//     }

//     // Дальше идет твоя проверка на мэтч (если нужно)
//     // ...

//     return response()->json(['status' => 'success']);
// }


class SendAutoGreetingOnLike
{
    /**
     * Отправляет авто-приветствие от имени юзера при нажатии кнопки "Симпатия".
     *
     * @param User $sender Юзер, который отправил симпатию
     * @param User $receiver Юзер, которому отправили симпатию
     */
    public function execute(User $sender, User $receiver): void
    {
        // 1. Проверяем, разрешил ли отправитель авто-сообщения
        if (!$sender->preferences || !$sender->preferences->allow_auto_messages) {
            return; 
        }

        // 2. Проверяем, не заблокировал ли получатель отправителя
        if ($receiver->blockedUsers()->where('blocked_id', $sender->id)->exists()) {
            return; 
        }

        // 3. Проверяем, нет ли уже чата между ними (чтобы не слать приветствие повторно)
        // Это важно! Если они уже общались, авто-приветствие не нужно.
        $hash = md5(min($sender->id, $receiver->id) . '-' . max($sender->id, $receiver->id));
        $existingChat = Chat::where('participants_hash', $hash)->first();

        if ($existingChat) {
            return; // Чат уже есть, спамить авто-приветствием не надо
        }

        try {
            $phrases = config('icebreakers.first_message', []);
            if (empty($phrases)) {
                return;
            }
            
            $phrase = $phrases[array_rand($phrases)];

            DB::transaction(function () use ($sender, $receiver, $phrase) {
                
                // Создаем новый чат
                $chat = Chat::getOrCreateBetween($sender->id, $receiver->id);

                // Отправляем сообщение от имени отправителя симпатии
                $message = Message::create([
                    'chat_id'   => $chat->id,
                    'sender_id' => $sender->id,
                    'type'      => 'text',
                    'body'      => $phrase,
                    'status'    => 'approved',
                ]);

                $chat->update(['last_message_at' => now()]);

                // Инкрементим счетчик непрочитанных у получателя
                $chat->participants()
                    ->where('user_id', $receiver->id)
                    ->increment('unread_count');
            });

            Log::info("Авто-приветствие при симпатии отправлено: От #{$sender->id} -> К #{$receiver->id}");

        } catch (\Exception $e) {
            Log::error('Ошибка отправки авто-приветствия: ' . $e->getMessage());
        }
    }
}