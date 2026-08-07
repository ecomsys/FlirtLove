<?php

namespace Database\Seeders;

use App\Enums\CommentRejectReason; // Подключаем наш Enum
use App\Models\Photo;
use App\Models\User;
use App\Models\PhotoComment;
use Illuminate\Database\Seeder;

class PhotoCommentSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('role', 'user')->get();
        $moderators = User::whereIn('role', ['admin', 'moderator'])->get();
        $photos = Photo::where('status', 'approved')->get();

        if ($users->isEmpty()) {
            $this->command->warn('⚠️ Нет пользователей для комментариев!');
            return;
        }

        if ($photos->isEmpty()) {
            $this->command->warn('⚠️ Нет одобренных фото для комментариев!');
            return;
        }

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

        // Хелпер для генерации полей модерации
        $getModerationFields = function(string $status) use ($moderators) {
            $moderatedBy = $moderators->isNotEmpty() ? $moderators->random()->id : null;
            $moderatedAt = now()->subDays(rand(0, 5));

            if ($status === 'pending') {
                return [
                    'moderated_by' => null,
                    'moderated_at' => null,
                    'reject_reason' => null,
                ];
            }

            if ($status === 'approved' || $status === 'spam') {
                // Для одобренных и спама причины отклонения НЕТ
                return [
                    'moderated_by' => $moderatedBy,
                    'moderated_at' => $moderatedAt,
                    'reject_reason' => null,
                ];
            }

            // Если статус 'rejected' — берем случайную причину ИЗ ENUM
            $reasons = array_column(CommentRejectReason::cases(), 'value');
            return [
                'moderated_by' => $moderatedBy,
                'moderated_at' => $moderatedAt,
                'reject_reason' => $reasons[array_rand($reasons)],
            ];
        };

        // ============================================
        // 1. ОДИНОЧНЫЕ КОММЕНТАРИИ (20 шт) - КОРНЕВЫЕ
        // ============================================
        for ($i = 0; $i < 20; $i++) {
            $photo = $photos->random();
            $user = $users->random();
            $status = $statuses[array_rand($statuses)];

            PhotoComment::create(array_merge([
                'photo_id' => $photo->id,
                'user_id' => $user->id,
                'content' => $comments[array_rand($comments)],
                'status' => $status,
                'parent_id' => null,
                'likes_count' => rand(0, 10),
                'reports_count' => rand(0, 3),
                'replies_count' => 0,
                'is_pinned' => (bool) rand(0, 1),
                'edited_at' => rand(0, 1) ? now()->subDays(rand(0, 2)) : null,
                'created_at' => now()->subDays(rand(0, 10)),
                'updated_at' => now()->subDays(rand(0, 5)),
            ], $getModerationFields($status)));

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
            $parent = PhotoComment::create(array_merge([
                'photo_id' => $photo->id,
                'user_id' => $user->id,
                'content' => $comments[array_rand($comments)] . ' (родитель)',
                'status' => 'approved',
                'parent_id' => null,
                'likes_count' => rand(0, 10),
                'reports_count' => rand(0, 2),
                'replies_count' => 0,
                'is_pinned' => (bool) rand(0, 1),
                'edited_at' => null,
                'created_at' => now()->subDays(rand(0, 10)),
                'updated_at' => now()->subDays(rand(0, 5)),
            ], $getModerationFields('approved')));

            $createdCount++;

            // Ответ 1
            $status1 = $statuses[array_rand($statuses)];
            PhotoComment::create(array_merge([
                'photo_id' => $photo->id,
                'user_id' => $replyUser->id,
                'content' => 'Ответ: ' . $comments[array_rand($comments)],
                'status' => $status1,
                'parent_id' => $parent->id,
                'likes_count' => rand(0, 5),
                'reports_count' => rand(0, 2),
                'replies_count' => 0,
                'is_pinned' => false,
                'edited_at' => rand(0, 1) ? now()->subDays(rand(0, 2)) : null,
                'created_at' => now()->subDays(rand(0, 5)),
                'updated_at' => now()->subDays(rand(0, 3)),
            ], $getModerationFields($status1)));

            // Инкрементируем счетчик ответов родителя
            $parent->increment('replies_count');
            $createdCount++;

            // Иногда второй ответ
            if (rand(0, 1)) {
                $replyUser2 = $users->random();
                $status2 = $statuses[array_rand($statuses)];
                
                PhotoComment::create(array_merge([
                    'photo_id' => $photo->id,
                    'user_id' => $replyUser2->id,
                    'content' => 'Еще один ответ: ' . $comments[array_rand($comments)],
                    'status' => $status2,
                    'parent_id' => $parent->id,
                    'likes_count' => rand(0, 3),
                    'reports_count' => rand(0, 1),
                    'replies_count' => 0,
                    'is_pinned' => false,
                    'edited_at' => null,
                    'created_at' => now()->subDays(rand(0, 3)),
                    'updated_at' => now()->subDays(rand(0, 2)),
                ], $getModerationFields($status2)));

                $parent->increment('replies_count');
                $createdCount++;
            }

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
                $status = $statuses[array_rand($statuses)];
                
                PhotoComment::create(array_merge([
                    'photo_id' => $existingParent->photo_id,
                    'user_id' => $replyUser->id,
                    'content' => 'Ответ на популярный комментарий: ' . $comments[array_rand($comments)],
                    'status' => $status,
                    'parent_id' => $existingParent->id,
                    'likes_count' => rand(0, 5),
                    'reports_count' => rand(0, 2),
                    'replies_count' => 0,
                    'is_pinned' => false,
                    'edited_at' => null,
                    'created_at' => now()->subDays(rand(0, 3)),
                    'updated_at' => now()->subDays(rand(0, 2)),
                ], $getModerationFields($status)));

                $existingParent->increment('replies_count');
                $createdCount++;
                $bar->advance();
            }
        }

        // 3.2. Корневые комментарии без ответов (для статистики)
        for ($i = 0; $i < 7; $i++) {
            $photo = $photos->random();
            $user = $users->random();

            PhotoComment::create(array_merge([
                'photo_id' => $photo->id,
                'user_id' => $user->id,
                'content' => $comments[array_rand($comments)] . ' (без ответов)',
                'status' => 'approved',
                'parent_id' => null,
                'likes_count' => rand(0, 15),
                'reports_count' => rand(0, 2),
                'replies_count' => 0,
                'is_pinned' => (bool) rand(0, 1),
                'edited_at' => null,
                'created_at' => now()->subDays(rand(0, 7)),
                'updated_at' => now()->subDays(rand(0, 4)),
            ], $getModerationFields('approved')));

            $createdCount++;
            $bar->advance();
        }

        // 3.3. Добавляем несколько комментариев от админа (если есть)
        $admin = User::where('role', 'admin')->first();
        if ($admin) {
            for ($i = 0; $i < 3; $i++) {
                $photo = $photos->random();
                PhotoComment::create(array_merge([
                    'photo_id' => $photo->id,
                    'user_id' => $admin->id,
                    'content' => '🔥 Админ одобряет! ' . $comments[array_rand($comments)],
                    'status' => 'approved',
                    'parent_id' => null,
                    'likes_count' => rand(5, 20),
                    'reports_count' => 0,
                    'replies_count' => 0,
                    'is_pinned' => (bool) rand(0, 1),
                    'edited_at' => null,
                    'created_at' => now()->subDays(rand(0, 5)),
                    'updated_at' => now(),
                ], $getModerationFields('approved')));

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
        $this->command->info("   │ Тип             │ Кол-во   │");
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