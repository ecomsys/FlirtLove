<?php


// UserSubscriptionSeeder — это архив покупок VIP-статусов.

// Здесь мы свяжем юзеров, тарифы и транзакции воедино. Чтобы протестировать крон-задачи (снятие VIP и пуш-уведомления), 
// мы сгенерируем активные подписки, истекшие (вчера или месяц назад) и отмененные (когда юзер нажал "отменить автопродление", 
// но VIP еще работает).

// Разбор архитектуры (Синхронизация):

// Жизненный цикл: Сидер генерирует реалистичные сценарии. Если статус expired — ends_at принудительно устанавливается в 
// прошлое (1-15 дней назад). Это позволит нам прямо сейчас протестировать крон-задачу, которая снимает is_premium у юзеров с 
// истекшей подпиской.
// Связь с Транзакциями: Мы ищем успешную транзакцию юзера и связываем подписку с ней (transaction_id). В админке при просмотре 
// подписки ты сможешь в один клик перейти к чеку об оплате.
// Синхронизация users.is_premium: Это критически важный момент! Если юзеру создали активную подписку, мы обновляем поле is_premium = true
// и premium_expires_at в таблице users. Без этого middleware не будет пускать юзера в VIP-разделы. Теперь таблицы полностью 
// синхронизированы.


namespace Database\Seeders;

use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('👑 Генерируем историю VIP-подписок...');

        $users = User::where('role', 'user')->get();
        $plans = SubscriptionPlan::all(); // Берем все, включая архивные

        if ($users->isEmpty() || $plans->isEmpty()) {
            $this->command->warn('⚠️ Нет пользователей или тарифов!');
            return;
        }

        $deletedCount = UserSubscription::count();
        if ($deletedCount > 0) {
            UserSubscription::query()->delete();
            $this->command->info("🗑️ Удалено {$deletedCount} старых подписок");
        }

        $bar = $this->command->getOutput()->createProgressBar($users->count());
        $createdCount = 0;

        foreach ($users as $user) {
            // Даем подписку примерно 60% юзеров
            if (rand(0, 100) >= 40) {
                $plan = $plans->random();
                
                // Ищем успешную транзакцию этого юзера для связи
                $transaction = Transaction::where('user_id', $user->id)
                    ->where('status', 'success')
                    ->where('type', 'subscription')
                    ->inRandomOrder()
                    ->first();

                // Рандомный статус подписки (70% active, 20% expired, 10% canceled)
                $statusRoll = rand(1, 10);
                $status = $statusRoll <= 7 ? 'active' : ($statusRoll <= 9 ? 'expired' : 'canceled');

                $startsAt = now()->subDays(rand(10, 100));
                $endsAt = (clone $startsAt)->addDays($plan->duration_days);

                // Если статус expired, гарантируем, что дата уже прошла
                if ($status === 'expired') {
                    $endsAt = now()->subDays(rand(1, 15));
                }

                // Если статус canceled, значит автопродление отключено, но подписка может быть еще активна по времени
                $isAutoRenew = ($status === 'active') ? (bool) rand(0, 1) : false;
                $canceledAt = (!$isAutoRenew && $status === 'canceled') ? now()->subDays(rand(1, 5)) : null;

                UserSubscription::create([
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'transaction_id' => $transaction?->id, // Связь с транзакцией (если есть)
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'is_auto_renew' => $isAutoRenew,
                    // Имитация ID подписки в платежке (нужна для отмены через API)
                    'provider_subscription_id' => $isAutoRenew ? 'sub_' . Str::random(24) : null,
                    'status' => $status,
                    'canceled_at' => $canceledAt,
                    'created_at' => $startsAt,
                    'updated_at' => now()->subDays(rand(0, 5)),
                ]);

                // Синхронизируем флаг is_premium в таблице users для active подписок
                if ($status === 'active' && $endsAt->isFuture()) {
                    $user->update([
                        'is_premium' => true,
                        'premium_expires_at' => $endsAt,
                    ]);
                }

                $createdCount++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine(2);

        // ============================================
        // СТАТИСТИКА
        // ============================================
        $stats = [
            'total' => UserSubscription::count(),
            'active' => UserSubscription::where('status', 'active')->count(),
            'expired' => UserSubscription::where('status', 'expired')->count(),
            'canceled' => UserSubscription::where('status', 'canceled')->count(),
            'auto_renew' => UserSubscription::where('is_auto_renew', true)->count(),
            'premium_users' => User::where('is_premium', true)->count(),
        ];

        $this->command->info('✅ Создано записей о подписках: ' . $stats['total']);
        $this->command->info('');
        $this->command->info('📊 Статистика подписок:');
        $this->command->info("   ┌───────────────────────────┬──────────┐");
        $this->command->info("   │ Показатель               │ Кол-во   │");
        $this->command->info("   ├───────────────────────────┼──────────┤");
        $this->command->info("   │ Всего подписок           │ {$stats['total']}        │");
        $this->command->info("   │ Активных                 │ {$stats['active']}        │");
        $this->command->info("   │ Истекших                 │ {$stats['expired']}        │");
        $this->command->info("   │ Отмененных (CANCELED)    │ {$stats['canceled']}        │");
        $this->command->info("   ├───────────────────────────┼──────────┤");
        $this->command->info("   │ С автопродлением         │ {$stats['auto_renew']}        │");
        $this->command->info("   │ Всего VIP-юзеров в users │ {$stats['premium_users']}        │");
        $this->command->info("   └───────────────────────────┴──────────┘");
    }
}