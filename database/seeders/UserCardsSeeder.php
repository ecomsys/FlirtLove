<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserCard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserCardsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('💳 Привязываем фейковые карты пользователям...');

        UserCard::truncate();

        // Берем всех обычных юзеров и админов
        $users = User::whereIn('role', ['user', 'admin'])->get();
        $gateways = ['yookassa', 'cloudpayments', 'stripe'];
        $cardTypes = ['visa', 'mastercard', 'mir'];

        $createdCount = 0;

        foreach ($users as $user) {
            // Выдаем каждому юзеру от 1 до 2 карт
            $cardsToCreate = rand(1, 2);

            for ($i = 0; $i < $cardsToCreate; $i++) {
                
                // Генерируем год (текущий + от 1 до 4 лет вперед)
                $expiryYear = (int) date('Y') + rand(1, 4);
                
                UserCard::create([
                    'user_id' => $user->id,
                    'gateway' => $gateways[array_rand($gateways)],
                    // Фейковый токен платежного шлюза
                    'payment_method_id' => 'pm_' . Str::uuid()->toString(),
                    'card_type' => $cardTypes[array_rand($cardTypes)],
                    // Случайные 4 цифры
                    'last4' => str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT),
                    'expiry_month' => str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT),
                    'expiry_year' => (string) $expiryYear,
                    'is_active' => true,
                    // Первая карта у юзера будет "по умолчанию"
                    'is_default' => ($i === 0),
                ]);
                
                $createdCount++;
            }
        }

        $this->command->info("✅ Привязано карт: {$createdCount} (для {$users->count()} пользователей).");
    }
}