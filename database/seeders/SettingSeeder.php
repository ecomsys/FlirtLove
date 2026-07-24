<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;


// php artisan db:seed

// # Вариант 2: Запустить только SettingSeeder
// php artisan db:seed --class=SettingSeeder

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // ===== Основные =====
            [
                'key' => 'site_name',
                'value' => 'LovePlanet',
                'group' => 'general',
                'label' => 'Название сайта',
                'type' => 'text',
                'is_public' => true,
            ],
            [
                'key' => 'site_description',
                'value' => 'Сайт знакомств для серьезных отношений',
                'group' => 'general',
                'label' => 'Описание сайта',
                'type' => 'text',
                'is_public' => true,
            ],
            [
                'key' => 'site_url',
                'value' => 'https://loveplanet.ru',
                'group' => 'general',
                'label' => 'URL сайта',
                'type' => 'text',
                'is_public' => false,
            ],
            [
                'key' => 'contact_email',
                'value' => 'support@loveplanet.ru',
                'group' => 'general',
                'label' => 'Email поддержки',
                'type' => 'email',
                'is_public' => true,
            ],

            // ===== Модерация =====
            [
                'key' => 'max_photos_per_user',
                'value' => '10',
                'group' => 'moderation',
                'label' => 'Максимум фото на пользователя',
                'type' => 'number',
                'is_public' => false,
            ],
            [
                'key' => 'moderation_auto_approve',
                'value' => '0',
                'group' => 'moderation',
                'label' => 'Авто-одобрение фото',
                'type' => 'boolean',
                'is_public' => false,
            ],
            [
                'key' => 'require_moderation_for_new_users',
                'value' => '1',
                'group' => 'moderation',
                'label' => 'Модерация для новых пользователей',
                'type' => 'boolean',
                'is_public' => false,
            ],

            // ===== Безопасность =====
            [
                'key' => 'min_password_length',
                'value' => '8',
                'group' => 'security',
                'label' => 'Минимальная длина пароля',
                'type' => 'number',
                'is_public' => false,
            ],
            [
                'key' => 'max_login_attempts',
                'value' => '5',
                'group' => 'security',
                'label' => 'Максимум попыток входа',
                'type' => 'number',
                'is_public' => false,
            ],

            // ===== Социальные сети =====
            [
                'key' => 'telegram_url',
                'value' => 'https://t.me/loveplanet',
                'group' => 'social',
                'label' => 'Telegram',
                'type' => 'url',
                'is_public' => true,
            ],
            [
                'key' => 'instagram_url',
                'value' => 'https://instagram.com/loveplanet',
                'group' => 'social',
                'label' => 'Instagram',
                'type' => 'url',
                'is_public' => true,
            ],
            [
                'key' => 'vk_url',
                'value' => 'https://vk.com/loveplanet',
                'group' => 'social',
                'label' => 'ВКонтакте',
                'type' => 'url',
                'is_public' => true,
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        $this->command->info('✅ Настройки созданы: ' . count($settings));
    }
}