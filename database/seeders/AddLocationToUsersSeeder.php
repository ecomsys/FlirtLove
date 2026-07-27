<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AddLocationToUsersSeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            'Москва' => ['lat' => 55.7558, 'lng' => 37.6173],
            'Санкт-Петербург' => ['lat' => 59.9343, 'lng' => 30.3351],
            'Калуга' => ['lat' => 54.5138, 'lng' => 36.2612],
        ];

        // ✅ Используем DB, чтобы не загружать модели с кастами
        $users = DB::table('users')->where('is_admin', false)->get();

        foreach ($users as $user) {
            $cityName = array_rand($cities);
            $center = $cities[$cityName];

            $latOffset = (mt_rand(-100, 100) / 1000) * 1.5;
            $lngOffset = (mt_rand(-100, 100) / 1000) * 1.5;

            $lat = $center['lat'] + $latOffset;
            $lng = $center['lng'] + $lngOffset;

            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'city' => $cityName,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'location' => DB::raw("ST_SetSRID(ST_MakePoint({$lng}, {$lat}), 4326)"),
                ]);

            $this->command->info("Пользователь {$user->name} → {$cityName} ({$lat}, {$lng})");
        }

        $this->command->info('✅ Координаты добавлены всем пользователям!');
    }
}