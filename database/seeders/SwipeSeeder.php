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
        // ✅ Берем только обычных пользователей (не админов) и с заполненными профилями
        $users = User::where('is_admin', false)
            ->where('has_completed_onboarding', true)
            ->pluck('id')
            ->toArray();
            
        $userCount = count($users);

        if ($userCount < 2) {
            $this->command->warn('⚠️ Нужно минимум 2 обычных пользователя для создания свайпов.');
            return;
        }

        $this->command->info("👥 Найдено {$userCount} пользователей для свайпов...");

        // Очищаем старые свайпы и матчи (опционально)
        $oldSwipes = Swipe::count();
        $oldMatches = UserMatch::count();
        
        if ($oldSwipes > 0 || $oldMatches > 0) {
            $this->command->info("🗑️ Удаляем старые данные: {$oldSwipes} свайпов, {$oldMatches} матчей");
            Swipe::query()->delete();
            UserMatch::query()->delete();
        }

        $swipesPerUser = 5;
        $maxSwipes = 15;
        $totalSwipes = 0;
        $totalMatches = 0;

        $this->command->info('🔄 Создаем свайпы и матчи...');
        $bar = $this->command->getOutput()->createProgressBar($userCount);

        DB::transaction(function () use ($users, $swipesPerUser, $maxSwipes, &$totalSwipes, &$totalMatches, $bar) {
            foreach ($users as $userId) {
                // Проверяем, сколько уже свайпов у пользователя
                $existingSwipes = Swipe::where('user_id', $userId)->count();
                if ($existingSwipes >= 20) {
                    $bar->advance();
                    continue;
                }

                // Определяем количество свайпов для этого пользователя
                $count = rand($swipesPerUser, $maxSwipes);
                
                // Получаем уже свайпнутых пользователей
                $alreadySwiped = Swipe::where('user_id', $userId)
                    ->pluck('target_user_id')
                    ->toArray();
                
                // Доступные цели (исключаем себя и уже свайпнутых)
                $available = array_diff($users, [$userId], $alreadySwiped);
                shuffle($available);
                $targets = array_slice($available, 0, $count);

                foreach ($targets as $targetId) {
                    // Рандомный тип свайпа
                    $rand = rand(1, 100);
                    if ($rand <= 60) {
                        $type = 'like';
                    } elseif ($rand <= 90) {
                        $type = 'dislike';
                    } else {
                        $type = 'superlike';
                    }

                    // Проверка на дубликат (на всякий случай)
                    if (Swipe::where('user_id', $userId)
                        ->where('target_user_id', $targetId)
                        ->exists()
                    ) {
                        continue;
                    }

                    // Создаем свайп
                    Swipe::create([
                        'user_id' => $userId,
                        'target_user_id' => $targetId,
                        'type' => $type,
                        'created_at' => now()->subDays(rand(0, 30)),
                    ]);
                    $totalSwipes++;

                    // ============================================
                    // ПРОВЕРКА НА МАТЧ (только для like и superlike)
                    // ============================================
                    if (in_array($type, ['like', 'superlike'])) {
                        // Проверяем, есть ли взаимный лайк от target к user
                        $mutual = Swipe::where('user_id', $targetId)
                            ->where('target_user_id', $userId)
                            ->whereIn('type', ['like', 'superlike'])
                            ->exists();

                        if ($mutual) {
                            // Проверяем, существует ли уже матч
                            $matchExists = UserMatch::where(function ($q) use ($userId, $targetId) {
                                $q->where('user1_id', $userId)
                                  ->where('user2_id', $targetId);
                            })->orWhere(function ($q) use ($userId, $targetId) {
                                $q->where('user1_id', $targetId)
                                  ->where('user2_id', $userId);
                            })->exists();

                            if (!$matchExists) {
                                // Создаем матч (user1_id < user2_id для консистентности)
                                UserMatch::create([
                                    'user1_id' => min($userId, $targetId),
                                    'user2_id' => max($userId, $targetId),
                                    'created_at' => now()->subDays(rand(0, 20)),
                                ]);
                                $totalMatches++;
                            }
                        }
                    }
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->command->newLine(2);

        // ============================================
        // СТАТИСТИКА
        // ============================================
        $stats = [
            'total_swipes' => Swipe::count(),
            'total_matches' => UserMatch::count(),
            'likes' => Swipe::where('type', 'like')->count(),
            'dislikes' => Swipe::where('type', 'dislike')->count(),
            'superlikes' => Swipe::where('type', 'superlike')->count(),
            'users_with_swipes' => Swipe::distinct('user_id')->count(),
        ];

        $this->command->info('✅ Создано свайпов: ' . $stats['total_swipes']);
        $this->command->info('✅ Создано матчей: ' . $stats['total_matches']);
        $this->command->info('');
        $this->command->info('📊 Статистика:');
        $this->command->info("   ┌─────────────────────┬──────────┐");
        $this->command->info("   │ Тип                 │ Количество │");
        $this->command->info("   ├─────────────────────┼──────────┤");
        $this->command->info("   │ Всего свайпов       │ {$stats['total_swipes']}        │");
        $this->command->info("   │ Лайки               │ {$stats['likes']}        │");
        $this->command->info("   │ Дизлайки            │ {$stats['dislikes']}        │");
        $this->command->info("   │ Суперлайки          │ {$stats['superlikes']}        │");
        $this->command->info("   ├─────────────────────┼──────────┤");
        $this->command->info("   │ Всего матчей        │ {$stats['total_matches']}        │");
        $this->command->info("   │ Пользователей       │ {$stats['users_with_swipes']}        │");
        $this->command->info("   └─────────────────────┴──────────┘");

        // ============================================
        // ДОПОЛНИТЕЛЬНО: Показываем несколько примеров матчей
        // ============================================
        if ($stats['total_matches'] > 0) {
            $exampleMatches = UserMatch::with(['user1', 'user2'])
                ->limit(3)
                ->get();

            if ($exampleMatches->isNotEmpty()) {
                $this->command->info('');
                $this->command->info('💑 Примеры матчей:');
                foreach ($exampleMatches as $match) {
                    $this->command->info("   - {$match->user1->name} ↔ {$match->user2->name}");
                }
            }
        }
    }
}