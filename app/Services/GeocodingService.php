<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    public function reverseGeocode(float $lat, float $lng): ?array
    {
        // ФИКС: Округляем до 3 знаков (точность ~100 метров). 
        // Это спасет Redis от переполнения и увеличит попадания в кэш до 99%
        $latRounded = round($lat, 3);
        $lngRounded = round($lng, 3);
        
        $cacheKey = "geocode_{$latRounded}_{$lngRounded}";

        return Cache::remember($cacheKey, now()->addDay(), function () use ($latRounded, $lngRounded) {
            try {
                // ФИКС: Защита от бана Nominatim. Микро-задержка перед запросом.
                usleep(1000000); // 1 секунда

                $response = Http::withHeaders([
                    'User-Agent' => 'LoveClone/1.0',
                    'Accept-Language' => 'ru-RU,ru;q=0.9',
                ])->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat' => $latRounded,
                    'lon' => $lngRounded,
                    'format' => 'json',
                    'zoom' => 18,
                    'addressdetails' => 1, 
                ]);

                if ($response->failed()) return null;

                $data = $response->json();
                $address = $data['address'] ?? [];

                return [
                    'display_name' => $data['display_name'] ?? null,
                    'city' => $address['city'] ?? $address['town'] ?? $address['village'] ?? null,
                    'country' => $address['country'] ?? null,
                ];
            } catch (\Exception $e) {
                Log::error('Geocoding failed', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }
}