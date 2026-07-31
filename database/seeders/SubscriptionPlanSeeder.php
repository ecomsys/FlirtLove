<?php

// Разбор архитектуры:

// JSON Фичи (features): Это киллер-фича твоего бэкенда. В middleware мы будем писать: 
// if (auth()->user()->plan->hasFeature('invisible')). Эти тестовые данные позволят нам полностью проверить, 
// как фичи включаются и выключаются в зависимости от тарифа. Годовой тариф дает безлимит (999), а месячный —
//  всего 50 лайков.
// Apple/Google ID: Заполнены плейсхолдерами вида vip_365_days. Когда ты будешь делать мобильное приложение, 
// тебе останется только вбить такие же ID в консолях App Store и Google Play, и сервер автоматически начнет
//  понимать, за какой продукт пришла оплата.
// Архивный тариф: VIP OLD (Архив) со статусом is_active = false спрятан. На фронте он не покажется, но если 
// в БД есть старые юзеры, купившие его год назад, их подписка в админке будет корректно ссылаться на этот тариф.
// trial_days: У полугодового и годового тарифа есть триал на 7 дней. Это позволит протестировать логику
//  создания рекуррентного платежа с триал-периодом.

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('💎 Создаем тарифы (планы подписок)...');

        $deletedCount = SubscriptionPlan::count();
        if ($deletedCount > 0) {
            SubscriptionPlan::query()->delete();
            $this->command->info("🗑️ Удалено {$deletedCount} старых тарифов");
        }

        $plans = [
            [
                'name' => 'VIP на 1 месяц',
                'price' => 499.00,
                'duration_days' => 30,
                'trial_days' => 0,
                'features' => [
                    'invisible' => false, // Невидимка только на 3+ мес
                    'likes_per_day' => 50,
                    'superlikes_per_day' => 5,
                    'hide_ads' => true,
                    'can_see_who_liked' => false, // Кто лайкнул только на 6+ мес
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'VIP на 3 месяца',
                'price' => 1299.00,
                'duration_days' => 90,
                'trial_days' => 0,
                'features' => [
                    'invisible' => true,
                    'likes_per_day' => 100,
                    'superlikes_per_day' => 10,
                    'hide_ads' => true,
                    'can_see_who_liked' => false,
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'VIP на 6 месяцев',
                'price' => 2399.00,
                'duration_days' => 180,
                'trial_days' => 7, // Даем неделю триала
                'features' => [
                    'invisible' => true,
                    'likes_per_day' => 150,
                    'superlikes_per_day' => 15,
                    'hide_ads' => true,
                    'can_see_who_liked' => true,
                ],
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'VIP на 1 год (Выгодный)',
                'price' => 3990.00,
                'duration_days' => 365,
                'trial_days' => 7,
                'features' => [
                    'invisible' => true,
                    'likes_per_day' => 999, // Безлимит
                    'superlikes_per_day' => 30,
                    'hide_ads' => true,
                    'can_see_who_liked' => true,
                ],
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'VIP OLD (Архив)',
                'price' => 999.00,
                'duration_days' => 60,
                'trial_days' => 0,
                'features' => [
                    'invisible' => false,
                    'likes_per_day' => 20,
                    'superlikes_per_day' => 2,
                    'hide_ads' => true,
                    'can_see_who_liked' => false,
                ],
                'is_active' => false, // Скрыли из продажи, но в БД остался для старых юзеров
                'sort_order' => 99,
            ],
        ];

        $bar = $this->command->getOutput()->createProgressBar(count($plans));
        $createdCount = 0;

        foreach ($plans as $plan) {
            SubscriptionPlan::create([
                'name' => $plan['name'],
                'slug' => Str::slug($plan['name']),
                'price' => $plan['price'],
                'currency' => 'RUB',
                'duration_days' => $plan['duration_days'],
                'trial_days' => $plan['trial_days'],
                'features' => $plan['features'],
                // Имитируем ID продуктов для мобильных приложений
                'apple_product_id' => 'com.loveplanet.vip.' . $plan['duration_days'],
                'google_product_id' => 'vip_' . $plan['duration_days'] . '_days',
                'is_active' => $plan['is_active'],
                'sort_order' => $plan['sort_order'],
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
            'total' => SubscriptionPlan::count(),
            'active' => SubscriptionPlan::where('is_active', true)->count(),
            'inactive' => SubscriptionPlan::where('is_active', false)->count(),
        ];

        $this->command->info('✅ Создано тарифов: ' . $stats['total']);
        $this->command->info('');
        $this->command->info('📊 Статистика тарифов:');
        $this->command->info("   ┌───────────────────────┬──────────┐");
        $this->command->info("   │ Показатель            │ Кол-во   │");
        $this->command->info("   ├───────────────────────┼──────────┤");
        $this->command->info("   │ Всего                 │ {$stats['total']}        │");
        $this->command->info("   │ Активных (в продаже)  │ {$stats['active']}        │");
        $this->command->info("   │ Скрытых (архив)       │ {$stats['inactive']}        │");
        $this->command->info("   └───────────────────────┴──────────┘");
    }
}