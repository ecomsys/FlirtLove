<?php 

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserPreference;
use App\Models\Album;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('👑 Создаем Владельцев проекта (Суперадминов)...');

        // Массив Владельцев. Можешь поменять имена и email под себя
        $founders = [
            [
                'email' => 'admin@admin.com',
                'name' => 'Главный Владелец',
                'password' => '12121212',
            ],
            [
                'email' => 'admin2@admin.com',
                'name' => 'Второй Владелец',
                'password' => '12121212',
            ],
        ];

        $founderIds = [];

        foreach ($founders as $founderData) {
            // 1. Создаем или обновляем Владельца
            $admin = User::updateOrCreate(
                ['email' => $founderData['email']],
                [
                    'name' => $founderData['name'],
                    'password' => Hash::make($founderData['password']),
                    'role' => 'admin', 
                    'status' => 'active', 
                    'is_premium' => true, 
                    'premium_expires_at' => now()->addYears(10),
                    'is_verified' => true,
                    'has_completed_onboarding' => true,
                    'last_login_at' => now(),
                    'last_login_ip' => '127.0.0.1',
                    'last_seen' => now(),
                    'email_verified_at' => now(),
                ]
            );

            $founderIds[] = $admin->id;

            // 2. Создаем профиль
            UserProfile::updateOrCreate(
                ['user_id' => $admin->id],
                [
                    'gender' => 'male',
                    'birth_date' => '1990-01-01',
                    'dating_goal' => 'friends',
                    'city' => 'Москва',
                    'country' => 'Россия',
                    'headline' => $founderData['name'] . ' сайта',
                    'bio' => 'Я тут главный! Если есть вопросы - пишите в поддержку. 😎',
                    'looking_for' => 'Помогаем пользователям находить любовь ❤️',
                    'interests' => ['разработка', 'управление', 'поддержка', 'путешествия'],
                    'self_portrait' => null, 
                    
                    'body_type' => 2,
                    'eye_color' => 1,
                    'hair_color' => 1,
                    'height' => 180,
                    'weight' => 80,
                    
                    'relationship_status' => 1,
                    'children_status' => 1,
                    'pets' => 1,
                    'housing' => 1,
                    'has_car' => 1,
                    'smoking' => 1,
                    'alcohol' => 1,
                    'zodiac_sign' => 10, 
                    
                    'body_decorations' => [],
                    'languages' => [1, 2], 
                    'sports' => [1, 2],
                    
                    'education' => 'Высшее',                   
                    'institution' => 'МГУ',
                    'institution_year' => 2012,
                    'activity' => 'IT',
                    'position' => 'CEO',
                ]
            );

            // 3. Создаем настройки
            UserPreference::updateOrCreate(
                ['user_id' => $admin->id],
                [
                    'locale' => 'ru',
                    'theme' => 'dark',
                    
                    'preferred_age_min' => 18,
                    'preferred_age_max' => 99,
                    'preferred_gender' => 'any',
                    'preferred_distance_km' => 10000,
                    
                    'search_filters' => null, 
                    'chat_filter_enabled' => false,
                    'chat_filter_settings' => null,
                    
                    'is_invisible' => false,
                    'hide_intimate' => false,
                    'disable_photo_comments' => false,
                    'hide_from_search' => true, // Владельцев не нужно показывать в ленте!
                    
                    'superlikes_remaining' => 999,
                    'superlikes_reset_at' => now()->addDays(365),
                    'credits' => 999999, 
                    
                    'push_enabled' => true,
                    'email_enabled' => true,
                    'email_settings' => [
                        'on_message'    => true,
                        'on_like'       => true,
                        'on_view'       => true,
                        'on_gift'       => true,
                        'on_event'      => true, 
                        'on_broadcast'  => true,
                        'sub_new_faces' => false,
                        'sub_popular'   => false,
                    ],
                ]
            );

            // 4. Создаем альбом
            Album::updateOrCreate(
                [
                    'user_id' => $admin->id,
                    'is_default' => true,
                ],
                [
                    'name' => 'Фото владельца',
                    'description' => 'Скрытые фотографии',
                    'is_private' => false,
                    'photos_count' => 0, 
                ]
            );

            $this->command->info("   ✅ Владелец создан:");
            $this->command->info("      📧 Email: {$founderData['email']}");
            $this->command->info("      🔑 Пароль: {$founderData['password']}");
            $this->command->info("      🆔 ID: {$admin->id}");
        }

        // Выводим подсказку для .env
        $foundersString = implode(',', $founderIds);
        $this->command->warn("\n⚠️  ВАЖНО! Добавьте эту строку в ваш .env файл:");
        $this->command->line("APP_FOUNDERS={$foundersString}");
        $this->command->info("Это защитит аккаунты Владельцев от случайного удаления в админке.\n");
    }
}