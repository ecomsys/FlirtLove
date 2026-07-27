<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('👤 Создаем обычных пользователей...');

        $genders = ['male', 'female'];
        $goals = ['friends', 'romantic', 'family', 'casual'];
        $cities = ['Москва', 'Санкт-Петербург', 'Казань', 'Новосибирск', 'Екатеринбург', 'Сочи', 'Краснодар', 'Владивосток'];
        
        // ✅ Новые массивы для анкеты
        $educations = ['Высшее', 'Среднее специальное', 'Неоконченное высшее', 'Два высших', 'Ученая степень'];
        $occupations = ['IT-специалист', 'Дизайнер', 'Врач', 'Учитель', 'Менеджер', 'Маркетолог', 'Инженер', 'Фрилансер', 'Студент'];
        $zodiacs = ['Овен', 'Телец', 'Близнецы', 'Рак', 'Лев', 'Дева', 'Весы', 'Скорпион', 'Стрелец', 'Козерог', 'Водолей', 'Рыбы'];

        for ($i = 1; $i <= 10; $i++) {
            $year = rand(1989, 2006);
            $month = rand(1, 12);
            $day = rand(1, 28);

            // ✅ Рандомно решаем, будет ли юзер премиумом (20% шанс)
            $isPremium = rand(1, 5) === 1;
            $premiumExpires = $isPremium ? now()->addDays(rand(10, 365)) : null;

            User::create([
                'name' => 'Пользователь ' . $i,
                'email' => 'user' . $i . '@test.com',
                'password' => Hash::make('password'),
                'is_admin' => false,
                'email_verified_at' => now(),
                'has_completed_onboarding' => true,
                'gender' => $genders[array_rand($genders)],
                'birth_date' => "{$year}-{$month}-{$day}",
                'dating_goal' => $goals[array_rand($goals)],
                'city' => $cities[array_rand($cities)],
                
                // ✅ Новые поля
                'is_premium' => $isPremium,
                'premium_expires_at' => $premiumExpires,
                'profile_views' => rand(0, 500),
                'likes_count' => rand(0, 150),
                'last_seen' => now()->subMinutes(rand(1, 4320)), // От 1 минуты до 3 дней назад
                'height' => rand(155, 200), // Рост от 155 до 200 см
                'education' => $educations[array_rand($educations)],
                'occupation' => $occupations[array_rand($occupations)],
                'zodiac_sign' => $zodiacs[array_rand($zodiacs)],
            ]);
        }

        $this->command->info('   ✅ Создано пользователей: ' . User::where('is_admin', false)->count());
    }
}