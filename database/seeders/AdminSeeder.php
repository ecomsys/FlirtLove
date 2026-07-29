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

        // Находим или создаем админа
        $admin = User::updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Администратор',
                'password' => Hash::make($adminPassword),
                'is_admin' => true,
                'is_banned' => false,
                'is_premium' => true, // Админу даем премиум
                'premium_expires_at' => now()->addYears(10),
                'is_verified' => true,
                'has_completed_onboarding' => true,
                'is_deactivated' => false,
                'superlikes_remaining' => 999,
                'last_login_at' => now(),
                'last_login_ip' => '127.0.0.1',
                'last_seen' => now(),
                'email_verified_at' => now(),
            ]
        );

        // ============================================
        // СОЗДАЕМ ПРОФИЛЬ АДМИНА
        // ============================================
        $profile = UserProfile::updateOrCreate(
            ['user_id' => $admin->id],
            [
                'gender' => 'male',
                'birth_date' => '1990-01-01',
                'dating_goal' => 'friends',
                'city' => 'Москва',
                'address' => 'Москва, Россия',
                'country' => 'Россия',
                'status' => 'Главный администратор сайта',
                'bio' => 'Я тут главный! Если есть вопросы - пишите в поддержку. 😎',
                'looking_for' => 'Помогаю пользователям находить любовь ❤️',
                'interests' => ['разработка', 'управление', 'поддержка', 'путешествия'],
                
                // Внешность (не важно для админа)
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
                
                'body_decorations' => [],
                'languages' => [1, 2], // Русский, Английский
                'sports' => [1, 2],
                
                'education' => 4, // Высшее
                'occupation' => 'Администратор',
                'institution' => 'МГУ',
                'institution_year' => 2012,
                'activity' => 'IT',
                'position' => 'CEO',
                
                'zodiac_sign' => 'capricorn',
                
                'profile_views' => 9999,
                'likes_count' => 999,
            ]
        );

        // ============================================
        // СОЗДАЕМ НАСТРОЙКИ АДМИНА
        // ============================================
        $preferences = UserPreference::updateOrCreate(
            ['user_id' => $admin->id],
            [
                'locale' => 'ru',
                'theme' => 'dark',
                
                'preferred_age_min' => 18,
                'preferred_age_max' => 99,
                'preferred_gender' => 'any',
                'preferred_distance_km' => 10000,
                
                'search_filters' => [
                    'body_type' => null,
                    'height_from' => null,
                    'height_to' => null,
                    'is_verified_only' => false,
                    'is_premium_only' => false,
                ],
                
                'chat_filter_enabled' => false,
                'chat_filter_settings' => null,
                
                'is_invisible' => false,
                'hide_intimate' => false,
                'disable_photo_comments' => false,
                'hide_from_search' => false,
                
                'push_enabled' => true,
                'email_settings' => [
                    'on_message' => true,
                    'on_like' => true,
                    'on_view' => true,
                    'on_photo_moderated' => true,
                    'on_report' => true,
                    'on_ban' => true,
                    'on_broadcast' => true,
                ],
            ]
        );

        // ============================================
        // СОЗДАЕМ АЛЬБОМ ДЛЯ АДМИНА
        // ============================================
        $album = Album::updateOrCreate(
            [
                'user_id' => $admin->id,
                'is_default' => true,
            ],
            [
                'name' => 'Админские фото',
                'description' => 'Фотографии администратора',
            ]
        );

        $this->command->info('   ✅ Админ создан:');
        $this->command->info("      📧 Email: {$adminEmail}");
        $this->command->info("      🔑 Пароль: {$adminPassword}");
        $this->command->info("      🆔 ID: {$admin->id}");
        $this->command->info("      📋 Профиль: ID {$profile->id}");
        $this->command->info("      ⚙️ Настройки: ID {$preferences->id}");
        $this->command->info("      📁 Альбом: ID {$album->id}");
    }
}