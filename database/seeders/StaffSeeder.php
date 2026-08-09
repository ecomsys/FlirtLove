<?php
namespace Database\Seeders;

use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserPreference;
use App\Models\Album;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('👷 Создаем сотрудников (Модератор и Саппорт)...');

        $staffMembers = [
            [
                'name' => 'Модератор Василий',
                'email' => 'moderator@moderator.com',
                'role' => 'moderator',
                'gender' => 'male',
                'headline' => 'Служба безопасности',
                'bio' => 'Слежу за порядком на сайте. Нарушители будут забанены!',               
            ],
            [
                'name' => 'Поддержка Анна',
                'email' => 'support@support.com',
                'role' => 'support',
                'gender' => 'female',
                'headline' => 'Служба заботы о пользователях',
                'bio' => 'Всегда рада помочь! Обращайтесь по любым вопросам.',     
            ],
        ];

        $password = '12121212';

        foreach ($staffMembers as $member) {
            // 1. Создаем или обновляем аккаунт
            $user = User::updateOrCreate(
                ['email' => $member['email']],
                [
                    'name' => $member['name'],
                    'password' => Hash::make($password),
                    'role' => $member['role'], // moderator или support
                    'status' => 'active',
                    'is_premium' => true, // Даем им VIP для тестов
                    'premium_expires_at' => now()->addYears(5),
                    'is_verified' => true,
                    'has_completed_onboarding' => true,
                    'last_login_at' => now(),
                    'last_login_ip' => '127.0.0.1',
                    'last_seen' => now(),
                    'email_verified_at' => now(),
                ]
            );

            // 2. Профиль
            UserProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'gender' => $member['gender'],
                    'birth_date' => '1995-05-15',
                    'dating_goal' => 'friends',
                    'city' => 'Санкт-Петербург',
                    'country' => 'Россия',
                    'headline' => $member['headline'],
                    'bio' => $member['bio'],
                    'looking_for' => 'Ищу нарушителей порядка (и хороших людей)!',
                    'interests' => ['работа', 'общение', 'кино'],
                    'height' => $member['gender'] === 'male' ? 180 : 165,
                    'weight' => $member['gender'] === 'male' ? 80 : 55,
                    'zodiac_sign' => 5, // Просто рандомное число                                    
                ]
            );

            // 3. Настройки
            UserPreference::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'locale' => 'ru',
                    'theme' => 'light',
                    'preferred_gender' => 'any',
                    'preferred_distance_km' => 10000,
                    'hide_from_search' => true, // Не показывать в ленте!
                    'superlikes_remaining' => 100,
                    'credits' => 5000,
                    'push_enabled' => true,
                    'email_enabled' => true,
                    'email_settings' => [
                        'on_message'    => true,
                        'on_like'       => false,
                        'on_view'       => false,
                        'on_gift'       => false,
                        'on_event'      => true, // Саппорт и модератор должны видеть события
                        'on_broadcast'  => true,
                        'sub_new_faces' => false,
                        'sub_popular'   => false,
                    ],
                ]
            );

            // 4. Альбом
            Album::updateOrCreate(
                ['user_id' => $user->id, 'is_default' => true],
                [
                    'name' => 'Общие',
                    'is_private' => false,
                    'photos_count' => 0,
                ]
            );

            $this->command->info("   ✅ Создан {$member['role']}: {$member['email']} (Пароль: {$password})");
        }
    }
}