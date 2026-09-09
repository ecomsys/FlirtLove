<?php

namespace App\Services\Search;

use App\Models\User;
use App\Models\UserProfile;
use App\Models\Swipe;
use App\Models\UserBlock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

// Как это использовать в Контроллере?
// В твоем контроллере поиска (например, SearchController) логика станет максимально тонкой:

// public function index(Request $request, UserSearchService $searchService)
// {
//     $filters = $request->validate([
//         'gender' => 'nullable|in:male,female,any',
//         'age_from' => 'nullable|integer|min:18',
//         'age_to' => 'nullable|integer|max:99',
//         'city_id' => 'nullable|integer|exists:cities,id',
//         'lat' => 'nullable|numeric',
//         'lng' => 'nullable|numeric',
//         'radius_km' => 'nullable|integer|min:1|max:500',
//         'interests' => 'nullable|array',
//         'interests.*' => 'string', // ['music', 'sport']
//         // ... другие фильтры
//     ]);

//     $users = $searchService->search(auth()->user(), $filters);

//     return response()->json($users);
// }

class UserSearchService
{
    /**
     * Основной метод поиска анкет
     *
     * @param User $user Юзер, который ищет
     * @param array $filters Фильтры (пол, возраст, гео, теги и т.д.)
     * @return LengthAwarePaginator
     */
    public function search(User $user, array $filters = []): LengthAwarePaginator
    {
        // 1. Собираем ID юзеров, которых мы не хотим видеть в выдаче
        // (Те, кого мы свайпнули, и те, кого мы заблокировали)
        $excludedIds = $this->getExcludedUserIds($user);

        // 2. Начинаем сборку запроса. Делаем JOIN, чтобы фильтровать по таблице профилей сразу
        $query = User::query()
            ->select('users.*') // Берем только данные таблицы users, чтобы не было конфликтов колонок
            ->join('user_profiles', 'users.id', '=', 'user_profiles.user_id')
            ->join('user_preferences', 'users.id', '=', 'user_preferences.user_id')
            ->where('users.id', '!=', $user->id) // Исключаем себя
            ->whereNotIn('users.id', $excludedIds) // Исключаем свайпнутых и заблокированных
            ->where('users.status', 'active') // Только активные
            ->where('users.role', 'user') // Исключаем админов и модераторов из поиска
            ->where('user_preferences.hide_from_search', false); // Исключаем тех, кто скрылся
        
        // ФИЛЬТР "КТО ВИДИТ МОЮ АНКЕТУ" (Premium-фича)
        // Исключаем тех, кто скрыл свою анкету от текущего юзера (по полу и возрасту)
        $userGender = $user->profile->gender ?? 'male';
        $userAge = $user->profile->age ?? 18;

        $query->where(function ($q) use ($userGender, $userAge) {
            // Условие 1: Анкета видна всем (any) ИЛИ анкета видна текущему полу юзера
            $q->where('user_preferences.visibility_gender', 'any')
              ->orWhere('user_preferences.visibility_gender', $userGender);
            
            // Условие 2: Возраст текущего юзера попадает в разрешенный диапазон
            // (Это применяется внутри того же замыкания, работает как AND для каждого OR выше)
        })
        ->where('user_preferences.visibility_age_min', '<=', $userAge)
        ->where('user_preferences.visibility_age_max', '>=', $userAge);

        // 4. БАЗОВЫЕ ФИЛЬТРЫ (Пол, Возраст, Город)
        $this->applyBaseFilters($query, $filters);

        // 3. БАЗОВЫЕ ФИЛЬТРЫ (Используют индексы)
        $this->applyBaseFilters($query, $filters);

        // 4. ГЕОЛОКАЦИЯ (Использует spatialIndex)
        $this->applyLocationFilter($query, $filters);

        // 5. РАСШИРЕННЫЕ ФИЛЬТРЫ (Множественный выбор через JSONB GIN)
        $this->applyAdvancedFilters($query, $filters);

        // 6. СОРТИРОВКА И ПАГИНАЦИЯ
        $sort = $filters['sort'] ?? 'last_seen';
        $this->applySorting($query, $sort);

        // Жадная загрузка связей, чтобы не было проблемы N+1 при выводе в карточках
        return $query->with(['profile', 'photos' => function($q) {
            $q->where('status', 'approved')->orderBy('is_primary', 'desc');
        }])->paginate(20); // По 20 анкет на страницу
    }

