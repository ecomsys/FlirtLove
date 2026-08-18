<?php

namespace Database\Seeders;

use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\SubscriptionPlan;
use App\Models\UserBalance;
use App\Models\UserGift;
use App\Models\Gift;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FinanceHistorySeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('💰 Генерируем реалистичную финансовую историю...');

        // 1. Очистка старых данных (в строгом порядке из-за Foreign Keys)
        UserGift::query()->delete();
        UserSubscription::query()->delete();
        Transaction::query()->delete();
        
        // Сбрасываем флаги премиума у всех юзеров перед началом
        User::where('is_premium', true)->update([
            'is_premium' => false,
            'premium_expires_at' => null,
        ]);

        $users = User::where('role', 'user')->get();
        $plans = SubscriptionPlan::all();
        $gifts = Gift::all();

        if ($users->isEmpty() || $plans->isEmpty()) {
            $this->command->warn('⚠️ Нет пользователей или тарифов!');
            return;
        }

        $bar = $this->command->getOutput()->createProgressBar($users->count());
        $createdTrans = 0;
        $createdSubs = 0;
        $createdGifts = 0;

        foreach ($users as $user) {
            // Даем финансовую историю примерно 60% юзеров
            if (rand(0, 100) >= 40) {
                
                // --- ЭТАП 1: Покупка подписки ---
                $plan = $plans->random();
                $transDate = now()->subDays(rand(5, 90));
                $provider = ['yookassa', 'stripe', 'apple', 'google', 'manual'][array_rand(['yookassa', 'stripe', 'apple', 'google', 'manual'])];
                
                // 80% успех, 20% неудача/ожидание
                $isSuccess = rand(1, 10) <= 8;
                $transStatus = $isSuccess ? 'success' : (rand(0, 1) ? 'failed' : 'pending');
                
                $trans = Transaction::create([
                    'user_id' => $user->id,
                    'amount' => $plan->price,
                    'currency' => 'RUB',
                    'type' => 'subscription',
                    'status' => $transStatus,
                    'provider' => $provider,
                    'provider_transaction_id' => Str::uuid()->toString(),
                    'credits_amount' => null,
                    'meta' => [
                        'plan_id' => $plan->id,
                        'plan_name' => $plan->name,
                        'fail_reason' => $transStatus === 'failed' ? 'Недостаточно средств на карте' : null,
                    ],
                    'created_at' => $transDate,
                    'updated_at' => $transDate,
                ]);
                $createdTrans++;

                if ($isSuccess) {
                    // Считаем даты подписки от даты транзакции (подписка активировалась чуть позже)
                    $startsAt = $transDate->copy()->addMinutes(5);
                    $endsAt = $startsAt->copy()->addDays($plan->duration_days);
                    
                    // Рандомный статус подписки (70% active, 20% expired, 10% canceled)
                    $subStatusRoll = rand(1, 10);
                    $subStatus = $subStatusRoll <= 7 ? 'active' : ($subStatusRoll <= 9 ? 'expired' : 'canceled');
                    
                    // Гарантируем, что если expired, то дата уже прошла
                    if ($subStatus === 'expired') {
                        $endsAt = now()->subDays(rand(1, 15));
                        $startsAt = $endsAt->copy()->subDays($plan->duration_days);
                    }
                    
                    $isAutoRenew = ($subStatus === 'active') ? (bool) rand(0, 1) : false;
                    $canceledAt = ($subStatus === 'canceled') ? now()->subDays(rand(1, 5)) : null;

                    UserSubscription::create([
                        'user_id' => $user->id,
                        'plan_id' => $plan->id,
                        'transaction_id' => $trans->id,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'is_auto_renew' => $isAutoRenew,
                        'provider_subscription_id' => $isAutoRenew ? 'sub_' . Str::random(24) : null,
                        'status' => $subStatus,
                        'canceled_at' => $canceledAt,
                        'created_at' => $startsAt,
                        'updated_at' => $startsAt,
                    ]);
                    $createdSubs++;

                    // Синхронизируем User только если подписка активна и не истекла
                    if ($subStatus === 'active' && $endsAt->isFuture()) {
                        $user->update([
                            'is_premium' => true,
                            'premium_expires_at' => $endsAt,
                        ]);
                    }
                }
                
                // --- ЭТАП 2: Покупка кредитов ---
                if (rand(1, 10) <= 4) {
                    $creditPack = ['credits' => 100, 'price' => 99.00]; // Фиксированный пак для простоты
                    $creditDate = now()->subDays(rand(1, 30));
                    
                    Transaction::create([
                        'user_id' => $user->id,
                        'amount' => $creditPack['price'],
                        'currency' => 'RUB',
                        'type' => 'credits',
                        'status' => 'success', // Кредиты всегда покупаем успешно для теста
                        'provider' => 'yookassa',
                        'provider_transaction_id' => Str::uuid()->toString(),
                        'credits_amount' => $creditPack['credits'],
                        'meta' => ['credits_purchased' => $creditPack['credits']],
                        'created_at' => $creditDate,
                        'updated_at' => $creditDate,
                    ]);
                    $createdTrans++;

                    // Начисляем кредиты на баланс
                    $balance = UserBalance::firstOrCreate(['user_id' => $user->id]);
                    $balance->addCredits($creditPack['credits']);
                }
                
                // --- ЭТАП 3: Покупка подарка за кредиты (если есть баланс и подарок) ---
                if ($user->balance && $user->balance->credits > 0 && $gifts->isNotEmpty()) {
                    $gift = $gifts->random();
                    // Если хватает кредитов на подарок
                    if ($user->balance->credits >= $gift->price) {
                        $receiver = $users->where('id', '!=', $user->id)->random();
                        $giftDate = now()->subDays(rand(0, 10));
                        $isRead = (bool) rand(0, 1);
                        
                        // Списываем кредиты
                        $user->balance->spendCredits($gift->price);
                        
                        UserGift::create([
                            'sender_id' => $user->id,
                            'receiver_id' => $receiver->id,
                            'gift_id' => $gift->id,
                            // ФИКС: Заполняем снапшоты, чтобы история не ломалась при изменении каталога
                            'snapshot_name' => $gift->name,
                            'snapshot_image_url' => $gift->image_url,
                            'snapshot_price' => $gift->price,
                            'message' => 'Симпатичный подарок от тестового сида!',
                            'is_private' => (bool) rand(0, 1),
                            'is_read' => $isRead,
                            'read_at' => $isRead ? $giftDate->copy()->addHours(rand(1, 24)) : null,
                            'created_at' => $giftDate,
                            'updated_at' => $giftDate,
                        ]);
                        $createdGifts++;
                    }
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
            'transactions' => $createdTrans,
            'subscriptions' => $createdSubs,
            'gifts' => $createdGifts,
            'revenue' => Transaction::where('status', 'success')->sum('amount'),
            'premium_users' => User::where('is_premium', true)->count(),
            'total_credits' => UserBalance::sum('credits'),
        ];

        $this->command->info('✅ Финансовая история сгенерирована!');
        $this->command->info('');
        $this->command->info('📊 Статистика:');
        $this->command->info("   • Создано транзакций: {$stats['transactions']}");
        $this->command->info("   • Создано подписок: {$stats['subscriptions']}");
        $this->command->info("   • Создано подарков: {$stats['gifts']}");
        $this->command->info("   • Выручка (Success): {$stats['revenue']} ₽");
        $this->command->info("   • VIP-юзеров синхронизировано: {$stats['premium_users']}");
        $this->command->info("   • Кредитов на балансах: {$stats['total_credits']}");
    }
}