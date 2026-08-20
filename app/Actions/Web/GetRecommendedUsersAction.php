<?php

namespace App\Actions\Web;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class GetRecommendedUsersAction
{
    /**
     * Получить список анкет для свайпинга.
     *
     * @param User $user Текущий юзер
     * @param int $limit Количество анкет за один запрос
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function execute(User $user, int $limit = 20)
    {
        // Если у юзера нет координат, мы не можем искать по радиусу
        if (!$user->profile || !$user->profile->location) {
            return collect();
        }

        $preferences = $user->preferences;
        $profile = $user->profile;
        
        // Берем радиус поиска из настроек (дефолт 50 км)
        $radius = $preferences->preferred_distance_km ?? 50;

        // Строим базовый запрос через JOIN (самый быстрый способ для фильтрации)
        $query = User::query()
            ->select('users.*', 'user_profiles.*') // Берем данные юзера и профиля
            ->join('user_profiles', 'users.id', '=', 'user_profiles.user_id')
            
            // === 1. БАЗОВЫЕ ФИЛЬТРЫ (По таблице users) ===
            ->where('users.is_banned', false)
            ->where('users.is_admin', false)
            ->where('users.is_deactivated', false)
            ->where('users.has_completed_onboarding', true)
            ->where('users.id', '!=', $user->id) // Не показывать себя

            // === 2. ИСКЛЮЧАЕМ ТЕХ, КОГО УЖЕ СВАЙПАЛИ (Subquery - не ест память!) ===
            ->whereNotIn('users.id', function ($q) use ($user) {
                $q->select('target_user_id')
                  ->from('swipes')
                  ->where('user_id', $user->id);
            })

            // === 3. ФИЛЬТРЫ ПОИСКА (По таблице user_profiles) ===
            // Возраст: birth_date должна быть МЕНЬШЕ (юзер старше) чем min возраст
            ->when($preferences->preferred_age_min, function ($q, $minAge) {
                $q->where('user_profiles.birth_date', '<=', now()->subYears($minAge)->format('Y-m-d'));
            })
            // Возраст: birth_date должна быть БОЛЬШЕ (юзер младше) чем max возраст
            ->when($preferences->preferred_age_max, function ($q, $maxAge) {
                $q->where('user_profiles.birth_date', '>=', now()->subYears($maxAge + 1)->format('Y-m-d'));
            })
            // Пол
            ->when($preferences->preferred_gender && $preferences->preferred_gender !== 'any', function ($q, $gender) {
                $q->where('user_profiles.gender', $gender);
            })

            // === 4. ГЕОЛОКАЦИЯ (PostGIS) ===
            // Ищем анкеты рядом. Используем ST_DWithin
            ->whereRaw(
                "ST_DWithin(user_profiles.location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)",
                [$profile->longitude, $profile->latitude, $radius * 1000]
            );

        // === 5. РАСШИРЕННЫЙ ПОИСК (JSON search_filters из Preferences) ===
        $extendedFilters = $preferences->search_filters;
        
        if (!empty($extendedFilters)) {
            // Проходим по каждому фильтру из настроек юзера
            foreach ($extendedFilters as $column => $value) {
                if ($value === null || $value === 0) continue; // Пропускаем пустые

                // Фильтры для числовых колонок (body_type, smoking, etc.)
                if (in_array($column, ['body_type', 'eye_color', 'hair_color', 'relationship_status', 'children_status', 'smoking', 'alcohol', 'education_level', 'has_car', 'pets', 'housing'])) {
                    $query->where("user_profiles.{$column}", $value);
                }
                
                // Фильтры для диапазона (Рост, Вес)
                if ($column === 'height_from') $query->where('user_profiles.height', '>=', $value);
                if ($column === 'height_to') $query->where('user_profiles.height', '<=', $value);
                
                // Фильтры для JSON-массивов (Языки, Спорт)
                if (in_array($column, ['languages', 'sports'])) {
                    $query->whereJsonContains("user_profiles.{$column}", $value);
                }
                
                // Фильтры по статусу верификации
                if ($column === 'is_verified_only' && $value) $query->where('users.is_verified', true);
                if ($column === 'is_premium_only' && $value) $query->where('users.is_premium', true);
            }
        }

        // === 6. СОРТИРКА И ЛИМИТ ===
        // Показываем сначала тех, кто онлайн, потом по дате последнего визита
        $query->orderByDesc('users.last_seen')
              ->limit($limit);

        // Жадно загружаем только одобренные главные фото для карусели
        return $query->with(['photos' => function ($q) {
            $q->where('status', 'approved')->orderByDesc('is_primary');
        }])->get();
    }
}