<?php

namespace App\Services;

use App\Models\GeoLocation;
use Illuminate\Support\Facades\Cache;

class GeoBlockService
{
    /**
     * Проверка, можно ли юзеру регистрироваться по его ISO коду страны.
     */
    public function isRegistrationAllowed(?string $countryCode): bool
    {
        if (!$countryCode) {
            return true; // Если не смогли определить гео — пускаем (решает антиспам)
        }

        $blockedCodes = Cache::remember('geo_blocked_iso_codes', now()->addHour(), function () {
            return GeoLocation::countries()
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
        return Cache::remember('geo_feed_blocked_ids', now()->addHour(), function () {
            return GeoLocation::feedBlocked()->pluck('id')->toArray();
        });
    }

    /**
     * Сброс кэша (вызывать в админке при изменении галочек)
     */
    public function clearCache(): void
    {
        Cache::forget('geo_blocked_iso_codes');
        Cache::forget('geo_feed_blocked_ids');
    }
}

// Архитектура Гео-блокировок (Geo-Blacklist)
// Зачем это нужно?
// В дейтинге 90% автоматизированного спама и скама идет из конкретных регионов (Нигерия, Индия, Филиппины). Банить по IP-адресам бесполезно — они меняются за секунды.
// Гео-блокировка — это иммунный щит на уровне базы данных. Она позволяет:

// Hard Block (Жестко запретить): Отсечь ботов на этапе регистрации. Если IP из Нигерии, юзер получает 403 ошибку и даже не может создать аккаунт.
// Soft Block (Скрыть из ленты): Скрыть анкеты из проблемных регионов в свайпах (теневой бан). Юзер думает, что он зарегистрировался и пользуется сайтом, но его никто не видит.
// 1. Состав системы (Файлы)
// Migration: geo_locations — единая таблица-дерево (Страны ➔ Регионы ➔ Города) с флагами is_registration_blocked и is_feed_blocked.
// Model: GeoLocation — содержит связи (parent/children) и скоупы для быстрой фильтрации.
// Service: GeoBlockService — ядро системы. Кэширует заблокированные ISO-коды и ID локаций, чтобы база не нагружалась на каждый запрос.
// Action: GeoLocationAction — отвечает за переключение тумблеров админом, сброс кэша и запись в AdminLog.
// Admin UI: livewire/admin/system/geo-locations — таблица с тумблерами для управления правилами в один клик.


// 3. Как интегрировать это в Web (Контроллеры)
// Вектор 1: Защита при Регистрации (Hard Block)
// В контроллере регистрации (или в Middleware) мы проверяем IP юзера через пакет stevebauman/location. Если страна в черном списке — отказываем в доступе.

// php

// use Stevebauman\Location\Facades\Location;
// use App\Services\GeoBlockService;

// public function register(Request $request)
// {
//     // 1. Получаем гео по IP
//     $location = Location::get($request->ip());
//     $countryCode = $location?->countryCode;

//     // 2. Проверяем через наш кэшируемый сервис
//     $geoService = app(GeoBlockService::class);

//     if (!$geoService->isRegistrationAllowed($countryCode)) {
//         abort(403, 'К сожалению, сервис недоступен в вашей стране.');
//     }

//     // ... логика создания юзера
// }
// Вектор 2: Скрытие из Ленты / Свайпов (Soft Block)
// В сервисе, который собирает анкеты для ленты (FeedService), мы исключаем юзеров, чьи country_id, region_id или city_id попали в черный список.

// php

// use App\Services\GeoBlockService;

// public function getFeed(User $user)
// {
//     $geoService = app(GeoBlockService::class);
    
//     // Получаем массив ID заблокированных локаций (из кэша!)
//     $blockedGeoIds = $geoService->getFeedBlockedIds();
    
//     return User::query()
//         ->where('id', '!=', $user->id)
//         // Исключаем юзеров, кто сидит в заблокированных регионах
//         ->when(!empty($blockedGeoIds), function ($q) use ($blockedGeoIds) {
//             $q->whereNotIn('country_id', $blockedGeoIds)
//               ->whereNotIn('region_id', $blockedGeoIds)
//               ->whereNotIn('city_id', $blockedGeoIds);
//         })
//         ->inRandomOrder()
//         ->limit(20)
//         ->get();
// }
// 4. Как управлять этим в Админке
// Идем в админку -> Система -> Гео-справочник.
// По умолчанию открыта вкладка "Страны" (чтобы не грузить тысячи городов).
// Вбиваем в поиск "Нигерия" или её ISO-код "NG".
// Включаем красный тумблер (Запретить регистрацию) или желтый (Скрыть из ленты).
// В этот момент срабатывает GeoLocationAction: он обновляет базу, сбрасывает кэш (GeoBlockService::clearCache) и пишет лог в AdminLog.
// Новое правило вступает в силу мгновенно для всех новых регистраций и запросов ленты.