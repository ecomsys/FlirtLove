<?php

// Разбор архитектуры:

// Реализм метаданных (meta JSON): Если платеж упал с ошибкой, мы пишем причину в meta ("Недостаточно средств"). 
// Если возврат — пишем refunded_at. В админке, когда саппорт откроет транзакцию, он сразу увидит, почему она не 
// прошла или когда были возвращены деньги.
// provider_transaction_id: Сгенерирован через Str::uuid(). Выглядит точь-в-точь как реальный ID от Stripe или ЮKassa.
// Разделение выручки: В статистике мы считаем revenue только по успешным платежам, а возвраты выводим отдельно. 
// Это стандартный формат финтех-отчетов.


namespace Database\Seeders;

use App\Models\Transaction;
use App\Models\User;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('💳 Генерируем финансовую историю (транзакции)...');

        $users = User::where('role', 'user')->get();
        $plans = SubscriptionPlan::where('is_active', true)->get();

        if ($users->isEmpty()) {
            $this->command->warn('⚠️ Нет пользователей для транзакций!');
            return;
        }

        $deletedCount = Transaction::count();
        if ($deletedCount > 0) {
            Transaction::query()->delete();
            $this->command->info("🗑️ Удалено {$deletedCount} старых транзакций");
        }

        $providers = ['yookassa', 'stripe', 'apple', 'google', 'manual'];
        $statuses = ['success', 'success', 'success', 'success', 'failed', 'refunded', 'pending']; // Успешных больше всего

        // Пакеты кредитов для теста микротранзакций
        $creditPacks = [
            ['credits' => 100, 'price' => 99.00],
            ['credits' => 500, 'price' => 399.00],
            ['credits' => 1000, 'price' => 699.00],
        ];

        $totalToCreate = 60;
        $bar = $this->command->getOutput()->createProgressBar($totalToCreate);
        $createdCount = 0;

        for ($i = 0; $i < $totalToCreate; $i++) {
            $user = $users->random();
            $provider = $providers[array_rand($providers)];
            $status = $statuses[array_rand($statuses)];
            
            // 60% — подписки, 40% — кредиты
            $isSubscription = rand(0, 100) >= 40;

            if ($isSubscription && $plans->isNotEmpty()) {
                $plan = $plans->random();
                $type = 'subscription';
                $amount = $plan->price;
                $creditsAmount = null;
                $meta = [
                    'plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                    'duration_days' => $plan->duration_days,
                ];
            } else {
                $pack = $creditPacks[array_rand($creditPacks)];
                $type = 'credits';
                $amount = $pack['price'];
                $creditsAmount = $pack['credits'];
                $meta = [
                    'credits_purchased' => $pack['credits'],
                ];
            }

            // Если платеж возвращен — добавляем инфу в meta
            if ($status === 'refunded') {
                $meta['refund_reason'] = 'Запрошено пользователем (Chargeback)';
                $meta['refunded_at'] = now()->subDays(rand(0, 5))->toDateTimeString();
            } 
            // Если ошибка — пишем причину
            elseif ($status === 'failed') {
                $meta['fail_reason'] = 'Недостаточно средств на карте (Insufficient funds)';
            }

            Transaction::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'currency' => 'RUB',
                'type' => $type,
                'status' => $status,
                'provider' => $provider,
                // Генерируем фейковый ID транзакции от платежки
                'provider_transaction_id' => Str::uuid()->toString(),
                'credits_amount' => $creditsAmount,
                'meta' => $meta,
                'created_at' => now()->subDays(rand(0, 60)),
                'updated_at' => now()->subDays(rand(0, 10)),
            ]);

            $createdCount++;
            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine(2);

        // ============================================
        // СТАТИСТИКА
        // ============================================
        $stats = [
            'total' => Transaction::count(),
            'success' => Transaction::where('status', 'success')->count(),
            'failed' => Transaction::where('status', 'failed')->count(),
            'refunded' => Transaction::where('status', 'refunded')->count(),
            'pending' => Transaction::where('status', 'pending')->count(),
            'revenue' => Transaction::where('status', 'success')->sum('amount'),
            'refunded_amount' => Transaction::where('status', 'refunded')->sum('amount'),
            'subscriptions' => Transaction::where('type', 'subscription')->count(),
            'credits' => Transaction::where('type', 'credits')->count(),
        ];

        $this->command->info('✅ Создано транзакций: ' . $stats['total']);
        $this->command->info('');
        $this->command->info('📊 Финансовая статистика:');
        $this->command->info("   ┌───────────────────────────┬──────────────┐");
        $this->command->info("   │ Показатель               │ Значение     │");
        $this->command->info("   ├───────────────────────────┼──────────────┤");
        $this->command->info("   │ Всего транзакций         │ {$stats['total']}            │");
        $this->command->info("   │ Успешных                 │ {$stats['success']}            │");
        $this->command->info("   │ Ошибок                   │ {$stats['failed']}            │");
        $this->command->info("   │ Возвратов (Refund)       │ {$stats['refunded']}            │");
        $this->command->info("   │ В ожидании               │ {$stats['pending']}            │");
        $this->command->info("   ├───────────────────────────┼──────────────┤");
        $this->command->info("   │ Подписок куплено         │ {$stats['subscriptions']}            │");
        $this->command->info("   │ Покупок кредитов         │ {$stats['credits']}            │");
        $this->command->info("   ├───────────────────────────┼──────────────┤");
        $this->command->info("   │ Выручка (Success)        │ {$stats['revenue']} ₽       │");
        $this->command->info("   │ Возвращено (Refund)      │ {$stats['refunded_amount']} ₽       │");
        $this->command->info("   └───────────────────────────┴──────────────┘");
    }
}