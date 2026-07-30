<?php

namespace App\Actions\Admin;

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

        DB::transaction(function () use ($user, $lat, $lng, $address, $city, $country) {
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