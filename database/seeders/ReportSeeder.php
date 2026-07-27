<?php

namespace Database\Seeders;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReportSeeder extends Seeder
{
    public function run(): void
    {
        // ✅ Берем только обычных юзеров
        $users = User::excludeAdmins()->get();
        
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
        ];

        for ($i = 0; $i < 10; $i++) {
            $reporter = $users->random();
            // ✅ Гарантируем, что юзер не кидает жалобу сам на себя
            $reported = $users->where('id', '!=', $reporter->id)->random();
            
            Report::create([
                'user_id' => $reporter->id,
                'reported_user_id' => $reported->id,
                'photo_id' => null,
                'reason' => $reasons[array_rand($reasons)],
                'status' => ['pending', 'resolved', 'rejected'][array_rand(['pending', 'resolved', 'rejected'])],
                'type' => 'user',
                'created_at' => now()->subDays(rand(0, 10)),
            ]);
        }

        $this->command->info('   ✅ Создано жалоб: ' . Report::count());
    }
}