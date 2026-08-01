<?php

namespace Database\Seeders;

use App\Models\UserBlock;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserBlockSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🚫 Генерируем черный список (блокировки)...');

        $users = User::where('role', 'user')->get();

        if ($users->count() < 3) {
            $this->command->warn('⚠️ Нужно минимум 3 пользователя для блокировок!');
            return;
        }

        // Очистка старых блокировок
        $deletedCount = UserBlock::count();
        if ($deletedCount > 0) {
            UserBlock::query()->delete();
            $this->command->info("🗑️ Удалено {$deletedCount} старых блокировок");
        }

        $reasons = ['spam', 'insult', 'creepy', 'scam', null]; // null - без причины, просто не нравится

        $totalToCreate = 15; // Создадим 15 блокировок
        $bar = $this->command->getOutput()->createProgressBar($totalToCreate);
        $createdCount = 0;

        // ============================================
        // 1. СЛУЧАЙНЫЕ БЛОКИРОВКИ (10 шт)
        // ============================================
        for ($i = 0; $i < 10; $i++) {
            $blocker = $users->random();
            $blocked = $users->where('id', '!=', $blocker->id)->random();

            $block = UserBlock::updateOrCreate(
                ['blocker_id' => $blocker->id, 'blocked_id' => $blocked->id],
                ['reason' => $reasons[array_rand($reasons)]]
            );

            if ($block->wasRecentlyCreated) {
                $createdCount++;
            }
            $bar->advance();
        }

        // ============================================
        // 2. МАССОВЫЕ БЛОКИРОВКИ (Маркер проблемного юзера)
        // Один юзер получает 5 блокировок от разных людей (спамер)
        // ============================================
        $this->command->newLine();
        $this->command->info('   🚨 Создаем массовика (спамера, которого блокируют все)...');
        
        $spammer = $users->random();
        $innocentUsers = $users->where('id', '!=', $spammer->id)->random(5);

        foreach ($innocentUsers as $innocentUser) {
            $block = UserBlock::updateOrCreate(
                ['blocker_id' => $innocentUser->id, 'blocked_id' => $spammer->id],
                ['reason' => 'spam'] // Все жалуются на спам
            );

            if ($block->wasRecentlyCreated) {
                $createdCount++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine(2);

        // ============================================
        // СТАТИСТИКА
        // ============================================
        $mostBlocked = UserBlock::select('blocked_id', \DB::raw('count(*) as blocks_count'))
            ->groupBy('blocked_id')
            ->orderByDesc('blocks_count')
            ->first();

        $this->command->info('✅ Создано блокировок: ' . $createdCount);
        
        if ($mostBlocked) {
            $badUser = User::find($mostBlocked->blocked_id);
            $this->command->info("   🚩 Самый блокируемый: {$badUser->name} (заблокирован {$mostBlocked->blocks_count} раз)");
        }
    }
}