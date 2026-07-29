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
        // Получаем обычных пользователей (не админов)
        $users = User::where('is_admin', false)->get();
        $photos = Photo::where('status', 'approved')->get();

        if ($users->isEmpty()) {
            $this->command->warn('⚠️ Нет пользователей для комментариев!');
            return;
        }

        if ($photos->isEmpty()) {
            $this->command->warn('⚠️ Нет одобренных фото для комментариев!');
            return;
        }

        // Очищаем старые комментарии (без truncate, чтобы не сбрасывать sequence)
        $deletedCount = PhotoComment::count();
        if ($deletedCount > 0) {
            PhotoComment::query()->delete();
            $this->command->info("🗑️ Удалено {$deletedCount} старых комментариев");
        }

        $this->command->info('💬 Создаем комментарии к фото...');

        $statuses = ['pending', 'approved', 'rejected', 'spam'];
        $comments = [
            'Красивое фото! 😍', 'Отличный снимок!', 'Где сделано?',
            'Очень атмосферно!', 'Прекрасный кадр!', 'Выглядит профессионально!',
            'Мне нравится! 👍', 'Класс!', 'Супер! 🔥',
            'Необычный ракурс!', 'Потрясающе!', 'Какая красота!',
            'Очень понравилось!', 'Хочу туда же!', 'Просто вау! 🤩',
            'Отличная композиция!', 'Спасибо за фото!', 'У вас хороший вкус!',
            'Вдохновляюще!', '😊 Прекрасно!', 'Шикарно!', 'Огонь! 🔥',
            'Красотка!', 'Отличный кадр!', 'Суперски выглядишь!',
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
                'parent_id' => null,
                'likes_count' => rand(0, 10),
                'reports_count' => rand(0, 3),
                'is_edited' => (bool) rand(0, 1),
                'is_pinned' => (bool) rand(0, 1),
                'created_at' => now()->subDays(rand(0, 10)),
                'updated_at' => now()->subDays(rand(0, 5)),
            ]);

            $createdCount++;
            $bar->advance();
        }

        // ============================================
        // 2. КОММЕНТАРИИ С ОТВЕТАМИ (10 шт)
        // ============================================
        for ($i = 0; $i < 10; $i++) {
            $photo = $photos->random();
            $user = $users->random();
            $replyUser = $users->random();

            // Родительский комментарий — КОРНЕВОЙ, всегда APPROVED
            $parent = PhotoComment::create([
                'photo_id' => $photo->id,
                'user_id' => $user->id,
                'content' => $comments[array_rand($comments)] . ' (родитель)',
                'status' => 'approved',
                'parent_id' => null,
                'likes_count' => rand(0, 10),
                'reports_count' => rand(0, 2),
                'is_edited' => false,
                'is_pinned' => (bool) rand(0, 1),
                'created_at' => now()->subDays(rand(0, 10)),
                'updated_at' => now()->subDays(rand(0, 5)),
            ]);

            // ✅ Ответ — parent_id = id родителя
            PhotoComment::create([
                'photo_id' => $photo->id,
                'user_id' => $replyUser->id,
                'content' => 'Ответ: ' . $comments[array_rand($comments)],
                'status' => $statuses[array_rand($statuses)],
                'parent_id' => $parent->id,
                'likes_count' => rand(0, 5),
                'reports_count' => rand(0, 2),
                'is_edited' => (bool) rand(0, 1),
                'is_pinned' => false,
                'created_at' => now()->subDays(rand(0, 5)),
                'updated_at' => now()->subDays(rand(0, 3)),
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
                    'reports_count' => rand(0, 1),
                    'is_edited' => false,
                    'is_pinned' => false,
                    'created_at' => now()->subDays(rand(0, 3)),
                    'updated_at' => now()->subDays(rand(0, 2)),
                ]);
                $createdCount++;
                $bar->advance();
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
                    'reports_count' => rand(0, 2),
                    'is_edited' => (bool) rand(0, 1),
                    'is_pinned' => false,
                    'created_at' => now()->subDays(rand(0, 3)),
                    'updated_at' => now()->subDays(rand(0, 2)),
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
                'reports_count' => rand(0, 2),
                'is_edited' => false,
                'is_pinned' => (bool) rand(0, 1),
                'created_at' => now()->subDays(rand(0, 7)),
                'updated_at' => now()->subDays(rand(0, 4)),
            ]);

            $createdCount++;
            $bar->advance();
        }

        // 3.3. Добавляем несколько комментариев от админа (если есть)
        $admin = User::where('is_admin', true)->first();
        if ($admin) {
            for ($i = 0; $i < 3; $i++) {
                $photo = $photos->random();
                PhotoComment::create([
                    'photo_id' => $photo->id,
                    'user_id' => $admin->id,
                    'content' => '🔥 Админ одобряет! ' . $comments[array_rand($comments)],
                    'status' => 'approved',
                    'parent_id' => null,
                    'likes_count' => rand(5, 20),
                    'reports_count' => 0,
                    'is_edited' => false,
                    'is_pinned' => (bool) rand(0, 1),
                    'created_at' => now()->subDays(rand(0, 5)),
                    'updated_at' => now(),
                ]);
                $createdCount++;
                $bar->advance();
            }
        }

        $bar->finish();
        $this->command->newLine(2);

        // ============================================
        // СТАТИСТИКА
        // ============================================
        $stats = [
            'total' => PhotoComment::count(),
            'pending' => PhotoComment::where('status', 'pending')->count(),
            'approved' => PhotoComment::where('status', 'approved')->count(),
            'rejected' => PhotoComment::where('status', 'rejected')->count(),
            'spam' => PhotoComment::where('status', 'spam')->count(),
            'replies' => PhotoComment::whereNotNull('parent_id')->count(),
            'root' => PhotoComment::whereNull('parent_id')->count(),
        ];

        $this->command->info('✅ Создано комментариев: ' . $stats['total']);
        $this->command->info('');
        $this->command->info('📊 Статистика:');
        $this->command->info("   ┌─────────────────┬──────────┐");
        $this->command->info("   │ Тип             │ Количество │");
        $this->command->info("   ├─────────────────┼──────────┤");
        $this->command->info("   │ Всего           │ {$stats['total']}        │");
        $this->command->info("   │ Корневые        │ {$stats['root']}        │");
        $this->command->info("   │ Ответы          │ {$stats['replies']}        │");
        $this->command->info("   ├─────────────────┼──────────┤");
        $this->command->info("   │ Ожидают         │ {$stats['pending']}        │");
        $this->command->info("   │ Одобрены        │ {$stats['approved']}        │");
        $this->command->info("   │ Отклонены       │ {$stats['rejected']}        │");
        $this->command->info("   │ Спам            │ {$stats['spam']}        │");
        $this->command->info("   └─────────────────┴──────────┘");
    }
}