<?php

namespace App\Services\Search;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UserSearchService
{
    public function search(?User $user, array $filters = []): Builder
    {
        $query = User::query()
            ->select('users.*')
            ->join('user_profiles', 'users.id', '=', 'user_profiles.user_id')
            ->join('user_preferences', 'users.id', '=', 'user_preferences.user_id')
            ->where('users.status', 'active')
            ->where('users.role', 'user')
            ->whereNotNull('user_profiles.gender')
            ->whereNotNull('user_profiles.birth_date')
            ->where('user_preferences.hide_from_search', false); // Скрывшихся не видят ни гости, ни юзеры

        if ($user) {
            $query->where('users.id', '!=', $user->id);

            $query->whereNotIn('users.id', function ($q) use ($user) {
                $q->select('target_user_id')->from('swipes')->where('user_id', $user->id);
            });

            $query->whereNotIn('users.id', function ($q) use ($user) {
                $q->select('blocked_id')->from('user_blocks')->where('blocker_id', $user->id);
            });

            $userGender = $user->profile->gender ?? 'male';
            $userAge = $user->profile->age ?? 18;

            $query->where(function ($q) use ($userGender, $userAge) {
                $q->where('user_preferences.visibility_gender', 'any')
                  ->orWhere('user_preferences.visibility_gender', $userGender);
            })
            ->where('user_preferences.visibility_age_min', '<=', $userAge)
            ->where('user_preferences.visibility_age_max', '>=', $userAge);
        }

        $this->applyBaseFilters($query, $filters);
        $this->applyLocationFilter($query, $filters);
        $this->applyAdvancedFilters($query, $filters);

        $query->orderByDesc('users.last_seen');

        // Возвращаем Builder, а контроллер сам сделает paginate()
        return $query->with(['profile.city', 'photos' => function($q) {
            $q->where('status', 'approved')->orderByDesc('is_primary')->limit(1);
        }])->withCount(['photos' => function($q) {
            $q->where('status', 'approved');
        }]);
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

            // Считаем дистанцию в км и добавляем в выборку, чтобы вывести в карточке
            $query->selectRaw(
                "ROUND(ST_DistanceSphere(user_profiles.location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geometry) / 1000, 1) as distance",
                [$lng, $lat]
            );
        } else {
            // Если координатов нет, возвращаем null чтобы фронтенд не упал
            $query->selectRaw('NULL as distance');
        }
    }

    private function applyBaseFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['gender']) && $filters['gender'] !== 'any') {
            $query->where('user_profiles.gender', $filters['gender']);
        }

        // ПРИВОДИМ К INT (для базы данных)
        $ageFrom = (int)($filters['age_from'] ?? 18);
        $ageTo = (int)($filters['age_to'] ?? 99);
        
        $maxDate = Carbon::now()->subYears($ageFrom)->format('Y-m-d');
        $minDate = Carbon::now()->subYears($ageTo)->format('Y-m-d');
        $query->whereBetween('user_profiles.birth_date', [$minDate, $maxDate]);

        if (!empty($filters['city_id'])) {
            $query->where('user_profiles.city_id', (int)$filters['city_id']);
        }

        if (!empty($filters['dating_goal']) && $filters['dating_goal'] !== 'any') {
            $query->where('user_profiles.dating_goal', $filters['dating_goal']);
        }

        if (!empty($filters['activity'])) {
            if ($filters['activity'] === 'online') {
                $query->where('users.last_seen', '>=', now()->subMinutes(5));
            } elseif ($filters['activity'] === 'recently') {
                $query->where('users.last_seen', '>=', now()->subDays(1));
            }
        }

        if (isset($filters['is_new']) && filter_var($filters['is_new'], FILTER_VALIDATE_BOOLEAN)) {
            $query->where('users.created_at', '>=', now()->subDays(7));
        }

        if (isset($filters['verified_only']) && filter_var($filters['verified_only'], FILTER_VALIDATE_BOOLEAN)) {
            $query->where('users.is_verified', true);
        }
    }

    private function applyAdvancedFilters(Builder $query, array $filters): void
    {
        // Слайдеры (Рост и Вес) - приводим к INT
        if (isset($filters['height_from']) && isset($filters['height_to'])) {
            $query->whereBetween('user_profiles.height', [(int)$filters['height_from'], (int)$filters['height_to']]);
        }
        if (isset($filters['weight_from']) && isset($filters['weight_to'])) {
            $query->whereBetween('user_profiles.weight', [(int)$filters['weight_from'], (int)$filters['weight_to']]);
        }

        // Обычные поля (whereIn для массивов из чекбоксов)
        $arrayFilters = ['body_type', 'eye_color', 'hair_color', 'relationship_status', 'children_status', 'pets', 'housing', 'has_car', 'education_level', 'income', 'smoking', 'alcohol', 'zodiac_sign'];
        foreach ($arrayFilters as $field) {
            if (!empty($filters[$field]) && is_array($filters[$field])) {
                // ЖЁСТКО ПРИВОДИМ ЗНАЧЕНИЯ К ЧИСЛАМ ДЛЯ POSTGRESQL
                $intValues = array_map('intval', $filters[$field]);
                $query->whereIn("user_profiles.{$field}", $intValues);
            }
        }

        // JSON массивы (где multiple choice) - тоже приводим к INT
        $jsonFilters = ['interests', 'languages', 'sports', 'body_decorations'];
        foreach ($jsonFilters as $field) {
            if (!empty($filters[$field]) && is_array($filters[$field])) {
                $intValues = array_map('intval', $filters[$field]);
                $query->whereJsonContains("user_profiles.{$field}", $intValues);
            }
        }
    }
}