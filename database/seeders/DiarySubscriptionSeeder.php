<?php

namespace Database\Seeders;

use App\Models\Diary;
use App\Models\DiarySubscription;
use App\Models\User;
use Illuminate\Database\Seeder;

class DiarySubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('📬 Генерируем подписки на дневники...');

        $users = User::where('role', 'user')->get();

        if ($users->count() < 2) {
            $this->command->warn('⚠️ Нужно минимум 2 пользователя для подписок!');
            return;
        }

        // Очистка старых подписок
        $deletedCount = DiarySubscription::count();
        if ($deletedCount > 0) {
            DiarySubscription::query()->delete();
            $this->command->info("🗑️ Удалено {$deletedCount} старых подписок");
        }

        // Находим авторов, у которых есть опубликованные посты (на них логично подписываться)
        $authorIds = Diary::where('status', 'published')
            ->select('user_id')
            ->groupBy('user_id')
            ->pluck('user_id');

        if ($authorIds->isEmpty()) {
            $this->command->warn('⚠️ Нет авторов с опубликованными постами!');
            return;
        }

        $totalToCreate = 30; // 30 подписок
        $bar = $this->command->getOutput()->createProgressBar($totalToCreate);
        $createdCount = 0;

        for ($i = 0; $i < $totalToCreate; $i++) {
            // Выбираем случайного автора (на кого подписываемся)
            $authorId = $authorIds->random();

            // Выбираем случайного читателя (кто подписывается), исключая самого автора
            $subscriber = $users->where('id', '!=', $authorId)->random();

            $sub = DiarySubscription::updateOrCreate(
                ['subscriber_id' => $subscriber->id, 'author_id' => $authorId],
                ['created_at' => now()->subDays(rand(1, 30))]
            );

            if ($sub->wasRecentlyCreated) {
                $createdCount++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine(2);

        // ============================================
        // СТАТИСТИКА
        // ============================================
        $mostPopular = DiarySubscription::select('author_id', \DB::raw('count(*) as subs_count'))
            ->groupBy('author_id')
            ->orderByDesc('subs_count')
            ->first();

        $this->command->info('✅ Создано подписок: ' . $createdCount);
        
        if ($mostPopular) {
            $popularAuthor = User::find($mostPopular->author_id);
            $this->command->info("   ⭐ Самый популярный автор: {$popularAuthor->name} ({$mostPopular->subs_count} читателей)");
        }
    }
}