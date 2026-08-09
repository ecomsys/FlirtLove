<?php

namespace Database\Seeders;

use App\Models\Diary;
use App\Models\Rubric;
use App\Models\User;
use Illuminate\Database\Seeder;

class DiarySeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('📖 Генерируем посты для дневников...');

        $users = User::where('role', 'user')->get();
        $rubrics = Rubric::where('is_active', true)->get();

        if ($users->isEmpty() || $rubrics->isEmpty()) {
            $this->command->warn('⚠️ Нет пользователей или рубрик для создания постов!');
            return;
        }

        // Очистка старых постов
        $deletedCount = Diary::count();
        if ($deletedCount > 0) {
            Diary::query()->delete();
            $this->command->info("🗑️ Удалено {$deletedCount} старых постов");
        }

        // Шаблоны заголовков (реалистичные для дейтинга)
        $titleTemplates = [
            'Мысли вслух', 'Одинокий вечер', 'Мой идеальный выходной',
            'Почему мы боимся чувств?', 'Случай на работе', 'Новая глава жизни',
            'Три вещи, которые я понял в этом году', 'Мой любимый город',
            'Как я справляюсь с грустью', 'Письмо будущему мне',
        ];

        $totalToCreate = 40; // 40 постов для ленты
        $bar = $this->command->getOutput()->createProgressBar($totalToCreate);
        $createdCount = 0;

        for ($i = 0; $i < $totalToCreate; $i++) {
            $user = $users->random();
            $rubric = $rubrics->random();

            // 80% постов опубликованы, 20% - черновики
            $isPublished = (bool) rand(0, 100) >= 20;

            Diary::create([
                'user_id' => $user->id,
                'rubric_id' => $rubric->id,
                'title' => $titleTemplates[array_rand($titleTemplates)],
                'body' => fake()->realTextBetween(300, 1500), // Генерим длинный текст
                'status' => $isPublished ? 'published' : 'draft',
                'published_at' => $isPublished ? now()->subDays(rand(1, 60)) : null,
                'is_comments_enabled' => (bool) rand(0, 10) >= 1, // 90% с открытыми комментами
                'views_count' => rand(5, 500), // Случайные просмотры
                'comments_count' => rand(0, 20), // Случайные комменты
                'created_at' => now()->subDays(rand(1, 60)),
            ]);

            $createdCount++;
            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine(2);

        // ============================================
        // СТАТИСТИКА
        // ============================================
        $stats = [
            'total' => Diary::count(),
            'published' => Diary::where('status', 'published')->count(),
            'draft' => Diary::where('status', 'draft')->count(),
        ];

        $this->command->info('✅ Создано постов: ' . $stats['total']);
        $this->command->info("   🟢 Опубликовано: {$stats['published']}");
        $this->command->info("   🟡 Черновиков: {$stats['draft']}");
    }
}