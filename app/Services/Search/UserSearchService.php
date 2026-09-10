<?php

namespace App\Services\Search;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class UserSearchService
{
    /**
     * Основной метод поиска анкет (Поддерживает гостей!)
     *
     * @param User|null $user Юзер, который ищет (или null, если гость)
     * @param array $filters Фильтры (пол, возраст, гео, теги и т.д.)
     * @return LengthAwarePaginator
     */
    public function search(?User $user, array $filters = []): LengthAwarePaginator
    {
        // 1. Начинаем сборку запроса. Делаем JOIN, чтобы фильтровать по таблице профилей сразу
        $query = User::query()
            ->select('users.*') // Берем только данные таблицы users
            ->join('user_profiles', 'users.id', '=', 'user_profiles.user_id')
            ->where('users.status', 'active') // Только активные
            ->where('users.role', 'user') // Исключаем админов из поиска
            ->whereNotNull('user_profiles.gender') // Исключаем пустые анкеты (онбординг не пройден)
            ->whereNotNull('user_profiles.birth_date');

        // 2. ЛОГИКА ДЛЯ АВТОРИЗОВАННОГО ЮЗЕРА
        if ($user) {
            $query->where('users.id', '!=', $user->id);

            // ФИКС: Subquery вместо pluck()->toArray(). Это спасет память на миллионнике!
            // База сама отбросит тех, кого мы свайпнули
            $query->whereNotIn('users.id', function ($q) use ($user) {
                $q->select('target_user_id')->from('swipes')->where('user_id', $user->id);
            });

            // И тех, кого мы заблокировали
            $query->whereNotIn('users.id', function ($q) use ($user) {
                $q->select('blocked_id')->from('user_blocks')->where('blocker_id', $user->id);
            });

            // Исключаем тех, кто скрылся из поиска (hide_from_search)
            $query->join('user_preferences', 'users.id', '=', 'user_preferences.user_id')
                  ->where('user_preferences.hide_from_search', false);

            // ФИЛЬТР "КТО ВИДИТ МОЮ АНКЕТУ" (Premium-фича)
            $userGender = $user->profile->gender ?? 'male';
            $userAge = $user->profile->age ?? 18;

            $query->where(function ($q) use ($userGender, $userAge) {
                $q->where('user_preferences.visibility_gender', 'any')
                  ->orWhere('user_preferences.visibility_gender', $userGender);
            })
            ->where('user_preferences.visibility_age_min', '<=', $userAge)
            ->where('user_preferences.visibility_age_max', '>=', $userAge);
        }

        // 3. БАЗОВЫЕ ФИЛЬТРЫ (Пол, Возраст, Город)
        $this->applyBaseFilters($query, $filters);

        // 4. ГЕОЛОКАЦИЯ (Использует spatialIndex)
        $this->applyLocationFilter($query, $filters);

        // 5. РАСШИРЕННЫЕ ФИЛЬТРЫ
        $this->applyAdvancedFilters($query, $filters);

        // 6. СОРТИРОВКА
        $query->orderByDesc('users.last_seen'); // По умолчанию: кто был онлайн недавно

        // Жадная загрузка связей, чтобы не было проблемы N+1
        return $query->with(['profile.city', 'photos' => function($q) {
            $q->where('status', 'approved')->orderByDesc('is_primary')->limit(4);
        }])->paginate(20);
    }

    private function applyBaseFilters(Builder $query, array $filters): void
    {
        // Пол
        if (!empty($filters['gender']) && $filters['gender'] !== 'any') {
            $query->where('user_profiles.gender', $filters['gender']);
        }

        // ФИКС: Возраст (Конвертируем в дату рождения, чтобы использовать индекс birth_date!)
        $ageFrom = $filters['age_from'] ?? 18;
        $ageTo = $filters['age_to'] ?? 99;
        
        $maxDate = Carbon::now()->subYears($ageFrom)->format('Y-m-d'); // Самая поздняя дата рождения (самые молодые)
        $minDate = Carbon::now()->subYears($ageTo)->format('Y-m-d'); // Самая ранняя дата рождения (самые старые)
        
        $query->whereBetween('user_profiles.birth_date', [$minDate, $maxDate]);

        // Город
        if (!empty($filters['city_id'])) {
            $query->where('user_profiles.city_id', $filters['city_id']);
        }

        // Цель знакомства
        if (!empty($filters['dating_goal'])) {
            $query->where('user_profiles.dating_goal', $filters['dating_goal']);
        }

        // Только онлайн
        if (!empty($filters['online_only']) && $filters['online_only'] === true) {
            $query->where('users.last_seen', '>=', now()->subMinutes(5));
        }

        // Только верифицированные
        if (!empty($filters['verified_only']) && $filters['verified_only'] === true) {
            $query->where('users.is_verified', true);
        }

        // ФИКС: Только с премиумом (используем дату, а не несуществующий флаг is_premium)
        if (!empty($filters['premium_only']) && $filters['premium_only'] === true) {
            $query->where('users.premium_expires_at', '>', now());
        }
    }

    private function applyLocationFilter(Builder $query, array $filters): void
    {
        if (!empty($filters['lat']) && !empty($filters['lng']) && !empty($filters['radius_km'])) {
            $lat = (float) $filters['lat'];
            $lng = (float) $filters['lng'];
            $radiusMeters = (int) $filters['radius_km'] * 1000;

            $query->whereNotNull('user_profiles.location')
                  ->whereRaw(
                      "ST_DWithin(user_profiles.location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)",
                      [$lng, $lat, $radiusMeters]
                  );
        }
    }

    private function applyAdvancedFilters(Builder $query, array $filters): void
    {
        $simpleFilters = ['body_type', 'smoking', 'alcohol', 'relationship_status', 'children_status', 'housing', 'has_car'];

        foreach ($simpleFilters as $field) {
            if (isset($filters[$field]) && $filters[$field] !== null && $filters[$field] !== 'any') {
                $query->where("user_profiles.{$field}", $filters[$field]);
            }
        }

        if (!empty($filters['height_from'])) {
            $query->where('user_profiles.height', '>=', $filters['height_from']);
        }
        if (!empty($filters['height_to'])) {
            $query->where('user_profiles.height', '<=', $filters['height_to']);
        }

        $jsonFilters = ['interests', 'languages', 'sports'];
        foreach ($jsonFilters as $field) {
            if (!empty($filters[$field]) && is_array($filters[$field])) {
                $query->whereJsonContains("user_profiles.{$field}", $filters[$field]);
            }
        }
    }
}