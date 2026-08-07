<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserPreference;
use App\Models\Album;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('👤 Создаем обычных пользователей...');

        $genders = ['male', 'female'];
        $goals = ['friends', 'romantic', 'family', 'casual', 'travel'];
        $cities = ['Москва', 'Санкт-Петербург', 'Казань', 'Новосибирск', 'Екатеринбург', 'Сочи', 'Краснодар', 'Владивосток'];
        
        $bios = [
            "Люблю путешествия и активный отдых. Ищу единомышленников.",
            "Ищу серьезные отношения. Ценю честность и юмор.",
            "Музыка, кино, долгие прогулки по городу. Скучно не будет!",
            "Верю в настоящую любовь. Готов к новым знакомствам.",
            "Работаю в IT, в свободное время хожу в зал и играю на гитаре."
        ];
        $lookingFors = [
            "Хочу найти доброго, заботливого человека для серьезных отношений.",
            "Ищу партнера для совместных путешествий и спорта.",
            "Мечтаю о семье и уюте. Хочу детей в будущем.",
            "Просто хочу найти классных людей для общения."
        ];
        $institutions = ["МГУ", "СПбГУ", "Кембридж", "МГТУ им. Баумана", "МГИМО", "ВШЭ"];
        $activities = ["IT", "Медицина", "Маркетинг", "Финансы", "Дизайн", "Образование", "Продажи"];
        $positions = ["Разработчик", "Менеджер проекта", "Врач-терапевт", "Учитель", "Дизайнер интерфейсов", "Бухгалтер", "Маркетолог"];

        // Пытаемся получить опции из конфига, если его нет - используем заглушки
        $options = config('profile_options', [
            'body_type' => [1 => 'Среднее', 2 => 'Спортивное', 3 => 'Полное'],
            'eye_color' => [1 => 'Карие', 2 => 'Голубые'],
            'hair_color' => [1 => 'Блонд', 2 => 'Брюнет'],
            'relationship_status' => [1 => 'Холост', 2 => 'В браке'],
            'children_status' => [1 => 'Нет', 2 => 'Есть'],
            'pets' => [1 => 'Нет', 2 => 'Кошка', 3 => 'Собака'],
            'housing' => [1 => 'Своя', 2 => 'Аренда'],
            'has_car' => [1 => 'Нет', 2 => 'Да'],
            'smoking' => [1 => 'Нет', 2 => 'Да'],
            'alcohol' => [1 => 'Нет', 2 => 'Иногда'],
            'body_decorations' => [1 => 'Тату', 2 => 'Пирсинг'],
            'languages' => [1 => 'Русский', 2 => 'Английский'],
            'sports' => [1 => 'Бег', 2 => 'Шахматы'],
            'education_level' => [1 => 'Среднее', 2 => 'Высшее'],
        ]);

        $getRandomIds = function(array $options, int $min = 1, int $max = 3): array {
            $keys = array_keys($options);
            shuffle($keys);
            $count = rand($min, min($max, count($keys)));
            return array_slice($keys, 0, $count);
        };

        // НЕ отключаем события! В модели User событие created само создаст пустые связи.
        // А мы ниже через updateOrCreate их заполним.

        for ($i = 1; $i <= 10; $i++) {
            $year = rand(1989, 2006);
            $month = rand(1, 12);
            $day = rand(1, 28);
            $birthDate = "{$year}-{$month}-{$day}";

            $isPremium = rand(1, 10) <= 3;
            $premiumExpires = $isPremium ? now()->addDays(rand(10, 365)) : null;

            $gender = $genders[array_rand($genders)];

            // Имитация ботоварни (пользователи 8, 9, 10)
            if (in_array($i, [8, 9, 10])) {
                $ip = '185.23.44.12'; 
                $status = 'shadowbanned'; // Теневой бан для ботов
            } else {
                $ip = rand(100, 220) . '.' . rand(10, 250) . '.' . rand(1, 255) . '.' . rand(1, 255);
                $status = 'active';
            }

            // 1. Создаем Юзера (событие booted создаст пустые profile, preferences, album)
            $user = User::updateOrCreate(
                ['email' => 'user' . $i . '@test.com'],
                [
                    'name' => 'Пользователь ' . $i,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'role' => 'user',
                    'status' => $status, // active или shadowbanned
                    'is_premium' => $isPremium,
                    'premium_expires_at' => $premiumExpires,
                    'is_verified' => (bool) rand(0, 1),
                    'has_completed_onboarding' => true,
                    'last_login_at' => now()->subDays(rand(0, 30)),
                    'last_login_ip' => $ip, 
                    'last_seen' => now()->subMinutes(rand(1, 4320)),
                ]
            );

            $lat = 55.5 + (rand(0, 100) / 100); 
            $lng = 37.3 + (rand(0, 100) / 100); 

            // 2. Обновляем Профиль
            UserProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'gender' => $gender,
                    'birth_date' => $birthDate,
                    'dating_goal' => $goals[array_rand($goals)],
                    'city' => $cities[array_rand($cities)],
                    'country' => 'Россия',
                    'headline' => $bios[array_rand($bios)], // Было status
                    'bio' => $bios[array_rand($bios)],
                    'looking_for' => $lookingFors[array_rand($lookingFors)],
                    'interests' => ['музыка', 'кино', 'спорт', 'путешествия', 'книги'],
                    'self_portrait' => null,
                    'body_type' => array_rand($options['body_type']),
                    'eye_color' => array_rand($options['eye_color']),
                    'hair_color' => array_rand($options['hair_color']),
                    'height' => rand(155, 200),
                    'weight' => rand(45, 110),
                    'relationship_status' => array_rand($options['relationship_status']),
                    'children_status' => array_rand($options['children_status']),
                    'pets' => array_rand($options['pets']),
                    'housing' => array_rand($options['housing']),
                    'has_car' => array_rand($options['has_car']),
                    'smoking' => array_rand($options['smoking']),
                    'alcohol' => array_rand($options['alcohol']),
                    'zodiac_sign' => $this->getZodiacSign($month, $day), // Теперь возвращает int (1-12)
                    'body_decorations' => $getRandomIds($options['body_decorations'], 0, 2),
                    'languages' => $getRandomIds($options['languages'], 1, 3),
                    'sports' => $getRandomIds($options['sports'], 0, 4),
                    'education' => array_rand($options['education_level']),                    
                    'institution' => $institutions[array_rand($institutions)],
                    'institution_year' => rand(2005, (int) date('Y') - 1),
                    'activity' => $activities[array_rand($activities)],
                    'position' => $positions[array_rand($positions)],
                    // PostGIS: Обязательно добавляем ::geography, так как колонка geography, а не geometry
                    'location' => DB::raw("ST_SetSRID(ST_MakePoint({$lng}, {$lat}), 4326)::geography"),
                ]
            );

            // 3. Обновляем Настройки
            $emailSettings = [
                'on_message' => (bool) rand(0, 1),
                'on_like' => (bool) rand(0, 1),
                'on_view' => (bool) rand(0, 1),
                'on_gift' => (bool) rand(0, 1),
                'on_event' => (bool) rand(0, 1), // Новый стандарт
                'on_broadcast' => (bool) rand(0, 1),
                'sub_new_faces' => (bool) rand(0, 1),
                'sub_popular' => (bool) rand(0, 1),
            ];

            UserPreference::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'locale' => 'ru',
                    'theme' => 'light',
                    'preferred_age_min' => rand(18, 25),
                    'preferred_age_max' => rand(30, 45),
                    'preferred_gender' => $genders[array_rand($genders)],
                    'preferred_distance_km' => rand(10, 100),
                    'search_filters' => null,
                    'chat_filter_enabled' => $isPremium ? (bool) rand(0, 1) : false,
                    'chat_filter_settings' => null,
                    'is_invisible' => $isPremium ? (bool) rand(0, 1) : false,
                    'hide_intimate' => (bool) rand(0, 1),
                    'disable_photo_comments' => (bool) rand(0, 1),
                    'hide_from_search' => false,
                    'superlikes_remaining' => rand(0, 5), // Переехало из users
                    'superlikes_reset_at' => now()->addHours(rand(1, 24)),
                    'credits' => rand(0, 500), // Внутренняя валюта
                    'push_enabled' => (bool) rand(0, 1),
                    'email_enabled' => true, // Глобальный тумблер
                    'email_settings' => $emailSettings,
                ]
            );

            // 4. Обновляем Альбом
            Album::updateOrCreate(
                ['user_id' => $user->id, 'is_default' => true],
                [
                    'name' => 'Общие',
                    'description' => 'Основные фотографии',
                    'is_private' => false,
                    'photos_count' => 0,
                ]
            );

            if ($i % 5 === 0) {
                $this->command->info("   ⏳ Создано {$i} пользователей...");
            }
        }

        $this->command->info('   ✅ Создано пользователей: ' . User::where('role', 'user')->count());
    }

    /**
     * Возвращаем номер знака зодиака (1-12) вместо строки
     */
    private function getZodiacSign(int $month, int $day): int
    {
        $zodiacs = [
            1 => ['start' => '03-21', 'end' => '04-19'], // Овен
            2 => ['start' => '04-20', 'end' => '05-20'], // Телец
            3 => ['start' => '05-21', 'end' => '06-20'], // Близнецы
            4 => ['start' => '06-21', 'end' => '07-22'], // Рак
            5 => ['start' => '07-23', 'end' => '08-22'], // Лев
            6 => ['start' => '08-23', 'end' => '09-22'], // Дева
            7 => ['start' => '09-23', 'end' => '10-22'], // Весы
            8 => ['start' => '10-23', 'end' => '11-21'], // Скорпион
            9 => ['start' => '11-22', 'end' => '12-21'], // Стрелец
            10 => ['start' => '12-22', 'end' => '01-19'], // Козерог
            11 => ['start' => '01-20', 'end' => '02-18'], // Водолей
            12 => ['start' => '02-19', 'end' => '03-20'], // Рыбы
        ];

        $date = sprintf('%02d-%02d', $month, $day);
        
        foreach ($zodiacs as $signId => $dates) {
            // Учитываем переход через год для Козерога
            if ($signId === 10) {
                if ($date >= $dates['start'] || $date <= $dates['end']) {
                    return $signId;
                }
            } else {
                if ($date >= $dates['start'] && $date <= $dates['end']) {
                    return $signId;
                }
            }
        }

        return 1; // По умолчанию Овен (если что-то пошло не так)
    }
}