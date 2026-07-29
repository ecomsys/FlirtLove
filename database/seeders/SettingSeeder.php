<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

// php artisan db:seed

// # Вариант 2: Запустить только SettingSeeder
// php artisan db:seed --class=SettingSeeder

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('⚙️  Заполняем настройки сайта...');

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
            [
                'key' => 'default_locale',
                'value' => 'ru',
                'group' => 'general',
                'label' => 'Язык по умолчанию',
                'type' => 'text',
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
            [
                'key' => 'max_photos_for_moderation',
                'value' => '5',
                'group' => 'moderation',
                'label' => 'Максимум фото на модерацию',
                'type' => 'number',
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
            [
                'key' => 'session_lifetime',
                'value' => '120',
                'group' => 'security',
                'label' => 'Время жизни сессии (минуты)',
                'type' => 'number',
                'is_public' => false,
            ],

            // ===== Премиум =====
            [
                'key' => 'premium_price_monthly',
                'value' => '499',
                'group' => 'premium',
                'label' => 'Цена премиума (месяц)',
                'type' => 'number',
                'is_public' => true,
            ],
            [
                'key' => 'premium_price_yearly',
                'value' => '2999',
                'group' => 'premium',
                'label' => 'Цена премиума (год)',
                'type' => 'number',
                'is_public' => true,
            ],
            [
                'key' => 'premium_superlikes_per_day',
                'value' => '5',
                'group' => 'premium',
                'label' => 'Суперлайков в день (премиум)',
                'type' => 'number',
                'is_public' => false,
            ],
            [
                'key' => 'free_superlikes_per_day',
                'value' => '1',
                'group' => 'premium',
                'label' => 'Суперлайков в день (бесплатно)',
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
            [
                'key' => 'youtube_url',
                'value' => 'https://youtube.com/@loveplanet',
                'group' => 'social',
                'label' => 'YouTube',
                'type' => 'url',
                'is_public' => true,
            ],

            // ===== Рассылки =====
            [
                'key' => 'broadcast_enabled',
                'value' => '1',
                'group' => 'broadcast',
                'label' => 'Включить рассылки',
                'type' => 'boolean',
                'is_public' => false,
            ],
            [
                'key' => 'broadcast_max_per_day',
                'value' => '3',
                'group' => 'broadcast',
                'label' => 'Максимум рассылок в день',
                'type' => 'number',
                'is_public' => false,
            ],
        ];

        $created = 0;
        $updated = 0;

        foreach ($settings as $setting) {
            $existing = Setting::where('key', $setting['key'])->first();

            if ($existing) {
                $existing->update($setting);
                $updated++;
            } else {
                Setting::create($setting);
                $created++;
            }
        }

        // ============================================
        // ВАЖНО: Сбрасываем кеш настроек!
        // ============================================
        Cache::forget('settings');
        $this->command->info('   🗑️ Кеш настроек очищен');

        $this->command->newLine();
        $this->command->info('✅ Настройки созданы/обновлены:');
        $this->command->info("   - Создано: {$created}");
        $this->command->info("   - Обновлено: {$updated}");
        $this->command->info("   - Всего: " . Setting::count());

        // ============================================
        // Показываем список групп
        // ============================================
        $groups = Setting::distinct()->pluck('group');
        $this->command->info('');
        $this->command->info('📂 Группы настроек:');
        foreach ($groups as $group) {
            $count = Setting::where('group', $group)->count();
            $this->command->info("   - {$group}: {$count} настройки");
        }
    }
}


// КАК ИСПОЛЬЗОВАТЬ НАСТРОЙКИ В КОДЕ
// php
// // Где угодно в коде
// use App\Models\Setting;

// // Получить значение
// $siteName = Setting::get('site_name');
// $maxPhotos = Setting::get('max_photos_per_user', 10); // с дефолтом

// // В Blade
// {{ Setting::get('site_name') }}

// // В контроллере
// if (Setting::get('moderation_auto_approve')) {
//     // Авто-одобрение включено
// }