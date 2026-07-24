<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('👤 Создаем пользователей...');

        // Админ
        User::create([
            'name' => 'Админ',
            'email' => 'admin@admin.com',
            'password' => Hash::make('12121212'),
            'is_admin' => true,
            'email_verified_at' => now(),
            'has_completed_onboarding' => true,
            'gender' => 'male',
            'birth_date' => '1990-01-01',
            'dating_goal' => 'friends',
            'city' => 'Москва',
        ]);

        $this->command->info('   ✅ Админ создан');

        // 10 пользователей
        $genders = ['male', 'female'];
        $goals = ['friends', 'romantic', 'family', 'casual'];
        $cities = ['Москва', 'Санкт-Петербург', 'Казань', 'Новосибирск', 'Екатеринбург', 'Сочи', 'Краснодар', 'Владивосток'];

        for ($i = 1; $i <= 10; $i++) {
            $year = rand(1989, 2006);
            $month = rand(1, 12);
            $day = rand(1, 28);

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
            ]);
        }

        $this->command->info('   ✅ Создано пользователей: ' . User::count());
    }
}