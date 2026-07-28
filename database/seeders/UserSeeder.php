<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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
        $industries = ["IT", "Медицина", "Маркетинг", "Финансы", "Дизайн", "Образование", "Продажи"];
        $occupations = ["Разработчик", "Менеджер проекта", "Врач-терапевт", "Учитель", "Дизайнер интерфейсов", "Бухгалтер", "Маркетолог"];

        // Хелпер для получения случайного набора ID из массива (для чекбоксов)
        $getRandomIds = function(array $options, int $min = 1, int $max = 3): array {
            $keys = array_keys($options);
            shuffle($keys);
            $count = rand($min, min($max, count($keys)));
            return array_slice($keys, 0, $count);
        };

        // Подключаем наш словарь опций
        $options = config('profile_options');

        for ($i = 1; $i <= 10; $i++) {
            $year = rand(1989, 2006);
            $month = rand(1, 12);
            $day = rand(1, 28);

            // Рандомно решаем, будет ли юзер премиумом (30% шанс)
            $isPremium = rand(1, 10) <= 3;
            $premiumExpires = $isPremium ? now()->addDays(rand(10, 365)) : null;

            // Генерируем Личную информацию (profile_details)
            $profileDetails = [
                'body_type' => array_rand($options['body_type']),
                'eye_color' => array_rand($options['eye_color']),
                'hair_color' => array_rand($options['hair_color']),
                'body_decorations' => $getRandomIds($options['body_decorations'], 0, 2),
                'relationship_status' => array_rand($options['relationship_status']),
                'children_status' => array_rand($options['children_status']),
                'pets' => array_rand($options['pets']),
                'housing' => array_rand($options['housing']),
                'has_car' => array_rand($options['has_car']),
                'education_level' => array_rand($options['education_level']),
                'institution' => $institutions[array_rand($institutions)],
                'graduation_year' => rand(2005, (int)date('Y') - 1),
                'industry' => $industries[array_rand($industries)],
                'occupation' => $occupations[array_rand($occupations)],
                'income' => array_rand($options['income']),
                'smoking' => array_rand($options['smoking']),
                'alcohol' => array_rand($options['alcohol']),
                'languages' => $getRandomIds($options['languages'], 1, 3),
                'sports' => $getRandomIds($options['sports'], 0, 4),
            ];

                        // Настройки фильтров чата (только для Premium)
            if ($isPremium) {
                $chatFilterEnabled = (bool)rand(0, 1);
                $chatFilterSettings = [
                    'gender' => $genders[array_rand($genders)],
                    'age_from' => rand(18, 25),
                    'age_to' => rand(35, 50),
                    'is_verified_only' => (bool)rand(0, 1),
                    'is_premium_only' => (bool)rand(0, 1),
                ];
            } else {
                $chatFilterEnabled = false;
                $chatFilterSettings = null;
            }

            // Расширенный поиск (Доступен ВСЕМ юзерам, даже бесплатным)
            $searchFilters = [
                'height_from' => rand(160, 175),
                'height_to' => rand(180, 195),
                'is_verified_only' => (bool)rand(0, 1),
                'is_premium_only' => (bool)rand(0, 1),
            ];

            // Настройки уведомлений
            $emailSettings = [
                'on_message' => (bool)rand(0, 1),
                'on_like' => (bool)rand(0, 1),
                'on_view' => false,
                'on_photo_moderated' => (bool)rand(0, 1),
                'on_report' => (bool)rand(0, 1),
                'on_ban' => true,
                'on_broadcast' => (bool)rand(0, 1),
            ];

            User::create([
                'name' => 'Пользователь ' . $i,
                'email' => 'user' . $i . '@test.com',
                'password' => Hash::make('password'),
                'is_admin' => false,
                'email_verified_at' => now(),
                'has_completed_onboarding' => true,
                
                // Базовые поля
                'gender' => $genders[array_rand($genders)],
                'birth_date' => "{$year}-{$month}-{$day}",
                'dating_goal' => $goals[array_rand($goals)],
                'city' => $cities[array_rand($cities)],
                'bio' => $bios[array_rand($bios)],
                'looking_for' => $lookingFors[array_rand($lookingFors)],
                
                // Внешность
                'height' => rand(155, 200),
                'weight' => rand(45, 110),
                
                // Статусы
                'is_premium' => $isPremium,
                'premium_expires_at' => $premiumExpires,
                'is_invisible' => $isPremium ? (bool)rand(0, 1) : false, // Невидимка только для премиум
                'profile_views' => rand(0, 500),
                'likes_count' => rand(0, 150),
                'last_seen' => now()->subMinutes(rand(1, 4320)), 
                
                // JSON-структуры
                'profile_details' => $profileDetails,
                'chat_filter_enabled' => $chatFilterEnabled,
                'chat_filter_settings' => $chatFilterSettings,
                'search_filters' => $searchFilters,
                'email_settings' => $emailSettings,
                'push_enabled' => (bool)rand(0, 1),
            ]);
        }

        $this->command->info('   ✅ Создано пользователей: ' . User::where('is_admin', false)->count());
    }
}