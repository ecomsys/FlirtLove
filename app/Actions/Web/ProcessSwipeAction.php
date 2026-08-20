<?php

namespace App\Actions\Web;

use App\Models\Swipe;
use App\Models\User;
use App\Models\UserMatch;
use App\Models\Chat;
use Illuminate\Support\Facades\DB;

class ProcessSwipeAction
{
    /**
     * Обработать свайп и проверить взаимность.
     *
     * @param User $swiper Кто свайпает
     * @param User $target Кого свайпают
     * @param string $type Тип свайпа (like, dislike, superlike)
     * @return array Результат операции
     */
    public function execute(User $swiper, User $target, string $type): array
    {
        // 1. Базовая защита: нельзя свайпать себя
        if ($swiper->id === $target->id) {
            return ['success' => false, 'message' => 'Вы не можете оценить себя'];
        }

        // 2. Проверка: не свайпал ли уже? (Опираемся на unique индекс в БД)
        $existingSwipe = Swipe::where('user_id', $swiper->id)
            ->where('target_user_id', $target->id)
            ->first();

        if ($existingSwipe) {
            return ['success' => false, 'message' => 'Вы уже оценили этого пользователя'];
        }

        // 3. Всё серьезное делаем внутри транзакции!
        // Если что-то упадет (например, при создании чата), свайп тоже откатится назад.
        return DB::transaction(function () use ($swiper, $target, $type) {
            
            // Создаем свайп
            $swipe = Swipe::create([
                'user_id' => $swiper->id,
                'target_user_id' => $target->id,
                'type' => $type,
            ]);

            // Если это дизлайк — просто сохраняем и уходим
            if ($type === 'dislike') {
                return ['success' => true, 'is_match' => false, 'message' => 'Дизлайк сохранён'];
            }

            // 4. Увеличиваем счетчик лайков на анкете цели (Теперь в Profile!)
            // increment() делает это атомарно, без перезаписи всей модели
            $target->profile?->increment('likes_count');

            // 5. Проверяем Взаимность (Мэтч)
            $isMutual = Swipe::where('user_id', $target->id)
                ->where('target_user_id', $swiper->id)
                ->positive() // Используем наш scope из модели Swipe
                ->exists();

            if ($isMutual) {
                // МАТЧ! 
                // ВАЖНО: Используем firstOrCreate с unique индексом ['user1_id', 'user2_id']
                // Это 100% защита от Race Condition. Если два запроса придут одновременно,
                // БД разрешит вставить только один мэтч, второй просто его " найдет".
                $match = UserMatch::firstOrCreate([
                    'user1_id' => min($swiper->id, $target->id),
                    'user2_id' => max($swiper->id, $target->id),
                ]);

                // Создаем чат ТОЛЬКО если мэтч действительно новый (не был найден как существующий)
                // Проверяем wasRecentlyCreated — это флаг Laravel
                if ($match->wasRecentlyCreated) {
                    Chat::getOrCreateBetween($swiper, $target);
                }

                return [
                    'success' => true, 
                    'is_match' => true, 
                    'match_id' => $match->id,
                    'message' => 'Взаимный лайк! У вас новый мэтч!'
                ];
            }

            // Если просто лайк, без взаимности
            return ['success' => true, 'is_match' => false, 'message' => 'Лайк отправлен'];
        });
    }
}