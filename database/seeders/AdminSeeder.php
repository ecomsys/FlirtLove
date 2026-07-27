<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('👑 Создаем главного администратора...');

        User::updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Админ',
                'password' => Hash::make('12121212'),
                'is_admin' => true,
                'email_verified_at' => now(),
                'has_completed_onboarding' => true,
                'gender' => 'male',
                'birth_date' => '1990-01-01',
                'dating_goal' => 'friends',
                'city' => 'Москва',
            ]
        );

        $this->command->info('   ✅ Админ готов (admin@admin.com / 12121212)');
    }
}