<?php

namespace Database\Seeders;

use App\Models\Broadcast;
use App\Models\User;
use Illuminate\Database\Seeder;

class BroadcastSeeder extends Seeder
{
    public function run(): void
    {
        // ✅ Берем только обычных юзеров
        $users = User::where('is_admin', false)->get();

        if ($users->isEmpty()) {
            $this->command->warn('⚠️ Нет пользователей для создания рассылок!');
            return;
        }

        $types = ['system', 'email', 'push'];
        $statuses = ['draft', 'sent', 'scheduled'];

        $templates = [
            [
                'title' => 'Добро пожаловать на LovePlanet!',
                'message' => 'Рады приветствовать вас на нашем сайте знакомств. Заполните свой профиль, загрузите фото и начните общение!',
                'type' => 'system',
            ],
            [
                'title' => 'Ваш профиль прошел модерацию',
                'message' => 'Поздравляем! Ваша анкета проверена и опубликована. Теперь другие пользователи могут вас найти.',
                'type' => 'system',
            ],
            [
                'title' => 'У вас новое сообщение 💬',
                'message' => 'Пользователь Иван отправил вам сообщение. Перейдите в чат, чтобы ответить.',
                'type' => 'push',
            ],
            [
                'title' => '🔥 У вас новый лайк!',
                'message' => 'Кому-то понравился ваш профиль. Посмотрите, кто это!',
                'type' => 'push',
            ],
            [
                'title' => 'Подписка на премиум активирована',
                'message' => 'Спасибо за покупку! Все возможности сайта теперь доступны. Удачи в поиске!',
                'type' => 'email',
            ],
            [
                'title' => 'Заполните профиль до конца',
                'message' => 'У вас заполнено только 40% профиля. Добавьте фото и информацию о себе — это повысит шансы на знакомство.',
                'type' => 'system',
            ],
            [
                'title' => 'Время обновить фото',
                'message' => 'Давно не обновляли фото? Новые снимки привлекают больше внимания! 😉',
                'type' => 'system',
            ],
            [
                'title' => 'Ваша подписка заканчивается',
                'message' => 'Премиум доступ истекает через 3 дня. Продлите подписку, чтобы не терять преимущества!',
                'type' => 'email',
            ],
            [
                'title' => 'Новый пользователь в вашем городе',
                'message' => 'Анна (22 года) только что зарегистрировалась в Москве. Посмотрите её профиль!',
                'type' => 'push',
            ],
            [
                'title' => 'Совет дня: как привлечь внимание',
                'message' => 'Вставьте интересный факт о себе в описание — это отличный способ начать разговор!',
                'type' => 'system',
            ],
            [
                'title' => 'Акция: скидка на премиум',
                'message' => 'Только до конца недели — 30% на все подписки. Успейте! 🎉',
                'type' => 'email',
            ],
            [
                'title' => 'Ваш аккаунт верифицирован',
                'message' => 'Поздравляем! Вы прошли верификацию. Ваш профиль отмечен специальным значком.',
                'type' => 'system',
            ],
            [
                'title' => '💔 Мы скучаем!',
                'message' => 'Вы давно не заходили на сайт. Вернитесь — вас ждут новые знакомства!',
                'type' => 'email',
            ],
            [
                'title' => '🌟 Топ-5 пользователей недели',
                'message' => 'Посмотрите, кто вошел в топ самых активных пользователей на этой неделе!',
                'type' => 'push',
            ],
        ];

        // Очищаем старые рассылки (без truncate)
        $deletedCount = Broadcast::count();
        if ($deletedCount > 0) {
            Broadcast::query()->delete();
            $this->command->info("🗑️ Удалено {$deletedCount} старых рассылок");
        }

        $this->command->info('📨 Создаем рассылки для пользователей...');

        $bar = $this->command->getOutput()->createProgressBar(count($templates) * 2);
        $createdCount = 0;

        // ============================================
        // 1. ПЕРСОНАЛЬНЫЕ РАССЫЛКИ (для конкретных юзеров)
        // ============================================
        foreach ($templates as $template) {
            $count = rand(1, 3);
            
            for ($i = 0; $i < $count; $i++) {
                $status = $statuses[array_rand($statuses)];
                $user = $users->random();
                
                Broadcast::create([
                    'user_id' => $user->id,
                    'type' => $template['type'],
                    'title' => $template['title'],
                    'message' => $template['message'],
                    'status' => $status,
                    'scheduled_at' => $status === 'scheduled' ? now()->addDays(rand(1, 5)) : null,
                    'sent_at' => $status === 'sent' ? now()->subDays(rand(0, 10)) : null,
                    'data' => [
                        'user_name' => $user->name,
                        'user_email' => $user->email,
                    ],
                    'created_at' => now()->subDays(rand(0, 30)),
                ]);

                $createdCount++;
                $bar->advance();
            }
        }

        // ============================================
        // 2. МАССОВЫЕ РАССЫЛКИ (user_id = null)
        // ============================================
        $this->command->newLine();
        $this->command->info('   📢 Создаем массовые рассылки...');

        $massTemplates = [
            [
                'title' => '🎉 Обновление сайта',
                'message' => 'Мы добавили новые функции! Теперь вы можете искать по интересам и фильтровать по городам.',
            ],
            [
                'title' => '📢 Важное объявление',
                'message' => 'Уважаемые пользователи! С 1 января меняются правила модерации. Подробнее в нашем блоге.',
            ],
            [
                'title' => '💝 С Днем Святого Валентина!',
                'message' => 'Желаем вам любви и счастья! Специальный промокод LOVE2025 на скидку 20% на премиум.',
            ],
            [
                'title' => '🌟 Новый дизайн сайта',
                'message' => 'Мы обновили дизайн LovePlanet. Наслаждайтесь новым интерфейсом и удобной навигацией!',
            ],
        ];

        foreach ($massTemplates as $template) {
            $status = $statuses[array_rand($statuses)];
            
            Broadcast::create([
                'user_id' => null,
                'type' => 'system',
                'title' => $template['title'],
                'message' => $template['message'],
                'status' => $status,
                'scheduled_at' => $status === 'scheduled' ? now()->addDays(rand(1, 5)) : null,
                'sent_at' => $status === 'sent' ? now()->subDays(rand(0, 7)) : null,
                'data' => [
                    'is_mass' => true,
                    'target_audience' => 'all_users',
                ],
                'created_at' => now()->subDays(rand(0, 20)),
            ]);

            $createdCount++;
            $bar->advance();
        }

        // ============================================
        // 3. ЗАПЛАНИРОВАННЫЕ РАССЫЛКИ В БУДУЩЕЕ
        // ============================================
        $this->command->newLine();
        $this->command->info('   ⏳ Создаем запланированные рассылки...');

        for ($i = 0; $i < 5; $i++) {
            $template = $templates[array_rand($templates)];
            $user = $users->random();
            
            Broadcast::create([
                'user_id' => rand(0, 1) ? $user->id : null,
                'type' => $template['type'],
                'title' => '📅 Запланировано: ' . $template['title'],
                'message' => $template['message'] . ' (отправка: ' . now()->addDays(rand(7, 30))->format('d.m.Y') . ')',
                'status' => 'scheduled',
                'scheduled_at' => now()->addDays(rand(7, 30)),
                'sent_at' => null,
                'data' => [
                    'is_scheduled' => true,
                    'priority' => rand(1, 5),
                ],
                'created_at' => now(),
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
            'total' => Broadcast::count(),
            'draft' => Broadcast::where('status', 'draft')->count(),
            'sent' => Broadcast::where('status', 'sent')->count(),
            'scheduled' => Broadcast::where('status', 'scheduled')->count(),
            'personal' => Broadcast::whereNotNull('user_id')->count(),
            'mass' => Broadcast::whereNull('user_id')->count(),
            'system' => Broadcast::where('type', 'system')->count(),
            'email' => Broadcast::where('type', 'email')->count(),
            'push' => Broadcast::where('type', 'push')->count(),
        ];

        $this->command->info('✅ Всего создано рассылок: ' . $stats['total']);
        $this->command->info('');
        $this->command->info('📊 Статистика:');
        $this->command->info("   ┌────────────────────────┬──────────┐");
        $this->command->info("   │ Тип                    │ Количество │");
        $this->command->info("   ├────────────────────────┼──────────┤");
        $this->command->info("   │ Всего                  │ {$stats['total']}        │");
        $this->command->info("   │ Черновики              │ {$stats['draft']}        │");
        $this->command->info("   │ Отправленные           │ {$stats['sent']}        │");
        $this->command->info("   │ Запланированные        │ {$stats['scheduled']}        │");
        $this->command->info("   ├────────────────────────┼──────────┤");
        $this->command->info("   │ Персональные           │ {$stats['personal']}        │");
        $this->command->info("   │ Массовые               │ {$stats['mass']}        │");
        $this->command->info("   ├────────────────────────┼──────────┤");
        $this->command->info("   │ System                 │ {$stats['system']}        │");
        $this->command->info("   │ Email                  │ {$stats['email']}        │");
        $this->command->info("   │ Push                   │ {$stats['push']}        │");
        $this->command->info("   └────────────────────────┴──────────┘");
    }
}