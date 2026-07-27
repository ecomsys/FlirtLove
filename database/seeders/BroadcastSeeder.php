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
        ];

        Broadcast::truncate();
        $this->command->info('🗑️ Старые рассылки удалены');

        $this->command->info('📨 Создаем рассылки для пользователей...');

        foreach ($templates as $template) {
            $count = rand(1, 3);
            
            for ($i = 0; $i < $count; $i++) {
                $status = $statuses[array_rand($statuses)];
                $user = $users->random();
                
                Broadcast::create([
                    'user_id' => rand(0, 1) ? $user->id : null,
                    'type' => $template['type'],
                    'title' => $template['title'],
                    'message' => $template['message'],
                    'status' => $status,
                    'scheduled_at' => $status === 'scheduled' ? now()->addDays(rand(1, 5)) : null,
                    'sent_at' => $status === 'sent' ? now()->subDays(rand(0, 10)) : null,
                    'created_at' => now()->subDays(rand(0, 30)),
                ]);
            }
        }

        // ✅ Убрали создание "Админских уведомлений"

        for ($i = 0; $i < 3; $i++) {
            $status = $statuses[array_rand($statuses)];
            Broadcast::create([
                'user_id' => null,
                'type' => 'system',
                'title' => 'Массовое уведомление #' . ($i + 1),
                'message' => 'Важное объявление для всех пользователей сайта!',
                'status' => $status,
                'scheduled_at' => $status === 'scheduled' ? now()->addDays(rand(1, 5)) : null,
                'sent_at' => $status === 'sent' ? now()->subDays(rand(0, 7)) : null,
                'created_at' => now()->subDays(rand(0, 20)),
            ]);
        }

        $this->command->info('✅ Создано рассылок: ' . Broadcast::count());
        $this->command->info("   📝 Черновиков: " . Broadcast::where('status', 'draft')->count());
        $this->command->info("   📤 Отправленных: " . Broadcast::where('status', 'sent')->count());
        $this->command->info("   ⏳ Запланированных: " . Broadcast::where('status', 'scheduled')->count());
    }
}