    /**
     * Базовые фильтры (Пол, Возраст, Город)
     */
    private function applyBaseFilters(Builder $query, array $filters): void
    {
        // Пол (использует составной индекс [gender, age])
        if (!empty($filters['gender']) && $filters['gender'] !== 'any') {
            $query->where('user_profiles.gender', $filters['gender']);
        }

        // Возраст (использует составной индекс [gender, age])
        $ageFrom = $filters['age_from'] ?? 18;
        $ageTo = $filters['age_to'] ?? 99;
        $query->whereBetween('user_profiles.age', [$ageFrom, $ageTo]);

        // Город (использует индекс city_id)
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

        // Только с премиумом
        if (!empty($filters['premium_only']) && $filters['premium_only'] === true) {
            $query->where('users.is_premium', true);
        }
    }

    /**
     * Фильтр по геолокации (Радиус)
     */
    private function applyLocationFilter(Builder $query, array $filters): void
    {
        // Если включен поиск по GPS
        if (!empty($filters['lat']) && !empty($filters['lng']) && !empty($filters['radius_km'])) {
            $lat = (float) $filters['lat'];
            $lng = (float) $filters['lng'];
            $radiusMeters = (int) $filters['radius_km'] * 1000; // Переводим км в метры

            // PostGIS запрос на поиск в радиусе. Использует spatialIndex!
            $query->whereNotNull('user_profiles.location')
                  ->whereRaw(
                      "ST_DWithin(user_profiles.location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)",
                      [$lng, $lat, $radiusMeters]
                  );
        }
    }

    /**
     * Расширенные фильтры (JSONB массивы и TINYINT)
     */
    private function applyAdvancedFilters(Builder $query, array $filters): void
    {
        // Одиночные поля (TINYINT)
        $simpleFilters = [
            'body_type', 'smoking', 'alcohol', 'relationship_status', 
            'children_status', 'housing', 'has_car'
        ];

        foreach ($simpleFilters as $field) {
            if (isset($filters[$field]) && $filters[$field] !== null && $filters[$field] !== 'any') {
                $query->where("user_profiles.{$field}", $filters[$field]);
            }
        }

        // Рост (Диапазон)
        if (!empty($filters['height_from'])) {
            $query->where('user_profiles.height', '>=', $filters['height_from']);
        }
        if (!empty($filters['height_to'])) {
            $query->where('user_profiles.height', '<=', $filters['height_to']);
        }

        // JSONB Массивы (Множественный выбор) - использует GIN индексы!
        $jsonFilters = ['interests', 'languages', 'sports'];

        foreach ($jsonFilters as $field) {
            if (!empty($filters[$field]) && is_array($filters[$field])) {
                // whereJsonContains транслируется в @> оператор Postgres, который летает благодаря GIN
                $query->whereJsonContains("user_profiles.{$field}", $filters[$field]);
            }
        }
    }

    /**
     * Исключение юзеров (Свайпы и Блокировки)
     * ВЫНОСИТСЯ В ПОДЗАПРОС, чтобы не грузить память массивами ID
     */
    private function getExcludedUserIds(User $user): array
    {
        // Получаем ID тех, кого лайкнули/дизлайкнули
        $swipedIds = Swipe::where('user_id', $user->id)->pluck('target_user_id')->toArray();
        
        // Получаем ID тех, кого заблокировали
        $blockedIds = UserBlock::where('blocker_id', $user->id)->pluck('blocked_id')->toArray();

        return array_merge($swipedIds, $blockedIds);
    }

    /**
     * Применение сортировки
     */
    private function applySorting(Builder $query, string $sort): void
    {
        switch ($sort) {
            case 'new_faces':
                // Сначала новые регистрации
                $query->orderBy('users.created_at', 'desc');
                break;
            case 'popular':
                // Можно добавить колонку likes_count в таблицу users и кэшировать её
                // Пока заглушка: сортировка по верификации и премиуму
                $query->orderByDesc('users.is_premium')
                      ->orderByDesc('users.is_verified')
                      ->orderByDesc('users.last_seen');
                break;
            case 'last_seen':
            default:
                // По умолчанию: кто был онлайн недавно (Использует индекс last_seen)
                $query->orderBy('users.last_seen', 'desc');
                break;
        }
    }
}