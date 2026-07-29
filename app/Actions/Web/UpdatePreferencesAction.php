<?php

namespace App\Actions;

use App\Models\User;
use App\Models\UserPreference;

class UpdatePreferencesAction
{
    /**
     * Обновить настройки поиска, приватности и уведомлений.
     *
     * @param User $user Текущий юзер
     * @param array $data Валидированные данные из Request
     * @return UserPreference
     */
    public function execute(User $user, array $data): UserPreference
    {
        $preferences = $user->preferences;

        // 1. ОЧИСТКА ПУСТЫХ ФИЛЬТРОВ
        // Если юзер сбросил расширенный поиск (например, удалил выбор "Курение"),
        // фронтенд может прислать null или просто не прислать ключ.
        // Мы чистим массив, чтобы не хранить в JSON пустышки типа {"smoking": null}
        if (isset($data['search_filters'])) {
            $data['search_filters'] = array_filter(
                $data['search_filters'], 
                fn($value) => $value !== null && $value !== 0 && $value !== []
            );
        }

        // То же самое для фильтров чата и email-уведомлений
        if (isset($data['chat_filter_settings'])) {
            $data['chat_filter_settings'] = array_filter(
                $data['chat_filter_settings'], 
                fn($value) => $value !== null
            );
        }

        if (isset($data['email_settings'])) {
            $data['email_settings'] = array_filter(
                $data['email_settings'], 
                fn($value) => $value !== null
            );
        }

        // 2. ОБНОВЛЕНИЕ
        // Eloquent сам превратит массивы search_filters, chat_filter_settings 
        // и email_settings в JSON строки благодаря $casts в модели!
        // И он сам отсечет лишние поля благодаря $fillable.
        $preferences->update($data);

        return $preferences;
    }
}

/*=====================================
ИНФО
=====================================
Как фронтенд должен отправлять данные ?

Чтобы наш GetRecommendedUsersAction (который мы написали ранее) мог накидывать WHERE условия, 
JSON search_filters должен быть плоским объектом (словарем), а не сложной структурой с ext_1, ext_2.

Когда юзер выбирает 3 динамических параметра на фронте (например: Рост, Курение, Языки), 
JS должен собрать их в такой объект перед отправкой на бэкенд:

{
  "preferred_gender": "female",
  "preferred_age_min": 18,
  "preferred_age_max": 25,
  "preferred_distance_km": 50,
  
  // Вот тут ВАЖНО! Ключи - это названия колонок из наших словарей/JSON-массивов
  "search_filters": {
    "height_from": 170,
    "height_to": 185,
    "smoking": 8,        // ID из словаря (8 = Против курения)
    "languages": [2, 4]  // Массив ID для множественного выбора
  }
}

*/