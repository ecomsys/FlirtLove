<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserEvent;
use Illuminate\Database\Seeder;

class UserEventsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('📊 Генерируем ленту событий пользователей...');

        UserEvent::truncate();

        $users = User::where('role', 'user')->get();

        // Шаблоны событий: type, properties (убрали description, он будет на фронте через __())
        $templates = [
            [
                'type' => 'photo_updated',
                'properties' => ['photo_id' => 1]
            ],
            [
                'type' => 'status_changed',
                'properties' => ['text' => 'Ищу серьезные отношения']
            ],
            [
                'type' => 'vip_purchased',
                'properties' => ['days' => 30, 'price' => 500]
            ],
            [
                'type' => 'diary_created',
                'properties' => ['diary_id' => 1, 'title' => 'Мой первый день на сайте']
            ],
            [
                'type' => 'profile_completed',
                'properties' => []
            ],
            [
                'type' => 'premium_purchased',
                'properties' => ['days' => 7]
            ],
        ];

        $insertData = [];

        foreach ($users as $user) {
            // Генерим от 2 до 4 событий для каждого юзера
            $eventsCount = rand(2, 4);

            for ($i = 0; $i < $eventsCount; $i++) {
                $template = $templates[array_rand($templates)];
                $date = now()->subDays(rand(1, 60))->subHours(rand(1, 23));

                $insertData[] = [
                    'user_id' => $user->id,
                    'type' => $template['type'],
                    'properties' => json_encode($template['properties']), // Кодируем в JSON для Postgres
                    'created_at' => $date,
                    'updated_at' => $date,
                ];
            }
        }

        // Массовая вставка (в 10 раз быстрее чем create в цикле)
        UserEvent::insert($insertData);

        $this->command->info('✅ Лента событий сгенерирована (' . count($insertData) . ' записей).');
    }
}