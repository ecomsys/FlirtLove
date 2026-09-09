<?php

namespace App\Services;

use App\Models\GeoIPLocation;
use Illuminate\Support\Facades\Cache;


// Как это использовать в ленте (FeedService):
//  $blockedIds = app(GeoIPBlockService::class)->getFeedBlockedIds();

// // Если $blockedIds пустой, whereNotIn отработает корректно (никого не скроет)
//  $users = User::whereNotIn('region_id', $blockedIds)->...;


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

        return !in_array(strtoupper($countryCode), $blockedCodes);
    }

    /**
     * Получить массив ID заблокированных для ленты локаций.
     * (Используется в FeedService, чтобы скрыть анкеты)
     */
    public function getFeedBlockedIds(): array
    {
        return Cache::remember('geoip_feed_blocked_ids', now()->addHour(), function () {
            // 1. Берем все узлы, которые прямо заблокировал админ
            $blockedRoots = GeoIPLocation::feedBlocked()->get(['id']);
            
            if ($blockedRoots->isEmpty()) {
                return [];
            }

            $blockedIds = $blockedRoots->pluck('id')->toArray();
            $rootIds = $blockedRoots->pluck('id')->toArray();

            // 2. Находим все регионы (2-й уровень), которые входят в заблокированные страны
            $level2Ids = GeoIPLocation::whereIn('parent_id', $rootIds)->pluck('id')->toArray();
            
            if (!empty($level2Ids)) {
                $blockedIds = array_merge($blockedIds, $level2Ids);
                
                // 3. Находим все города (3-й уровень), которые входят в эти регионы
                $level3Ids = GeoIPLocation::whereIn('parent_id', $level2Ids)->pluck('id')->toArray();
                $blockedIds = array_merge($blockedIds, $level3Ids);
            }

            // Возвращаем плоский уникальный массив ID
            return array_unique($blockedIds);
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
