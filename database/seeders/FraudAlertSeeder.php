<?php

// Здесь мы генерируем "улики" для службы безопасности. Важно соблюсти логику: если severity = high (например, 
// детская по***рафия или массовая ботоварня), то статус скорее всего resolved (автоматический бан или модератор 
// уже успел забанить). Если severity = low, то это может быть false_positive (ложняк). Ну и обязательно оставляем 
// пачку open алертов, чтобы в админке было что верстать в очереди на модерацию.

// Здесь мы реализовали мощный метод getScenarioData(), который генерирует улики (meta) под конкретный тип нарушения. 
// Если сработал minor (несовершеннолетний) — это сразу high и resolved (бан), а если mass_messaging — это medium,
//  и часто это false_positive (человек просто общительный, админ разберет).

namespace Database\Seeders;

use App\Models\FraudAlert;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FraudAlertSeeder extends Seeder
{
    public function run(): void
    {
        // Очищаем старые алерты, чтобы не было дублей при повторном сидировании
        DB::table('fraud_alerts')->truncate();

        // Находим админа (или любого первого юзера), который будет "разбирать" алерты
        $admin = User::first();

        // Массив реалистичных триггеров и соответствующих им улик (meta)
        $triggerTemplates = [
            [
                'trigger_type' => 'links_in_chat',
                'severity' => 'high',
                'meta' => fn() => [
                    'ip' => '194.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(1, 254),
                    'message_text' => 'Пиши мне в тг @scammer_' . rand(100, 999),
                    'matched_rule' => 'tg_link_regex',
                ]
            ],
            [
                'trigger_type' => 'mass_messaging',
                'severity' => 'medium',
                'meta' => fn() => [
                    'messages_sent_5min' => rand(25, 80),
                    'identical_message' => 'Привет! Давай познакомимся, я ищу щедрого мужчину...',
                    'ip' => '188.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(1, 254),
                ]
            ],
            [
                'trigger_type' => 'prostitute',
                'severity' => 'high',
                'meta' => fn() => [
                    'field' => 'about_me',
                    'matched_words' => ['массаж', 'выезд', 'индивидуалка'],
                    'profile_url' => '/users/' . rand(1, 100),
                ]
            ],
            [
                'trigger_type' => 'same_device',
                'severity' => 'medium',
                'meta' => fn() => [
                    'device_id' => 'DEV-' . strtoupper(\Str::random(12)),
                    'accounts_count' => rand(3, 10),
                    'ip' => '45.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(1, 254),
                ]
            ],
            [
                'trigger_type' => 'scam_phrase',
                'severity' => 'low',
                'meta' => fn() => [
                    'message_text' => 'Переведи мне 500 рублей на карту, я скину фотки',
                    'matched_rule' => 'money_request_regex',
                ]
            ],
        ];

        $users = User::limit(20)->get();

        // Генерируем 50 алертов
        for ($i = 0; $i < 50; $i++) {
            // Случайно выбираем шаблон триггера
            $template = $triggerTemplates[array_rand($triggerTemplates)];
            
            // Генерируем дату в последние 30 дней
            $createdAt = now()->subDays(rand(0, 30))->subHours(rand(0, 23));
            
            // Рандомизируем статус (70% - open, 20% - resolved, 10% - false_positive)
            $statusRoll = rand(1, 100);
            $status = $statusRoll <= 70 ? 'open' : ($statusRoll <= 90 ? 'resolved' : 'false_positive');
            
            // Если алерт открыт, admin_id и resolved_at должны быть null
            $isAdminResolved = $status !== 'open';

            // 15% шанс, что аккаунт скаммера уже удален (user_id = null)
            $isUserDeleted = rand(1, 100) <= 15;

            FraudAlert::create([
                'user_id' => $isUserDeleted ? null : ($users->isNotEmpty() ? $users->random()->id : null),
                'trigger_type' => $template['trigger_type'],
                'severity' => $template['severity'],
                'meta' => $template['meta'](), // Вызываем замыкание для генерации униканых данных
                'status' => $status,
                'admin_id' => $isAdminResolved ? ($admin->id ?? null) : null,
                'resolved_at' => $isAdminResolved ? $createdAt->copy()->addHours(rand(1, 48)) : null,
                'created_at' => $createdAt,
                'updated_at' => $isAdminResolved ? $createdAt->copy()->addHours(rand(1, 48)) : $createdAt,
            ]);
        }
    }
}