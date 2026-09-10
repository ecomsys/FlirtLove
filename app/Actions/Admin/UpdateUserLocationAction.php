<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\User;
use App\Services\GeocodingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateUserLocationAction
{
    public function __construct(
        private GeocodingService $geocodingService
    ) {}

    // ФИКС: Добавили User $admin параметром, чтобы не зависеть от сессии (auth())
    public function execute(User $user, float $lat, float $lng, User $admin, ?string $existingAddress = null): array
    {
        $geoData = $this->geocodingService->reverseGeocode($lat, $lng);

        $address = $geoData['display_name'] ?? $existingAddress;
        $city = $geoData['city'] ?? null;
        $country = $geoData['country'] ?? null;

        if (!$address) {
            $address = $user->profile?->address;
        }

        $before = $user->profile ? $user->profile->only(['address', 'city', 'country']) : null;

        DB::transaction(function () use ($user, $lat, $lng, $address, $city, $country, $before, $admin) {
            $locationData = [
                'address' => $address,
                'city' => $city,
                'country' => $country,
                // ФИКС: Защита от SQL-инъекций через координаты (приводим к float)
                'location' => DB::raw("ST_SetSRID(ST_MakePoint(" . (float) $lng . ", " . (float) $lat . "), 4326)"),
            ];

            if ($user->profile) {
                $user->profile->update($locationData);
            } else {
                $user->profile()->create($locationData);
            }

            $after = [
                'address' => $address,
                'city' => $city,
                'country' => $country,
                'context' => [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'admin_id' => $admin->id, // ФИКС: Берем ID из переданного объекта
                    'lat' => $lat,
                    'lng' => $lng
                ]
            ];

            AdminLog::record('user.location_update', $user, $admin, $before, $after, participants: [$user->id]);

            Log::info('Локация пользователя обновлена', [
                'user_id' => $user->id, 'lat' => $lat, 'lng' => $lng, 'admin_id' => $admin->id,
            ]);
        });

        return [
            'success' => true,
            'address' => $address,
            'message' => 'Координаты и адрес обновлены',
        ];
    }
}