<?php

namespace Database\Seeders;

use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Database\Seeder;

class PromoCodeSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🎁 Создаем промокоды...');

        PromoCode::truncate();

        $promos = [
            // 1. Базовый промокод на 20%
            [
                'code' => 'WELCOME20',
                'discount_type' => PromoCode::TYPE_PERCENT,
                'discount_value' => 20,
                'max_uses' => null, 
                'is_active' => true,
            ],
            // 2. Фиксированная скидка 500 рублей
            [
                'code' => 'SAVE500',
                'discount_type' => PromoCode::TYPE_FIXED,
                'discount_value' => 500.00,
                'max_uses' => 1000,
                'used_count' => 150, 
                'is_active' => true,
            ],
            // 3. Промокод на выдачу Premium (14 дней)
            [
                'code' => 'FREEPREM14',
                'discount_type' => PromoCode::TYPE_PREMIUM,
                'discount_value' => 0,
                'duration_days' => 14, // НОВОЕ: даем 14 дней
                'max_uses' => 50,
                'is_active' => true,
            ],
            // 4. Промокод на выдачу VIP (7 дней)
            [
                'code' => 'FREEVIP7',
                'discount_type' => PromoCode::TYPE_VIP,
                'discount_value' => 0,
                'duration_days' => 7,
                'max_uses' => 50,
                'is_active' => true,
            ],
            // 5. Просроченный промокод
            [
                'code' => 'EXPIRED10',
                'discount_type' => PromoCode::TYPE_PERCENT,
                'discount_value' => 10,
                'expires_at' => now()->subMonth(), 
                'is_active' => true,
            ],
            // 6. Промокод с исчерпанным лимитом
            [
                'code' => 'LIMIT99',
                'discount_type' => PromoCode::TYPE_FIXED,
                'discount_value' => 99.00,
                'max_uses' => 100,
                'used_count' => 100, 
                'is_active' => true,
            ],
        ];

        foreach ($promos as $promo) {
            PromoCode::create($promo);
        }

        // 7. Персональный промокод для первого юзера
        $firstUser = User::where('role', 'user')->first();
        if ($firstUser) {
            PromoCode::create([
                'code' => 'PETYA2024',
                'discount_type' => PromoCode::TYPE_PERCENT,
                'discount_value' => 50,
                'user_id' => $firstUser->id, 
                'max_uses' => 1,
                'is_active' => true,
            ]);
        }

        $this->command->info('✅ Создано 7 тестовых промокодов (включая просроченные, VIP и персональные).');
    }
}