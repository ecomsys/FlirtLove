<?php

namespace Database\Seeders;

use App\Models\{AdminLog, BlogCategory, BlogPost, Broadcast, Chat, ChatParticipant, FraudAlert, Gift, Media, Message, Photo, PhotoComment, Album, Report, StopWord, SubscriptionPlan, Swipe, Transaction, User, UserGift, UserMatch, UserPreference, UserProfile, UserSubscription, Setting, Diary, DiaryComment, DiarySubscription, Rubric, SupportTemplate, GeoIPLocation};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🚀 Запуск полной генерации базы данных LovePlanet...');
        $this->command->info('');

        // ============================================
        // 1. ОЧИСТКА БАЗЫ ДАННЫХ
        // ============================================
        $this->command->info('🗑️ Очистка базы данных...');
        $this->cleanDatabase();
        $this->command->info('✅ База данных очищена!');
        $this->command->info('');

        // ============================================
        // 2. ОЧИСТКА ПАПОК С ФОТО И МЕДИА
        // ============================================
        $this->command->info('📁 Очистка папок с фото и медиа...');
        $this->cleanPhotoDirectories();
        $this->command->info('✅ Папки очищены!');
        $this->command->info('');

        // ============================================
        // 3. ОЧИСТКА КЭША НАСТРОЕК
        // ============================================
        Cache::forget('settings_all'); 
        Cache::forget('stop_words_active'); // Сбрасываем кэш стоп-слов  
        Cache::forget('geoip_blocked_iso_codes'); // Сбрасываем кэш гео-блокировок
        Cache::forget('geoip_feed_blocked_ids'); // Сбрасываем кэш гео-блокировок
        $this->command->info('🗑️ Кеш настроек и безопасности очищен');
        $this->command->info('');

        // ============================================
        // 4. ЗАПУСК СИДЕРОВ (СТРОГО ПО ЭТАПАМ)
        // ============================================
        $this->command->info('📦 Запуск сидеров...');
        $this->command->info('');

        // ЭТАП 1: БАЗА
        $this->command->info('📌 ЭТАП 1: Базовые сущности');
        $this->call([
            AdminSeeder::class,
            StaffSeeder::class,
            UserSeeder::class,
            SettingSeeder::class,
        ]);

        // ЭТАП 2: СПРАВОЧНИКИ И БЛОГ
        $this->command->info('📌 ЭТАП 2: Справочники и Блог');
        $this->call([
            SubscriptionPlanSeeder::class,
            GiftSeeder::class,
            StopWordSeeder::class,
            PageSeeder::class,  
            RubricSeeder::class,
            BlogSeeder::class,
        ]);

        // ЭТАП 3: КОНТЕНТ ЮЗЕРОВ
        $this->command->info('📌 ЭТАП 3: Контент');
        $this->call([
            AddLocationToUsersSeeder::class,
            PhotoAlbumsSeeder::class,
            PhotoCommentSeeder::class,
            ProfileViewSeeder::class,
            DiarySeeder::class,
        ]);

        // ЭТАП 4: СОЦИАЛЬНЫЙ ГРАФ
        $this->command->info('📌 ЭТАП 4: Взаимодействия');
        $this->call([
            SwipeSeeder::class,
            DiarySubscriptionSeeder::class,
            DiaryCommentSeeder::class,
        ]);

        // ЭТАП 5: КОММУНИКАЦИЯ
        $this->command->info('📌 ЭТАП 5: Коммуникация');
        $this->call([
            ChatSeeder::class,
        ]);

        // ЭТАП 6: МОНЕТИЗАЦИЯ
        $this->command->info('📌 ЭТАП 6: Монетизация');
        $this->call([          
            FinanceHistorySeeder::class,
            UserGiftSeeder::class,
        ]);

        // ЭТАП 7: БЕЗОПАСНОСТЬ И МОДЕРАЦИЯ
        $this->command->info('📌 ЭТАП 7: Безопасность');
        $this->call([
            ReportSeeder::class,
            FraudAlertSeeder::class,
            VerificationSeeder::class, 
            AdminLogSeeder::class,
            UserBlockSeeder::class,
        ]);

        // ЭТАП 8: МАРКЕТИНГ, ЛОГИ, ГЕО И ПОДДЕРЖКА
        $this->command->info('📌 ЭТАП 8: Рассылки, логи, гео и поддержка');
        $this->call([
            BroadcastSeeder::class,
            TestLogsSeeder::class,            
            SupportTemplateSeeder::class,
            GeoIPLocationsSeeder::class, // Если файла нет, просто удали эту строку
        ]);

        $this->command->info('');
        $this->command->info('🎉 Все сидеры выполнены успешно!');
        $this->command->info('');

        // ============================================
        // 5. ИТОГОВАЯ СТАТИСТИКА
        // ============================================
        $this->command->info('📊 Итоговая статистика базы:');
        $this->command->info('   ┌───────────────────────────┬────────────┐');
        $this->command->info('   │ Сущность                  │ Количество │');
        $this->command->info('   ├───────────────────────────┼────────────┤');
        
        $this->command->info('   │ 👑 Админов                │ ' . str_pad(User::where('role', 'admin')->count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 👤 Пользователей          │ ' . str_pad(User::where('role', 'user')->count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 📸 Фото                   │ ' . str_pad(Photo::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 🖼️ Медиа файлов           │ ' . str_pad(Media::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 💬 Комментариев (фото)    │ ' . str_pad(PhotoComment::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 📔 Дневников              │ ' . str_pad(Diary::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 💬 Комментариев (дневник) │ ' . str_pad(DiaryComment::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 📁 Рубрик                 │ ' . str_pad(Rubric::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 📰 Рубрик (блог)         │ ' . str_pad(BlogCategory::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 📝 Статей (блог)         │ ' . str_pad(BlogPost::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 👉 Свайпов                │ ' . str_pad(Swipe::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ ❤️ Матчей                 │ ' . str_pad(UserMatch::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 💌 Чатов                  │ ' . str_pad(Chat::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 💬 Сообщений              │ ' . str_pad(Message::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 🎁 Подарков (каталог)     │ ' . str_pad(Gift::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 💝 Подарков (отправлено)  │ ' . str_pad(UserGift::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 💳 Транзакций             │ ' . str_pad(Transaction::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 👑 Подписок               │ ' . str_pad(UserSubscription::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 🚩 Жалоб                  │ ' . str_pad(Report::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 🚨 Антифрод алертов       │ ' . str_pad(FraudAlert::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 🛑 Стоп-слов              │ ' . str_pad(StopWord::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 🕵️ Логов админа           │ ' . str_pad(AdminLog::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ ⚙️ Настроек               │ ' . str_pad(Setting::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 📨 Рассылок               │ ' . str_pad(Broadcast::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 💬 Шаблонов поддержки     │ ' . str_pad(SupportTemplate::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 🌍 Гео-локаций           │ ' . str_pad(GeoIPLocation::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   └───────────────────────────┴────────────┘');
        
        $this->command->info('');
        $this->command->info('🔑 Данные для входа:');
        $this->command->info('   Админ: admin@admin.com / 12121212');
        $this->command->info('   Юзер:  user1@test.com (до 10) / password');
    }

    /**
     * Очистка базы данных (универсальная для Postgres и MySQL)
     */
    private function cleanDatabase(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('TRUNCATE TABLE 
                admin_logs,
                broadcasts,
                blog_posts,     
                blog_categories, 
                chat_participants,
                chats,
                fraud_alerts,
                geoip_locations,
                media, 
                messages,
                photo_comments,
                photos,
                albums,
                reports,
                stop_words,
                support_templates,
                swipes,
                user_gifts,
                user_matches,
                user_preferences,
                user_profiles,
                user_subscriptions,
                transactions,
                subscription_plans,
                gifts,
                settings,
                users,
                diary_comments,
                diary_subscriptions,
                diaries,
                rubrics
                RESTART IDENTITY CASCADE'
            );
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            
            AdminLog::query()->delete();
            Broadcast::query()->delete();
            BlogPost::query()->delete();     
            BlogCategory::query()->delete(); 
            ChatParticipant::query()->delete();
            Message::query()->delete();
            Chat::query()->delete();
            FraudAlert::query()->delete();         
            GeoIPLocation::query()->delete(); // ФИКС: Очистка гео
            Media::query()->delete(); 
            PhotoComment::query()->delete();
            Photo::query()->delete();
            Album::query()->delete();
            Report::query()->delete();
            StopWord::query()->delete();
            SupportTemplate::query()->delete(); // ФИКС: Очистка шаблонов
            Swipe::query()->delete();
            UserGift::query()->delete();
            UserMatch::query()->delete();
            Transaction::query()->delete();
            UserSubscription::query()->delete();           
            SubscriptionPlan::query()->delete();
            Gift::query()->delete();
            UserPreference::query()->delete();
            UserProfile::query()->delete();
            Setting::query()->delete();
            User::query()->delete();
            
            DiaryComment::query()->delete();
            DiarySubscription::query()->delete();
            Diary::query()->delete();
            Rubric::query()->delete();
            
            $tables = [
                'users', 'user_profiles', 'user_preferences', 'albums', 'photos', 
                'photo_comments', 'reports', 'stop_words', 'support_templates', 'swipes', 'user_matches', 
                'chats', 'chat_participants', 'messages', 'gifts', 'user_gifts', 
                'subscription_plans', 'user_subscriptions', 'transactions', 'fraud_alerts', 
                'admin_logs', 'broadcasts', 'settings', 'media', 'geoip_locations',
                'blog_categories', 
                'blog_posts',      
                'diary_comments', 'diary_subscriptions', 'diaries', 'rubrics'               
            ];
            
            foreach ($tables as $table) {
                DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
            }
            
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * Очистка папок с фото и медиа
     */
    private function cleanPhotoDirectories(): void
    {
        $directories = ['photos/pending', 'photos/approved', 'photos/profile', 'media']; 
        
        foreach ($directories as $dir) {
            if (Storage::disk('public')->exists($dir)) {
                Storage::disk('public')->deleteDirectory($dir);
                $this->command->line("   ✅ Удалена папка: {$dir}");
            }
        }
        
        foreach ($directories as $dir) {
            if (!Storage::disk('public')->exists($dir)) {
                Storage::disk('public')->makeDirectory($dir);
                $this->command->line("   ✅ Создана папка: {$dir}");
            }
        }
    }
}