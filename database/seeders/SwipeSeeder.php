<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Swipe;
use App\Models\UserMatch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SwipeSeeder extends Seeder
{
    public function run(): void
    {
        // ✅ Исключаем админов из свайпов
        $users = User::excludeAdmins()->pluck('id')->toArray();
        $userCount = count($users);

        if ($userCount < 2) {
            $this->command->warn('Нужно минимум 2 обычных пользователя для создания свайпов.');
            return;
        }

        $this->command->info("Создаём свайпы и матчи для {$userCount} пользователей...");

        $swipesPerUser = 5;
        $maxSwipes = 15;
        $totalSwipes = 0;
        $totalMatches = 0;

        DB::transaction(function () use ($users, $swipesPerUser, $maxSwipes, &$totalSwipes, &$totalMatches) {
            foreach ($users as $userId) {
                $existingSwipes = Swipe::where('user_id', $userId)->count();
                if ($existingSwipes >= 20) {
                    continue;
                }

                $count = rand($swipesPerUser, $maxSwipes);
                $alreadySwiped = Swipe::where('user_id', $userId)->pluck('target_user_id')->toArray();
                $available = array_diff($users, [$userId], $alreadySwiped);
                shuffle($available);
                $targets = array_slice($available, 0, $count);

                foreach ($targets as $targetId) {
                    $rand = rand(1, 100);
                    if ($rand <= 60) {
                        $type = 'like';
                    } elseif ($rand <= 90) {
                        $type = 'dislike';
                    } else {
                        $type = 'superlike';
                    }

                    if (Swipe::where('user_id', $userId)->where('target_user_id', $targetId)->exists()) {
                        continue;
                    }

                    Swipe::create([
                        'user_id' => $userId,
                        'target_user_id' => $targetId,
                        'type' => $type,
                    ]);
                    $totalSwipes++;

                    if (in_array($type, ['like', 'superlike'])) {
                        $mutual = Swipe::where('user_id', $targetId)
                            ->where('target_user_id', $userId)
                            ->whereIn('type', ['like', 'superlike'])
                            ->exists();

                        if ($mutual) {
                            $matchExists = UserMatch::where(function ($q) use ($userId, $targetId) {
                                $q->where('user1_id', $userId)->where('user2_id', $targetId);
                            })->orWhere(function ($q) use ($userId, $targetId) {
                                $q->where('user1_id', $targetId)->where('user2_id', $userId);
                            })->exists();

                            if (!$matchExists) {
                                UserMatch::create([
                                    'user1_id' => min($userId, $targetId),
                                    'user2_id' => max($userId, $targetId),
                                ]);
                                $totalMatches++;
                            }
                        }
                    }
                }
            }
        });

        $this->command->info("✅ Создано свайпов: {$totalSwipes}");
        $this->command->info("✅ Создано матчей: {$totalMatches}");
    }
}