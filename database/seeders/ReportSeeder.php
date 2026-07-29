<?php

namespace Database\Seeders;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReportSeeder extends Seeder
{
    public function run(): void
    {
        // ✅ Берем только обычных юзеров (не админов)
        $users = User::where('is_admin', false)->get();
        
        if ($users->count() < 2) {
            $this->command->warn('⚠️ Нужно минимум 2 обычных пользователя для жалоб!');
            return;
        }

        $this->command->info('🚩 Создаем жалобы...');

        $reasons = [
            'Оскорбляет других пользователей в чате',
            'Профиль выглядит фейковым',
            'Спамит ссылками на посторонние сайты',
            'Выдает себя за другого человека',
            'Рассылает неприемлемый контент',
            'Нарушает правила сообщества',
            'Некорректное поведение',
            'Агрессия в общении',
            'Рассылка рекламы',
            'Неприемлемые фото',
        ];

        $statuses = ['pending', 'resolved', 'rejected'];
        $types = ['user', 'photo'];

        $createdCount = 0;

        // ============================================
        // 1. ЖАЛОБЫ НА ПОЛЬЗОВАТЕЛЕЙ (15 шт)
        // ============================================
        $this->command->info('   📝 Создаем жалобы на пользователей...');
        
        for ($i = 0; $i < 15; $i++) {
            $reporter = $users->random();
            // Гарантируем, что юзер не кидает жалобу сам на себя
            $reported = $users->where('id', '!=', $reporter->id)->random();
            
            Report::create([
                'user_id' => $reporter->id,
                'reported_user_id' => $reported->id,
                'photo_id' => null,
                'reason' => $reasons[array_rand($reasons)],
                'status' => $statuses[array_rand($statuses)],
                'type' => 'user',
                'moderator_id' => null,
                'resolved_at' => null,
                'created_at' => now()->subDays(rand(0, 30)),
                'updated_at' => now()->subDays(rand(0, 10)),
            ]);

            $createdCount++;
        }

        $this->command->info("   ✅ Создано жалоб на пользователей: 15");

        // ============================================
        // 2. ЖАЛОБЫ НА ФОТО (5 шт)
        // ============================================
        $this->command->info('   📝 Создаем жалобы на фото...');

        $photos = \App\Models\Photo::where('status', 'approved')->get();

        if ($photos->isNotEmpty()) {
            for ($i = 0; $i < 5; $i++) {
                $reporter = $users->random();
                $photo = $photos->random();
                $reported = $photo->user; // Владелец фото

                Report::create([
                    'user_id' => $reporter->id,
                    'reported_user_id' => $reported->id,
                    'photo_id' => $photo->id,
                    'reason' => 'Неприемлемое фото: ' . $reasons[array_rand($reasons)],
                    'status' => $statuses[array_rand($statuses)],
                    'type' => 'photo',
                    'moderator_id' => null,
                    'resolved_at' => null,
                    'created_at' => now()->subDays(rand(0, 20)),
                    'updated_at' => now()->subDays(rand(0, 5)),
                ]);

                $createdCount++;
            }
            $this->command->info("   ✅ Создано жалоб на фото: 5");
        } else {
            $this->command->warn("   ⚠️ Нет фото для жалоб, пропускаем...");
        }

        // ============================================
        // 3. РАЗРЕШЕННЫЕ ЖАЛОБЫ (с модератором)
        // ============================================
        $admin = User::where('is_admin', true)->first();
        
        if ($admin) {
            $this->command->info('   📝 Создаем разрешенные жалобы (с модератором)...');
            
            for ($i = 0; $i < 5; $i++) {
                $reporter = $users->random();
                $reported = $users->where('id', '!=', $reporter->id)->random();
                
                Report::create([
                    'user_id' => $reporter->id,
                    'reported_user_id' => $reported->id,
                    'photo_id' => null,
                    'reason' => $reasons[array_rand($reasons)],
                    'status' => 'resolved',
                    'type' => 'user',
                    'moderator_id' => $admin->id,
                    'resolved_at' => now()->subDays(rand(1, 15)),
                    'created_at' => now()->subDays(rand(10, 30)),
                    'updated_at' => now()->subDays(rand(1, 10)),
                ]);

                $createdCount++;
            }
            $this->command->info("   ✅ Создано разрешенных жалоб: 5");
        } else {
            $this->command->warn("   ⚠️ Нет админа для модерации, пропускаем...");
        }

        // ============================================
        // 4. СТАТИСТИКА
        // ============================================
        $this->command->newLine();
        $this->command->info('✅ Всего создано жалоб: ' . $createdCount);

        $stats = [
            'total' => Report::count(),
            'pending' => Report::where('status', 'pending')->count(),
            'resolved' => Report::where('status', 'resolved')->count(),
            'rejected' => Report::where('status', 'rejected')->count(),
            'user_reports' => Report::where('type', 'user')->count(),
            'photo_reports' => Report::where('type', 'photo')->count(),
        ];

        $this->command->info('');
        $this->command->info('📊 Статистика:');
        $this->command->info("   ┌─────────────────────┬──────────┐");
        $this->command->info("   │ Тип                 │ Количество │");
        $this->command->info("   ├─────────────────────┼──────────┤");
        $this->command->info("   │ Всего               │ {$stats['total']}        │");
        $this->command->info("   │ На пользователей    │ {$stats['user_reports']}        │");
        $this->command->info("   │ На фото             │ {$stats['photo_reports']}        │");
        $this->command->info("   ├─────────────────────┼──────────┤");
        $this->command->info("   │ Ожидают             │ {$stats['pending']}        │");
        $this->command->info("   │ Разрешены           │ {$stats['resolved']}        │");
        $this->command->info("   │ Отклонены           │ {$stats['rejected']}        │");
        $this->command->info("   └─────────────────────┴──────────┘");
    }
}