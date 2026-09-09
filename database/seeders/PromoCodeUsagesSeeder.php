<?php

namespace Database\Seeders;

use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class PromoCodeUsagesSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🎟️ Генерируем историю использования промокодов...');

        PromoCodeUsage::truncate();
        PromoCode::query()->update(['used_count' => 0]);

        $users = User::where('role', 'user')->get();
        
        if ($users->isEmpty()) {
            $this->command->warn('⚠️ Нет юзеров для генерации.');
            return;
        }

        $createdCount = 0;

        foreach ($users as $user) {
            // Симулируем, что 30% юзеров использовали промокод
            if (rand(1, 10) > 3) {
                continue;
            }

            // 1. Находим все валидные для этого юзера промокоды (не просроченные, не привязанные к другим и т.д.)
            $validPromos = PromoCode::all()->filter(fn($p) => $p->isValidForUser($user))->values();
            
            if ($validPromos->isEmpty()) {
                continue;
            }

            // 2. Берем один случайный валидный промокод
            $promo = $validPromos->random();

            // 3. Решаем, к чему его применять:
            // Если это бесплатный VIP/Premium — транзакция не нужна (система просто начислит дни в реальности).
            // Если это скидка (percent/fixed) — привязываем к реальной успешной транзакции.
            $transaction = null;
            
            if (!in_array($promo->discount_type, [PromoCode::TYPE_PREMIUM, PromoCode::TYPE_VIP])) {
                $transaction = Transaction::where('user_id', $user->id)
                    ->where('status', 'success')
                    ->inRandomOrder()
                    ->first();

                // Если у юзера нет успешных транзакций, а промокод требует скидки на оплату — пропускаем
                if (!$transaction) {
                    continue;
                }
            }

            // 4. ИСПОЛЬЗУЕМ НАШ НОВЫЙ АТОМАРНЫЙ МЕТОД apply()!
            // Это и есть "реальное действие" — метод сам посчитает снапшот скидки и инкрементнет счетчик
            $usage = $promo->apply($user, $transaction);

            if ($usage) {
                // Чтобы история выглядела реалистично, сдвигаем дату применения в прошлое
                $date = now()->subDays(rand(1, 30));
                $usage->update(['created_at' => $date, 'updated_at' => $date]);
                
                $createdCount++;
            }
        }

        $this->command->info("✅ Реалистично применено промокодов: {$createdCount}");
    }
}