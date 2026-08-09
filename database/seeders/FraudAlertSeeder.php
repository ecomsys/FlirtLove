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

class FraudAlertSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🚨 Генерируем антифрод-алерты и улики мошенников...');

        $users = User::where('role', 'user')->get();
        $admin = User::where('role', 'admin')->first();

        if ($users->isEmpty()) {
            $this->command->warn('⚠️ Нет пользователей для генерации алертов!');
            return;
        }

        // Очистка старых алертов
        $deletedCount = FraudAlert::count();
        if ($deletedCount > 0) {
            FraudAlert::query()->delete();
            $this->command->info("🗑️ Удалено {$deletedCount} старых алертов");
        }

        $triggers = [
            'same_device',      // 10 аккаунтов с одного телефона
            'mass_messaging',   // Спам одинаковыми сообщениями
            'links_in_chat',    // Кинул ссылку на телеграм (Сработал StopWord Honeypot)
            'prostitute',       // Стоп-слова проституции
            'scam_keywords',    // Стоп-слова мошенничества (крипта, инвестиции)
            'photo_nudity',     // ИИ распознал 18+ в основной фотке
            'minor',            // ИИ или модератор заподозрил несовершеннолетнего
        ];

        $bar = $this->command->getOutput()->createProgressBar(30);
        $createdCount = 0;

        for ($i = 0; $i < 30; $i++) {
            $user = $users->random();
            $trigger = $triggers[array_rand($triggers)];

            // Определяем уровень опасности и статус на основе триггера
            [$severity, $status, $meta] = $this->getScenarioData($trigger, $user);

            $resolvedAt = null;
            $adminId = null;

            // Если статус resolved или false_positive — нужен админ и дата
            if ($status !== 'open' && $admin) {
                $adminId = $admin->id;
                $resolvedAt = now()->subDays(rand(0, 5));
            }

            FraudAlert::create([
                'user_id' => $user->id,
                'trigger_type' => $trigger,
                'severity' => $severity,
                'meta' => $meta,
                'status' => $status,
                'admin_id' => $adminId,
                'resolved_at' => $resolvedAt,
                'created_at' => now()->subDays(rand(0, 15)),
                'updated_at' => now()->subDays(rand(0, 5)),
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
            'total' => FraudAlert::count(),
            'open' => FraudAlert::where('status', 'open')->count(),
            'resolved' => FraudAlert::where('status', 'resolved')->count(),
            'false_positive' => FraudAlert::where('status', 'false_positive')->count(),
            'high' => FraudAlert::where('severity', 'high')->count(),
            'medium' => FraudAlert::where('severity', 'medium')->count(),
            'low' => FraudAlert::where('severity', 'low')->count(),
        ];

        $this->command->info('✅ Создано антифрод-алертов: ' . $stats['total']);
        $this->command->info('');
        $this->command->info('📊 Статистика угроз:');
        $this->command->info("   ┌─────────────────────────┬──────────┐");
        $this->command->info("   │ Показатель              │ Кол-во   │");
        $this->command->info("   ├─────────────────────────┼──────────┤");
        $this->command->info("   │ Всего алертов           │ {$stats['total']}        │");
        $this->command->info("   │ 🔴 Ожидают проверки     │ {$stats['open']}        │");
        $this->command->info("   │ 🟢 Подтверждены (Бан)   │ {$stats['resolved']}        │");
        $this->command->info("   │ ⚪ Ложные срабатывания   │ {$stats['false_positive']}        │");
        $this->command->info("   ├─────────────────────────┼──────────┤");
        $this->command->info("   │ Опасность: High 🔥      │ {$stats['high']}        │");
        $this->command->info("   │ Опасность: Medium ⚠️    │ {$stats['medium']}        │");
        $this->command->info("   │ Опасность: Low 🧊       │ {$stats['low']}        │");
        $this->command->info("   └─────────────────────────┴──────────┘");
    }

    /**
     * Генерация реалистичного сценария на основе типа триггера.
     * Возвращает [severity, status, meta]
     */
    private function getScenarioData(string $trigger, User $user): array
    {
        $ip = '185.23.' . rand(1, 255) . '.' . rand(1, 255);

        return match ($trigger) {
            'same_device' => [
                rand(0, 1) ? 'high' : 'medium',
                rand(0, 1) ? 'resolved' : 'open',
                ['device_id' => 'dev_' . substr(md5($ip), 0, 10), 'ip' => $ip, 'accounts_found' => rand(3, 15)]
            ],
            'mass_messaging' => [
                'medium',
                rand(0, 1) ? 'open' : 'false_positive', // Часто бывает что юзер просто активный
                ['messages_sent_1h' => rand(30, 150), 'sample_text' => 'Привет! Пиши мне в телеграм @scammer']
            ],
            'links_in_chat' => [
                'medium',
                'open',
                ['matched_rule' => 'telegram_link', 'message_text' => 'Давай перейдем в тг, тут неудобно', 'chat_id' => rand(1, 50)]
            ],
            'prostitute' => [
                'high',
                rand(0, 1) ? 'resolved' : 'open',
                ['matched_rule' => 'prostitution_keywords', 'profile_text' => 'Предлагаю досуг...']
            ],
            'scam_keywords' => [
                'high',
                'open',
                ['matched_rule' => 'crypto_investment', 'message_text' => 'Заработок на крипте, вкладывай сюда']
            ],
            'photo_nudity' => [
                'high',
                rand(0, 1) ? 'resolved' : 'open',
                ['photo_id' => rand(1, 100), 'ai_confidence' => rand(85, 99) . '%', 'model' => 'NSFW_Detector_v2']
            ],
            'minor' => [
                'high', // Самый высокий приоритет!
                'resolved', // Обычно банят мгновенно
                ['photo_id' => rand(1, 100), 'estimated_age' => rand(12, 16), 'model' => 'Age_Estimator_v1']
            ],
            default => ['low', 'open', []]
        };
    }
}