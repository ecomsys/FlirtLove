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

    public function execute(User $user, float $lat, float $lng, ?string $existingAddress = null): array
    {
        $geoData = $this->geocodingService->reverseGeocode($lat, $lng);

        $address = $geoData['display_name'] ?? $existingAddress;
        $city = $geoData['city'] ?? null;
        $country = $geoData['country'] ?? null;

        if (!$address) {
            $address = $user->profile?->address;
        }

        // ФИКС: Сохраняем старые данные до обновления
        $before = $user->profile ? $user->profile->only(['address', 'city', 'country']) : null;

        DB::transaction(function () use ($user, $lat, $lng, $address, $city, $country, $before) {
            $locationData = [
                'address' => $address,
                'city' => $city,
                'country' => $country,
                'location' => DB::raw("ST_SetSRID(ST_MakePoint({$lng}, {$lat}), 4326)"),
            ];

            if ($user->profile) {
                $user->profile->update($locationData);
            } else {
                $user->profile()->create($locationData);
            }

            // ФИКС: Формируем лог с диффами и контекстом
            $after = [
                'address' => $address,
                'city' => $city,
                'country' => $country,
                'context' => [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'admin_id' => auth()->id(),
                    'lat' => $lat,
                    'lng' => $lng
                ]
            ];

            AdminLog::record('user.location_update', $user, auth()->user(), $before, $after, participants: [$user->id]);

            Log::info('Локация пользователя обновлена', [
                'user_id' => $user->id, 'lat' => $lat, 'lng' => $lng, 'admin_id' => auth()->id(),
            ]);
        });

        return [
            'success' => true,
            'address' => $address,
            'message' => 'Координаты и адрес обновлены',
        ];
    }
}