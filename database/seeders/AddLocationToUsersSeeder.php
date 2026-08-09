<?php 

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddLocationToUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('user_profiles')) {
            $this->command->error('❌ Таблица user_profiles не существует! Запусти миграции сначала.');
            return;
        }

        $cities = [
            'Москва' => ['lat' => 55.7558, 'lng' => 37.6173],
            'Санкт-Петербург' => ['lat' => 59.9343, 'lng' => 30.3351],
            'Казань' => ['lat' => 55.7887, 'lng' => 49.1221],
            'Новосибирск' => ['lat' => 55.0084, 'lng' => 82.9357],
            'Екатеринбург' => ['lat' => 56.8389, 'lng' => 60.6057],
            'Сочи' => ['lat' => 43.6028, 'lng' => 39.7342],
            'Краснодар' => ['lat' => 45.0355, 'lng' => 38.9753],
            'Владивосток' => ['lat' => 43.1155, 'lng' => 131.8855],
            'Калининград' => ['lat' => 54.7104, 'lng' => 20.4522],
            'Ростов-на-Дону' => ['lat' => 47.2357, 'lng' => 39.7015],
            'Самара' => ['lat' => 53.1959, 'lng' => 50.1008],
            'Уфа' => ['lat' => 54.7388, 'lng' => 55.9721],
            'Красноярск' => ['lat' => 56.0106, 'lng' => 92.8526],
            'Пермь' => ['lat' => 58.0104, 'lng' => 56.2294],
            'Воронеж' => ['lat' => 51.6608, 'lng' => 39.2003],
        ];

        // Берем только обычных юзеров (role = 'user')
        $users = DB::table('users')->where('role', 'user')->get();

        if ($users->isEmpty()) {
            $this->command->warn('⚠️ Нет пользователей для обновления координат.');
            return;
        }

        $this->command->info("📍 Начинаем обновление координат для {$users->count()} пользователей...");

        $updated = 0;
        foreach ($users as $user) {
            $cityName = array_rand($cities);
            $center = $cities[$cityName];

            // Небольшой разброс координат в пределах города
            $latOffset = (mt_rand(-150, 150) / 1000) * 0.8;
            $lngOffset = (mt_rand(-150, 150) / 1000) * 0.8;

            $lat = $center['lat'] + $latOffset;
            $lng = $center['lng'] + $lngOffset;

            // ВАЖНО: Добавлено ::geography, так как колонка имеет тип geography!
            // Профиль уже 100% существует (создается событием booted в модели User)
            DB::table('user_profiles')
                ->where('user_id', $user->id)
                ->update([
                    'city' => $cityName,
                    'country' => 'Россия',
                    'location' => DB::raw("ST_SetSRID(ST_MakePoint({$lng}, {$lat}), 4326)::geography"),
                    'updated_at' => now(),
                ]);

            $updated++;
            $this->command->line("   ✓ Пользователь ID {$user->id} → {$cityName} ({$lat}, {$lng})");
        }

        $this->command->info("✅ Координаты добавлены для {$updated} пользователей!");
    }
}