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
        $goals = ['friends', 'romantic', 'family', 'casual'];
        $cities = ['Москва', 'Санкт-Петербург', 'Казань', 'Новосибирск', 'Екатеринбург', 'Сочи', 'Краснодар', 'Владивосток'];
        
        // Текстовые заготовки
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

        // Подключаем словарь опций
        $options = config('profile_options');

        // Хелпер для получения случайного набора ID из массива
        $getRandomIds = function(array $options, int $min = 1, int $max = 3): array {
            $keys = array_keys($options);
            shuffle($keys);
            $count = rand($min, min($max, count($keys)));
            return array_slice($keys, 0, $count);
        };

        // Отключаем события модели, чтобы не создавались дубликаты
        User::unsetEventDispatcher();

        for ($i = 1; $i <= 10; $i++) {
            $year = rand(1989, 2006);
            $month = rand(1, 12);
            $day = rand(1, 28);
            $birthDate = "{$year}-{$month}-{$day}";

            // Рандомно решаем, будет ли юзер премиумом (30% шанс)
            $isPremium = rand(1, 10) <= 3;
            $premiumExpires = $isPremium ? now()->addDays(rand(10, 365)) : null;

            // Рандомный пол
            $gender = $genders[array_rand($genders)];

            // ============================================
            // 1. СОЗДАЕМ ПОЛЬЗОВАТЕЛЯ
            // ============================================
            $user = User::create([
                'name' => 'Пользователь ' . $i,
                'email' => 'user' . $i . '@test.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                
                // Статусы и флаги
                'is_admin' => false,
                'is_banned' => false,
                'is_premium' => $isPremium,
                'premium_expires_at' => $premiumExpires,
                'is_verified' => (bool) rand(0, 1),
                'has_completed_onboarding' => true,
                'is_deactivated' => false,
                
                // Активность
                'superlikes_remaining' => rand(0, 5),
                'last_login_at' => now()->subDays(rand(0, 30)),
                'last_login_ip' => '127.0.0.1',
                'last_seen' => now()->subMinutes(rand(1, 4320)),
            ]);

            // ============================================
            // 2. СОЗДАЕМ ПРОФИЛЬ
            // ============================================
            $profile = UserProfile::create([
                'user_id' => $user->id,
                'gender' => $gender,
                'birth_date' => $birthDate,
                'dating_goal' => $goals[array_rand($goals)],
                'city' => $cities[array_rand($cities)],
                
                // Тексты
                'status' => $bios[array_rand($bios)],
                'bio' => $bios[array_rand($bios)],
                'looking_for' => $lookingFors[array_rand($lookingFors)],
                'interests' => ['музыка', 'кино', 'спорт', 'путешествия', 'книги'],
                
                // Внешность (одиночный выбор)
                'body_type' => array_rand($options['body_type']),
                'eye_color' => array_rand($options['eye_color']),
                'hair_color' => array_rand($options['hair_color']),
                'height' => rand(155, 200),
                'weight' => rand(45, 110),
                
                // Личные данные (одиночный выбор)
                'relationship_status' => array_rand($options['relationship_status']),
                'children_status' => array_rand($options['children_status']),
                'pets' => array_rand($options['pets']),
                'housing' => array_rand($options['housing']),
                'has_car' => array_rand($options['has_car']),
                'smoking' => array_rand($options['smoking']),
                'alcohol' => array_rand($options['alcohol']),
                
                // Множественный выбор (JSON)
                'body_decorations' => $getRandomIds($options['body_decorations'], 0, 2),
                'languages' => $getRandomIds($options['languages'], 1, 3),
                'sports' => $getRandomIds($options['sports'], 0, 4),
                
                // Работа и образование
                'education' => array_rand($options['education_level']),
                'occupation' => $positions[array_rand($positions)],
                'institution' => $institutions[array_rand($institutions)],
                'institution_year' => rand(2005, (int) date('Y') - 1),
                'activity' => $activities[array_rand($activities)],
                'position' => $positions[array_rand($positions)],
                
                // Гороскоп
                'zodiac_sign' => $this->getZodiacSign($month, $day),
                
                // Счетчики
                'profile_views' => rand(0, 500),
                'likes_count' => rand(0, 150),
            ]);

            // ============================================
            // 3. СОЗДАЕМ НАСТРОЙКИ
            // ============================================
            
            // Настройки фильтров чата (только для Premium)
            if ($isPremium) {
                $chatFilterEnabled = (bool) rand(0, 1);
                $chatFilterSettings = [
                    'gender' => $genders[array_rand($genders)],
                    'age_from' => rand(18, 25),
                    'age_to' => rand(35, 50),
                    'is_verified_only' => (bool) rand(0, 1),
                    'is_premium_only' => (bool) rand(0, 1),
                ];
            } else {
                $chatFilterEnabled = false;
                $chatFilterSettings = null;
            }

            // Расширенный поиск (Доступен ВСЕМ юзерам)
            $searchFilters = [
                'body_type' => array_rand($options['body_type']),
                'height_from' => rand(160, 175),
                'height_to' => rand(180, 195),
                'is_verified_only' => (bool) rand(0, 1),
                'is_premium_only' => (bool) rand(0, 1),
            ];

            // Настройки уведомлений
            $emailSettings = [
                'on_message' => (bool) rand(0, 1),
                'on_like' => (bool) rand(0, 1),
                'on_view' => false,
                'on_photo_moderated' => (bool) rand(0, 1),
                'on_report' => (bool) rand(0, 1),
                'on_ban' => true,
                'on_broadcast' => (bool) rand(0, 1),
            ];

            UserPreference::create([
                'user_id' => $user->id,
                'locale' => ['en', 'ru'][array_rand(['en', 'ru'])],
                'theme' => ['light', 'dark'][array_rand(['light', 'dark'])],
                
                // Предпочтения поиска
                'preferred_age_min' => rand(18, 25),
                'preferred_age_max' => rand(30, 45),
                'preferred_gender' => $genders[array_rand($genders)],
                'preferred_distance_km' => rand(10, 100),
                
                'search_filters' => $searchFilters,
                'chat_filter_enabled' => $chatFilterEnabled,
                'chat_filter_settings' => $chatFilterSettings,
                
                // Приватность
                'is_invisible' => $isPremium ? (bool) rand(0, 1) : false,
                'hide_intimate' => (bool) rand(0, 1),
                'disable_photo_comments' => (bool) rand(0, 1),
                'hide_from_search' => (bool) rand(0, 1),
                
                // Уведомления
                'push_enabled' => (bool) rand(0, 1),
                'email_settings' => $emailSettings,
            ]);

            // ============================================
            // 4. СОЗДАЕМ АЛЬБОМ ПО УМОЛЧАНИЮ
            // ============================================
            Album::create([
                'user_id' => $user->id,
                'name' => 'Общие',
                'description' => 'Основные фотографии',
                'is_default' => true,
            ]);

            // Прогресс-бар
            if ($i % 5 === 0) {
                $this->command->info("   ⏳ Создано {$i} пользователей...");
            }
        }

        $this->command->info('   ✅ Создано пользователей: ' . User::where('is_admin', false)->count());
    }

    /**
     * Определяем знак зодиака по дате
     */
    private function getZodiacSign(int $month, int $day): string
    {
        $zodiacs = [
            'aries' => ['start' => '03-21', 'end' => '04-19'],
            'taurus' => ['start' => '04-20', 'end' => '05-20'],
            'gemini' => ['start' => '05-21', 'end' => '06-20'],
            'cancer' => ['start' => '06-21', 'end' => '07-22'],
            'leo' => ['start' => '07-23', 'end' => '08-22'],
            'virgo' => ['start' => '08-23', 'end' => '09-22'],
            'libra' => ['start' => '09-23', 'end' => '10-22'],
            'scorpio' => ['start' => '10-23', 'end' => '11-21'],
            'sagittarius' => ['start' => '11-22', 'end' => '12-21'],
            'capricorn' => ['start' => '12-22', 'end' => '01-19'],
            'aquarius' => ['start' => '01-20', 'end' => '02-18'],
            'pisces' => ['start' => '02-19', 'end' => '03-20'],
        ];

        $date = sprintf('%02d-%02d', $month, $day);
        
        foreach ($zodiacs as $sign => $dates) {
            if ($date >= $dates['start'] && $date <= $dates['end']) {
                return $sign;
            }
        }

        return 'unknown';
    }
}