<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Swipe;
use App\Models\UserMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SwipeController extends Controller
{
    public function store(Request $request, User $user)
    {
        $validated = $request->validate([
            'type' => 'required|in:like,dislike,superlike'
        ]);

        /** @var \App\Models\User $authUser */
        $authUser = $request->user();
        
        $authId = $authUser->id;
        $targetId = $user->id;

        if ($authId === $targetId) {
            return response()->json(['success' => false, 'message' => 'Нельзя свайпать себя'], 400);
        }

        return DB::transaction(function () use ($validated, $authId, $targetId) {

            // 1. Ищем существующий свайп
            $swipe = Swipe::where('user_id', $authId)
                ->where('target_user_id', $targetId)
                ->lockForUpdate()
                ->first();

            if ($swipe) {
                $swipe->update([
                    'type' => $validated['type'],
                    'rewinded_at' => null
                ]);
            } else {
                $swipe = Swipe::create([
                    'user_id' => $authId,
                    'target_user_id' => $targetId,
                    'type' => $validated['type']
                ]);
            }

            $isMatch = false;

            // 2. Если юзер ставит Лайк/Суперлайк
            if (in_array($validated['type'], [Swipe::TYPE_LIKE, Swipe::TYPE_SUPERLIKE])) {

                // Проверяем, есть ли ответный активный лайк от цели
                $reverseSwipe = Swipe::where('user_id', $targetId)
                    ->where('target_user_id', $authId)
                    ->active()
                    ->positive()
                    ->exists();

                if ($reverseSwipe) {
                    // Используем твой метод создания/реактивации мэтча
                    UserMatch::createMatch($authId, $targetId);
                    $isMatch = true;
                }
            } else {
                // 3. НОВАЯ ЛОГИКА: Если юзер ставит ДИЗЛАЙК

                // Проверяем, были ли они сматчены до этого
                $user1Id = min($authId, $targetId);
                $user2Id = max($authId, $targetId);

                /** @var UserMatch|null $existingMatch */
                $existingMatch = UserMatch::where('user1_id', $user1Id)
                    ->where('user2_id', $user2Id)
                    ->active() // Проверяем только активные мэтчи
                    ->first();

                // Если мэтч был — разрываем его! (Вызываем твой метод из модели UserMatch)
                if ($existingMatch) {
                    $existingMatch->unmatch($authId);

                    return response()->json([
                        'success' => true,
                        'type' => 'dislike',
                        'is_match' => false,
                        'unmatched' => true,
                        'message' => 'Вы отменили лайк. Мэтч разорван.'
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'type' => $validated['type'],
                'is_match' => $isMatch,
                'message' => $isMatch ? 'У вас мэтч!' : null
            ]);
        });
    }
}