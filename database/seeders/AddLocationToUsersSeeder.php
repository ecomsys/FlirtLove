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

        // Названия городов пишем на английском, так как пакет world хранит их так
        $cities = [
            'Moscow' => ['lat' => 55.7558, 'lng' => 37.6173],
            'Saint Petersburg' => ['lat' => 59.9343, 'lng' => 30.3351],
            'Kazan' => ['lat' => 55.7887, 'lng' => 49.1221],
            'Novosibirsk' => ['lat' => 55.0084, 'lng' => 82.9357],
            'Yekaterinburg' => ['lat' => 56.8389, 'lng' => 60.6057],
            'Sochi' => ['lat' => 43.6028, 'lng' => 39.7342],
            'Krasnodar' => ['lat' => 45.0355, 'lng' => 38.9753],
            'Vladivostok' => ['lat' => 43.1155, 'lng' => 131.8855],
            'Kaliningrad' => ['lat' => 54.7104, 'lng' => 20.4522],
            'Rostov-on-Don' => ['lat' => 47.2357, 'lng' => 39.7015],
            'Samara' => ['lat' => 53.1959, 'lng' => 50.1008],
            'Ufa' => ['lat' => 54.7388, 'lng' => 55.9721],
            'Krasnoyarsk' => ['lat' => 56.0106, 'lng' => 92.8526],
            'Perm' => ['lat' => 58.0104, 'lng' => 56.2294],
            'Voronezh' => ['lat' => 51.6608, 'lng' => 39.2003],
        ];

        // Достаем ID России и собираем ID нужных городов из БД
        $russia = DB::table('countries')->where('iso2', 'RU')->first();
        $cityIds = DB::table('cities')
            ->where('country_id', $russia->id ?? 0)
            ->whereIn('name', array_keys($cities))
            ->pluck('id', 'name'); // Вернет коллекцию: ['Moscow' => 123, 'Kazan' => 456, ...]

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

            // Находим ID города, если он есть в базе
            $cityId = $cityIds[$cityName] ?? null;

            // ВАЖНО: Добавлено ::geography, так как колонка имеет тип geography!
            DB::table('user_profiles')
                ->where('user_id', $user->id)
                ->update([
                    'city_id' => $cityId, // Записываем ID вместо строки
                    'country_id' => $russia->id ?? null, // Записываем ID страны
                    'location' => DB::raw("ST_SetSRID(ST_MakePoint({$lng}, {$lat}), 4326)::geography"),
                    'updated_at' => now(),
                ]);

            $updated++;
            $this->command->line("   ✓ Пользователь ID {$user->id} → {$cityName} (ID: {$cityId}) ({$lat}, {$lng})");
        }

        $this->command->info("✅ Координаты добавлены для {$updated} пользователей!");
    }
}