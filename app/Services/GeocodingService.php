<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    public function reverseGeocode(float $lat, float $lng): ?array
    {
        $cacheKey = "geocode_{$lat}_{$lng}";

        return Cache::remember($cacheKey, now()->addDay(), function () use ($lat, $lng) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'LoveClone/1.0',
                    'Accept-Language' => 'ru-RU,ru;q=0.9',
                ])->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat' => $lat,
                    'lon' => $lng,
                    'format' => 'json',
                    'zoom' => 18,
                    'addressdetails' => 1, // Важно для получения частей адреса
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