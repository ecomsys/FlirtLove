<?php

namespace Database\Seeders;

use App\Models\Photo;
use App\Models\User;
use App\Models\PhotoComment;
use Illuminate\Database\Seeder;

class PhotoCommentSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $photos = Photo::where('status', 'approved')->get();

        if ($users->isEmpty() || $photos->isEmpty()) {
            $this->command->warn('⚠️ Нет пользователей или фото для комментариев!');
            return;
        }

        // ✅ Очищаем старые комментарии
        PhotoComment::truncate();
        $this->command->info('🗑️ Старые комментарии удалены');

        $this->command->info('💬 Создаем комментарии к фото...');

        $statuses = ['pending', 'approved', 'rejected', 'spam'];
        $comments = [
            'Красивое фото! 😍', 'Отличный снимок!', 'Где сделано?',
            'Очень атмосферно!', 'Прекрасный кадр!', 'Выглядит профессионально!',
            'Мне нравится! 👍', 'Класс!', 'Супер! 🔥',
            'Необычный ракурс!', 'Потрясающе!', 'Какая красота!',
            'Очень понравилось!', 'Хочу туда же!', 'Просто вау! 🤩',
            'Отличная композиция!', 'Спасибо за фото!', 'У вас хороший вкус!',
            'Вдохновляюще!', '😊 Прекрасно!',
        ];

        $bar = $this->command->getOutput()->createProgressBar(40);
        $createdCount = 0;

        // ============================================
        // 1. ОДИНОЧНЫЕ КОММЕНТАРИИ (20 шт) - КОРНЕВЫЕ
        // ============================================
        for ($i = 0; $i < 20; $i++) {
            $photo = $photos->random();
            $user = $users->random();

            PhotoComment::create([
                'photo_id' => $photo->id,
                'user_id' => $user->id,
                'content' => $comments[array_rand($comments)],
                'status' => $statuses[array_rand($statuses)],
                'parent_id' => null,           // ✅ КОРНЕВОЙ
                'likes_count' => rand(0, 10),
                'created_at' => now()->subDays(rand(0, 10)),
            ]);

            $createdCount++;
            $bar->advance();
        }

        // ============================================
        // 2. КОММЕНТАРИИ С ОТВЕТАМИ (10 шт)
        //    Родительский комментарий ВСЕГДА APPROVED
        //    Ответы могут быть любыми (parent_id = id родителя)
        // ============================================
        for ($i = 0; $i < 10; $i++) {
            $photo = $photos->random();
            $user = $users->random();
            $replyUser = $users->random();

            // ✅ Родительский комментарий — КОРНЕВОЙ, всегда APPROVED
            $parent = PhotoComment::create([
                'photo_id' => $photo->id,
                'user_id' => $user->id,
                'content' => $comments[array_rand($comments)] . ' (родитель)',
                'status' => 'approved',        // ✅ Только approved!
                'parent_id' => null,           // ✅ КОРНЕВОЙ
                'likes_count' => rand(0, 10),
                'created_at' => now()->subDays(rand(0, 10)),
            ]);

            // ✅ Ответ — parent_id = id родителя
            PhotoComment::create([
                'photo_id' => $photo->id,
                'user_id' => $replyUser->id,
                'content' => 'Ответ: ' . $comments[array_rand($comments)],
                'status' => $statuses[array_rand($statuses)],
                'parent_id' => $parent->id,    // ✅ ССЫЛКА НА РОДИТЕЛЯ
                'likes_count' => rand(0, 5),
                'created_at' => now()->subDays(rand(0, 5)),
            ]);

            // Иногда второй ответ
            if (rand(0, 1)) {
                $replyUser2 = $users->random();
                PhotoComment::create([
                    'photo_id' => $photo->id,
                    'user_id' => $replyUser2->id,
                    'content' => 'Еще один ответ: ' . $comments[array_rand($comments)],
                    'status' => $statuses[array_rand($statuses)],
                    'parent_id' => $parent->id,
                    'likes_count' => rand(0, 3),
                    'created_at' => now()->subDays(rand(0, 3)),
                ]);
            }

            $createdCount += 2; // parent + reply
            $bar->advance(2);
        }

        // ============================================
        // 3. ДОПОЛНИТЕЛЬНЫЕ СЦЕНАРИИ
        // ============================================

        // 3.1. Ответы на существующие корневые комментарии
        for ($i = 0; $i < 3; $i++) {
            $existingParent = PhotoComment::where('status', 'approved')
                ->whereNull('parent_id')
                ->inRandomOrder()
                ->first();

            if ($existingParent) {
                $replyUser = $users->random();
                PhotoComment::create([
                    'photo_id' => $existingParent->photo_id,
                    'user_id' => $replyUser->id,
                    'content' => 'Ответ на популярный комментарий: ' . $comments[array_rand($comments)],
                    'status' => $statuses[array_rand($statuses)],
                    'parent_id' => $existingParent->id,
                    'likes_count' => rand(0, 5),
                    'created_at' => now()->subDays(rand(0, 3)),
                ]);
                $createdCount++;
                $bar->advance();
            }
        }

        // 3.2. Корневые комментарии без ответов (для статистики)
        for ($i = 0; $i < 7; $i++) {
            $photo = $photos->random();
            $user = $users->random();

            PhotoComment::create([
                'photo_id' => $photo->id,
                'user_id' => $user->id,
                'content' => $comments[array_rand($comments)] . ' (без ответов)',
                'status' => 'approved',
                'parent_id' => null,
                'likes_count' => rand(0, 15),
                'created_at' => now()->subDays(rand(0, 7)),
            ]);

            $createdCount++;
            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();
        $this->command->info('✅ Создано комментариев: ' . PhotoComment::count());

        $stats = [
            'total' => PhotoComment::count(),
            'pending' => PhotoComment::where('status', 'pending')->count(),
            'approved' => PhotoComment::where('status', 'approved')->count(),
            'rejected' => PhotoComment::where('status', 'rejected')->count(),
            'spam' => PhotoComment::where('status', 'spam')->count(),
            'replies' => PhotoComment::whereNotNull('parent_id')->count(),
            'root' => PhotoComment::whereNull('parent_id')->count(),
        ];

        $this->command->info('📊 Статистика:');
        $this->command->info("   - Всего: {$stats['total']}");
        $this->command->info("   - Корневых: {$stats['root']}");
        $this->command->info("   - Ответов: {$stats['replies']}");
        $this->command->info("   - Ожидают: {$stats['pending']}");
        $this->command->info("   - Одобрены: {$stats['approved']}");
        $this->command->info("   - Отклонены: {$stats['rejected']}");
        $this->command->info("   - Спам: {$stats['spam']}");
    }
}