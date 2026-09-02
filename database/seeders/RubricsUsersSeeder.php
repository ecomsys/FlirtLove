<?php

namespace Database\Seeders;

use App\Models\Rubric;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

// php artisan db:seed --class=Database\Seeders\RubricsUsersSeeder

class RubricsUsersSeeder extends Seeder
{
    /**
     * Заполнение базы личными рубриками юзеров.
     */
    public function run(): void
    {
        // Берем 30 случайных активных юзеров (исключая админов)
        $users = User::where('role', 'user')
            ->inRandomOrder()
            ->take(30)
            ->get();

        if ($users->isEmpty()) {
            $this->command->info('Нет юзеров для создания рубрик. Сначала запустите UserSeeder.');
            return;
        }

        $rubricNames = [
            'Мысли', 'Стихи', 'Путешествия', 'Работа', 'Личное', 
            'Хобби', 'Философия', 'Сны', 'Музыка', 'Новости дня'
        ];

        $createdCount = 0;

        foreach ($users as $user) {
            // Выбираем случайное количество рубрик (от 1 до 3) для каждого юзера
            $namesForUser = array_rand(array_flip($rubricNames), rand(1, 3));
            $namesForUser = (array) $namesForUser; // На случай, если выпадет 1 рубрика
            
            $sortOrder = 0;

            foreach ($namesForUser as $name) {
                // Добавляем ID юзера и случайную строку к слагу для 100% уникальности
                $slug = Str::slug($name) . '-' . $user->id . '-' . Str::random(4);

                Rubric::create([
                    'user_id' => $user->id,
                    'name' => $name,
                    'slug' => $slug,
                    'description' => 'Личная рубрика пользователя ' . $user->name,
                    'is_active' => true,
                    'sort_order' => $sortOrder++,
                ]);

                $createdCount++;
            }
        }

        $this->command->info("Успешно создано {$createdCount} личных рубрик для {$users->count()} пользователей.");
    }
}