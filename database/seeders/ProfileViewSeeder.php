<?php

namespace Database\Seeders;

use App\Models\ProfileView;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProfileViewSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('👁️ Генерируем историю просмотров анкет...');

        $users = User::where('role', 'user')->get();

        if ($users->count() < 2) {
            $this->command->warn('⚠️ Нужно минимум 2 пользователя для просмотров!');
            return;
        }

        // Очистка старых просмотров
        $deletedCount = ProfileView::count();
        if ($deletedCount > 0) {
            ProfileView::query()->delete();
            $this->command->info("🗑️ Удалено {$deletedCount} старых просмотров");
        }

        $bar = $this->command->getOutput()->createProgressBar($users->count());
        $createdCount = 0;

        foreach ($users as $user) {
            // Каждый юзер смотрит от 5 до 20 чужих анкет
            $viewCount = rand(5, 20);
            
            // Берем случайных юзеров, исключая себя
            $targets = $users->where('id', '!=', $user->id)->random(min($viewCount, $users->count() - 1));

            foreach ($targets as $target) {
                // updateOrCreate защищает от дублей (один юзер смотрит другого - одна запись)
                $view = ProfileView::updateOrCreate(
                    ['viewer_id' => $user->id, 'viewed_id' => $target->id],
                    ['updated_at' => now()->subDays(rand(0, 14))] // Обновляем время последнего просмотра
                );

                if ($view->wasRecentlyCreated) {
                    $createdCount++;
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine(2);

        // ============================================
        // СТАТИСТИКА
        // ============================================
        $stats = [
            'total' => ProfileView::count(),
            'most_viewed' => ProfileView::select('viewed_id', \DB::raw('count(*) as views'))
                ->groupBy('viewed_id')
                ->orderByDesc('views')
                ->first(),
        ];

        $this->command->info('✅ Создано просмотров: ' . $stats['total']);
        
        if ($stats['most_viewed']) {
            $popularUser = User::find($stats['most_viewed']->viewed_id);
            $this->command->info("🔥 Самый популярный: {$popularUser->name} (просмотрен {$stats['most_viewed']->views} раз)");
        }
    }
}