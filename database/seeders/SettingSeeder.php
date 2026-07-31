<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

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
                'description' => 'Отображается в шапке сайта и во вкладке браузера',
                'type' => 'text',
                'is_public' => true,
            ],
            [
                'key' => 'site_description',
                'value' => 'Сайт знакомств для серьезных отношений',
                'group' => 'general',
                'label' => 'Описание сайта',
                'description' => 'Meta description для SEO',
                'type' => 'text',
                'is_public' => true,
            ],
            [
                'key' => 'contact_email',
                'value' => 'support@loveplanet.ru',
                'group' => 'general',
                'label' => 'Email поддержки',
                'description' => 'Адрес, на который пользователи пишут жалобы',
                'type' => 'email',
                'is_public' => true,
            ],
            [
                'key' => 'default_locale',
                'value' => 'ru',
                'group' => 'general',
                'label' => 'Язык по умолчанию',
                'description' => 'Код локали (ru, en)',
                'type' => 'text',
                'is_public' => true,
            ],

            // ===== Лимиты (Дейтинг) =====
            [
                'key' => 'likes_per_day_free',
                'value' => '30',
                'group' => 'limits',
                'label' => 'Лайков в день (Бесплатно)',
                'description' => 'Сколько свайпов вправо может делать юзер без VIP',
                'type' => 'number',
                'is_public' => true,
            ],
            [
                'key' => 'likes_per_day_premium',
                'value' => '100',
                'group' => 'limits',
                'label' => 'Лайков в день (VIP)',
                'description' => 'Сколько свайпов вправо может делать юзер с VIP',
                'type' => 'number',
                'is_public' => true,
            ],
            [
                'key' => 'free_superlikes_per_day',
                'value' => '1',
                'group' => 'limits',
                'label' => 'Суперлайков в день (Бесплатно)',
                'description' => 'Лимит суперлайков для обычных юзеров',
                'type' => 'number',
                'is_public' => false,
            ],
            [
                'key' => 'premium_superlikes_per_day',
                'value' => '5',
                'group' => 'limits',
                'label' => 'Суперлайков в день (VIP)',
                'description' => 'Лимит суперлайков для VIP-юзеров',
                'type' => 'number',
                'is_public' => false,
            ],

            // ===== Модерация =====
            [
                'key' => 'max_photos_per_user',
                'value' => '10',
                'group' => 'moderation',
                'label' => 'Максимум фото на пользователя',
                'description' => 'Лимит фотографий в профиле',
                'type' => 'number',
                'is_public' => false,
            ],
            [
                'key' => 'moderation_auto_approve',
                'value' => '0',
                'group' => 'moderation',
                'label' => 'Авто-одобрение фото',
                'description' => 'Публиковать фото сразу без проверки модератором (0 - нет, 1 - да)',
                'type' => 'boolean',
                'is_public' => false,
            ],
            [
                'key' => 'require_moderation_for_new_users',
                'value' => '1',
                'group' => 'moderation',
                'label' => 'Модерация для новых пользователей',
                'description' => 'Отправлять ли анкеты новичков на ручную проверку',
                'type' => 'boolean',
                'is_public' => false,
            ],

            // ===== Безопасность =====
            [
                'key' => 'min_password_length',
                'value' => '8',
                'group' => 'security',
                'label' => 'Минимальная длина пароля',
                'description' => 'При регистрации и смене пароля',
                'type' => 'number',
                'is_public' => false,
            ],
            [
                'key' => 'max_login_attempts',
                'value' => '5',
                'group' => 'security',
                'label' => 'Максимум попыток входа',
                'description' => 'Лимит неверных вводов пароля до блокировки (Throttle)',
                'type' => 'number',
                'is_public' => false,
            ],

            // ===== Социальные сети =====
            [
                'key' => 'telegram_url',
                'value' => 'https://t.me/loveplanet',
                'group' => 'social',
                'label' => 'Telegram',
                'description' => 'Ссылка на официальный канал',
                'type' => 'url',
                'is_public' => true,
            ],
            [
                'key' => 'instagram_url',
                'value' => 'https://instagram.com/loveplanet',
                'group' => 'social',
                'label' => 'Instagram',
                'description' => 'Ссылка на официальный профиль',
                'type' => 'url',
                'is_public' => true,
            ],

            // ===== Рассылки =====
            [
                'key' => 'broadcast_max_per_day',
                'value' => '3',
                'group' => 'broadcast',
                'label' => 'Максимум рассылок в день',
                'description' => 'Защита от спама юзеров от администрации',
                'type' => 'number',
                'is_public' => false,
            ],
        ];

        $created = 0;
        $updated = 0;

        foreach ($settings as $setting) {
            // Паттерн updateOrCreate: создает если нет, обновляет если есть
            $model = Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );

            if ($model->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        // В нашей модели Setting событие saved() сбрасывает кэш автоматически!
        // Но для 100% надежности вызовем явно.
        Setting::flushCache();
        $this->command->info('   🗑️ Кеш настроек сброшен');

        $this->command->newLine();
        $this->command->info('✅ Настройки созданы/обновлены:');
        $this->command->info("   - Создано: {$created}");
        $this->command->info("   - Обновлено: {$updated}");
        $this->command->info("   - Всего: " . Setting::count());

        // Показываем список групп
        $groups = Setting::distinct()->pluck('group');
        $this->command->info('');
        $this->command->info('📂 Группы настроек:');
        foreach ($groups as $group) {
            $count = Setting::where('group', $group)->count();
            $this->command->info("   - {$group}: {$count} шт.");
        }
    }
}

// ============================================
// КАК ИСПОЛЬЗОВАТЬ НАСТРОЙКИ В КОДЕ
// ============================================
// use App\Models\Setting;
//
// // Получить значение (БЕЗ ЗАПРОСОВ В БД, берется из кэша)
// $siteName = Setting::get('site_name');
// $maxPhotos = Setting::get('max_photos_per_user', 10); // с дефолтом
//
// // В Blade
// {{ Setting::get('site_name') }}
//
// // В Middleware (проверка лимитов)
// $limit = auth()->user()->is_premium 
//     ? Setting::get('likes_per_day_premium') 
//     : Setting::get('likes_per_day_free');