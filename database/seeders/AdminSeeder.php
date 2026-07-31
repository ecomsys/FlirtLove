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
        $this->command->info('👑 Создаем главного администратора...');

        $adminEmail = 'admin@admin.com';
        $adminPassword = '12121212';

        // 1. Создаем или обновляем Админа
        $admin = User::updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Администратор',
                'password' => Hash::make($adminPassword),
                'role' => 'admin', // Новое поле вместо is_admin
                'status' => 'active', // Новое поле вместо is_banned/is_deactivated
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

        // 2. Создаем профиль Админа
        UserProfile::updateOrCreate(
            ['user_id' => $admin->id],
            [
                'gender' => 'male',
                'birth_date' => '1990-01-01',
                'dating_goal' => 'friends',
                'city' => 'Москва',
                'country' => 'Россия',
                'headline' => 'Главный администратор сайта', // Было status
                'bio' => 'Я тут главный! Если есть вопросы - пишите в поддержку. 😎',
                'looking_for' => 'Помогаю пользователям находить любовь ❤️',
                'interests' => ['разработка', 'управление', 'поддержка', 'путешествия'],
                'self_portrait' => null, // Новое JSON поле, пока пусто
                
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
                'zodiac_sign' => 10, // Теперь это число (например, 10 = Козерог)
                
                'body_decorations' => [],
                'languages' => [1, 2], 
                'sports' => [1, 2],
                
                'education' => 'Высшее', 
                'occupation' => 'Администратор',
                'institution' => 'МГУ',
                'institution_year' => 2012,
                'activity' => 'IT',
                'position' => 'CEO',
                
                // location и address не указываем, админу не обязательно
            ]
        );

        // 3. Создаем настройки Админа
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
                'hide_from_search' => true, // Админа не нужно показывать в ленте юзерам!
                
                // Лимиты и валюта (Переехали из таблицы users)
                'superlikes_remaining' => 999,
                'superlikes_reset_at' => now()->addDays(365),
                'credits' => 999999, // Админ может тестировать подарки
                
                // Уведомления
                'push_enabled' => true,
                'email_enabled' => true,
                'email_settings' => [
                    'on_message'    => true,
                    'on_like'       => true,
                    'on_view'       => true,
                    'on_gift'       => true,
                    'on_event'      => true, // Заменили on_report, on_ban, on_photo_moderated
                    'on_broadcast'  => true,
                    'sub_new_faces' => false,
                    'sub_popular'   => false,
                ],
            ]
        );

        // 4. Создаем альбом Админа
        Album::updateOrCreate(
            [
                'user_id' => $admin->id,
                'is_default' => true,
            ],
            [
                'name' => 'Админские фото',
                'description' => 'Фотографии администратора',
                'is_private' => false,
                'photos_count' => 0, // Новое поле
            ]
        );

        $this->command->info('   ✅ Админ создан:');
        $this->command->info("      📧 Email: {$adminEmail}");
        $this->command->info("      🔑 Пароль: {$adminPassword}");
        $this->command->info("      🆔 ID: {$admin->id}");
    }
}