<?php

namespace App\Services;

use App\Models\GeoIPLocation;
use Illuminate\Support\Facades\Cache;

class GeoIPBlockService
{
    /**
     * Проверка, можно ли юзеру регистрироваться по его ISO коду страны.
     */
    public function isRegistrationAllowed(?string $countryCode): bool
    {
        if (!$countryCode) {
            return true; // Если не смогли определить гео — пускаем (решает антиспам)
        }

        $blockedCodes = Cache::remember('geoip_blocked_iso_codes', now()->addHour(), function () {
            return GeoIPLocation::countries()
                ->registrationBlocked()
                ->pluck('iso_code')
                ->toArray();
        });

        // Если код страны есть в черном списке — регистрация запрещена
        return !in_array(strtoupper($countryCode), $blockedCodes);
    }

    /**
     * Получить массив ID заблокированных для ленты локаций.
     * (Используется в FeedService, чтобы скрыть анкеты)
     */
    public function getFeedBlockedIds(): array
    {
        return Cache::remember('geoip_feed_blocked_ids', now()->addHour(), function () {
            return GeoIPLocation::feedBlocked()->pluck('id')->toArray();
        });
    }

    /**
     * Сброс кэша (вызывать в админке при изменении галочек)
     */
    public function clearCache(): void
    {
        Cache::forget('geoip_blocked_iso_codes');
        Cache::forget('geoip_feed_blocked_ids');
    }
}

// Финальное резюме связей (Deep Analysis):
// Админка (UI) ➔ Клик по тумблеру вызывает метод toggleRegistration в Volt.
// Volt Component ➔ Достает модель и передает в GeoIPLocationAction.
// GeoIPLocationAction ➔ Обновляет флаг в БД, формирует красивый diff с родителем, пишет в AdminLog и дергает GeoIPBlockService::clearCache().
// GeoIPBlockService ➔ Сбрасывает кэш Redis/Database.
// Контроллер Регистрации (Web) ➔ При следующем запросе юзера GeoIPBlockService::isRegistrationAllowed видит пустой кэш, идет в БД, берет свежие заблокированные ISO-коды, кладет в кэш на час и возвращает результат.
// FeedService (Web) ➔ GeoIPBlockService::getFeedBlockedIds отдает массив ID, и User::whereNotIn('region_id', ...) скрывает скамеров из ленты.
