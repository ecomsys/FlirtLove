<?php

namespace Database\Seeders;

use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\SubscriptionPlan;
use App\Models\UserBoost;
use App\Models\UserBalance;
use App\Models\UserGift;
use App\Models\Gift;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FinanceHistorySeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('💰 Генерируем жирную финансовую историю...');

        // 1. Очистка старых данных (в строгом порядке из-за Foreign Keys)
        UserGift::query()->delete();
        UserBoost::query()->delete();
        UserSubscription::query()->delete();
        Transaction::query()->delete();
        
        // Сбрасываем ВСЕ флаги монетизации у юзеров
        User::query()->update([
            'is_premium' => false, 'premium_expires_at' => null,
            'is_vip' => false, 'vip_expires_at' => null,
        ]);

        // Сбрасываем балансы (только кредиты и суперлайки)
        UserBalance::query()->update([
            'credits' => 0,
            'superlikes_remaining' => 1,
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
        $createdBoosts = 0;
        $createdGifts = 0;

        foreach ($users as $user) {
            
            // Даем историю 80% юзеров
            if (rand(1, 10) <= 8) {
                
                $purchasesCount = rand(1, 4);
                
                for ($i = 0; $i < $purchasesCount; $i++) {
                    // 60% подписка, 40% покупка кредитов
                    $roll = rand(1, 100);
                    
                    if ($roll <= 60) {
                        // --- ЭТАП 1: Покупка подписки (Premium или VIP) ---
                        $plan = $plans->random();
                        $transDate = now()->subDays(rand(1, 120));
                        $provider = ['yookassa', 'stripe', 'apple', 'google', 'manual'][array_rand(['yookassa', 'stripe', 'apple', 'google', 'manual'])];
                        
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
                                'tier' => $plan->tier,
                                'fail_reason' => $transStatus === 'failed' ? 'Недостаточно средств на карте' : null,
                            ],
                            'created_at' => $transDate,
                            'updated_at' => $transDate,
                        ]);
                        $createdTrans++;

                        if ($isSuccess) {
                            $startsAt = $transDate->copy()->addMinutes(5);
                            $endsAt = $startsAt->copy()->addDays($plan->duration_days);
                            
                            // Статус строго привязан к времени!
                            if ($endsAt->isPast()) {
                                $subStatus = 'expired';
                                $canceledAt = null;
                            } else {
                                $subStatus = rand(1, 10) <= 8 ? 'active' : 'canceled';
                                $canceledAt = ($subStatus === 'canceled') ? now()->subDays(rand(1, 5)) : null;
                            }

                            UserSubscription::create([
                                'user_id' => $user->id,
                                'plan_id' => $plan->id,
                                'transaction_id' => $trans->id,
                                'tier' => $plan->tier,
                                'starts_at' => $startsAt,
                                'ends_at' => $endsAt,
                                'is_auto_renew' => ($subStatus === 'active') ? (bool) rand(0, 1) : false,
                                'provider_subscription_id' => ($subStatus === 'active' && rand(0,1)) ? 'sub_' . Str::random(24) : null,
                                'status' => $subStatus,
                                'canceled_at' => $canceledAt,
                                'created_at' => $startsAt,
                                'updated_at' => $startsAt,
                            ]);
                            $createdSubs++;

                            // Синхронизируем кэш в users с правильным стеканием дат!
                            if ($subStatus === 'active') {
                                if ($plan->tier === 'premium') {
                                    $startFrom = $user->premium_expires_at && $user->premium_expires_at->isFuture() 
                                                 ? $user->premium_expires_at 
                                                 : now();
                                    $user->update([
                                        'is_premium' => true, 
                                        'premium_expires_at' => $startFrom->copy()->addDays($plan->duration_days)
                                    ]);
                                } elseif ($plan->tier === 'vip') {
                                    $startFrom = $user->vip_expires_at && $user->vip_expires_at->isFuture() 
                                                 ? $user->vip_expires_at 
                                                 : now();
                                    $user->update([
                                        'is_vip' => true, 
                                        'vip_expires_at' => $startFrom->copy()->addDays($plan->duration_days)
                                    ]);
                                }
                            }
                        }
                    } 
                    else {
                        // --- ЭТАП 2: Покупка кредитов (Единиц) ---
                        // 1 кредит = 1 рубль. Паки как в топовых приложениях.
                        $creditPacks = [
                            ['credits' => 80, 'price' => 80.00],
                            ['credits' => 300, 'price' => 250.00],
                            ['credits' => 650, 'price' => 500.00],
                            ['credits' => 1250, 'price' => 900.00],
                        ];
                        $creditPack = $creditPacks[array_rand($creditPacks)];
                        $creditDate = now()->subDays(rand(1, 60));
                        
                        Transaction::create([
                            'user_id' => $user->id,
                            'amount' => $creditPack['price'],
                            'currency' => 'RUB',
                            'type' => 'credits',
                            'status' => 'success',
                            'provider' => 'yookassa',
                            'provider_transaction_id' => Str::uuid()->toString(),
                            'credits_amount' => $creditPack['credits'],
                            'meta' => ['credits_purchased' => $creditPack['credits']],
                            'created_at' => $creditDate,
                            'updated_at' => $creditDate,
                        ]);
                        $createdTrans++;

                        $balance = UserBalance::firstOrCreate(['user_id' => $user->id]);
                        $balance->addCredits($creditPack['credits']);
                    }
                } // Конец цикла покупок
                
                // --- ЭТАП 3: Трата кредитов на Бусты и Подарки ---
                $user->refresh(); // Обновляем юзера, чтобы получить свежий баланс
                $balance = $user->balance;

                if ($balance && $balance->credits > 0) {
                    
                    // 3.1 Активация Бустов (1 буст = 80 кредитов)
                    $boostAttempts = rand(0, 3);
                    for ($b = 0; $b < $boostAttempts; $b++) {
                        if ($balance->credits >= 80) {
                            $balance->spendCredits(80); // Списываем 80 единиц
                            $boostDate = now()->subDays(rand(0, 15));
                            
                            UserBoost::create([
                                'user_id' => $user->id,
                                'transaction_id' => null,
                                'type' => 'profile_boost',
                                'starts_at' => $boostDate,
                                'ends_at' => $boostDate->copy()->addMinutes(30), // Длится 30 минут
                                'status' => 'expired', // Дата в прошлом, значит истек
                                'created_at' => $boostDate,
                                'updated_at' => $boostDate,
                            ]);
                            $createdBoosts++;
                        }
                    }

                    // 3.2 Покупка подарков
                    if ($gifts->isNotEmpty()) {
                        $giftAttempts = rand(0, 3);
                        for ($g = 0; $g < $giftAttempts; $g++) {
                            // Находим подарок, на который хватает кредитов
                            $affordableGifts = $gifts->filter(fn($gift) => $gift->price <= $balance->credits);
                            if ($affordableGifts->isEmpty()) break;

                            $gift = $affordableGifts->random();
                            $balance->spendCredits($gift->price);
                            
                            $receiver = $users->where('id', '!=', $user->id)->random();
                            $giftDate = now()->subDays(rand(0, 15));
                            $isRead = (bool) rand(0, 1);
                            
                            UserGift::create([
                                'sender_id' => $user->id,
                                'receiver_id' => $receiver->id,
                                'gift_id' => $gift->id,
                                'snapshot_name' => $gift->name,
                                'snapshot_image_url' => $gift->image_url,
                                'snapshot_price' => $gift->price,
                                'message' => 'Подарок от тестового сида!',
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
            'boosts' => $createdBoosts,
            'gifts' => $createdGifts,
            'revenue' => Transaction::where('status', 'success')->sum('amount'),
            'premium_users' => User::where('is_premium', true)->count(),
            'vip_users' => User::where('is_vip', true)->count(),
            'total_credits' => UserBalance::sum('credits'),
        ];

        $this->command->info('✅ Финансовая история сгенерирована!');
        $this->command->info('');
        $this->command->info('📊 Статистика:');
        $this->command->info("   • Создано транзакций: {$stats['transactions']}");
        $this->command->info("   • Создано подписок: {$stats['subscriptions']}");
        $this->command->info("   • Активировано бустов: {$stats['boosts']}");
        $this->command->info("   • Отправлено подарков: {$stats['gifts']}");
        $this->command->info("   • Выручка (Success): {$stats['revenue']} ₽");
        $this->command->info("   • Активных Premium: {$stats['premium_users']}");
        $this->command->info("   • Активных VIP: {$stats['vip_users']}");
        $this->command->info("   • Кредитов на балансах: {$stats['total_credits']}");
    }
}