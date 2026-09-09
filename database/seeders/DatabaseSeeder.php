<?php

namespace Database\Seeders;

use App\Models\{
    AdminLog, UserAuthLog, UserEvent, 
    BlogCategory, BlogPost, Broadcast, Chat, ChatParticipant, FraudAlert, 
    Gift, Media, Message, Photo, PhotoComment, Album, Report, StopWord, 
    SubscriptionPlan, Swipe, Transaction, User, UserGift, UserMatch, 
    UserPreference, UserProfile, UserSubscription, Setting, Diary, DiaryComment, 
    DiarySubscription, DiaryRubric, SupportTemplate, GeoIPLocation, UserCard, 
    PromoCode, PromoCodeUsage, Page, DiaryLike, DiaryCommentLike, ProfileView
};
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
        Cache::forget('stop_words_active');  
        Cache::forget('geoip_blocked_iso_codes'); 
        Cache::forget('geoip_feed_blocked_ids'); 
        $this->command->info('🗑️ Кеш настроек и безопасности очищен');
        $this->command->info('');

        // ============================================
        // 4. ГЕО-ДАННЫЕ 
        // ============================================
        $this->command->info('🌍 ВАЖНО: Если таблицы стран пусты, сначала выполните команду: php artisan world:install');
        $this->command->info('');

        // ============================================
        // 5. ЗАПУСК СИДЕРОВ (СТРОГО ПО ЭТАПАМ)
        // ============================================
        $this->command->info('📦 Запуск сидеров...');
        $this->command->info('');

        // ЭТАП 1: БАЗА И ГЕО-БЛОКИРОВКИ
        $this->command->info('📌 ЭТАП 1: Базовые сущности и Гео');
        $this->call([
            GeoIPLocationsSeeder::class, 
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
            DiaryRubricSeeder::class,
            BlogSeeder::class,
            PromoCodeSeeder::class,
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
            UserCardsSeeder::class,
            PromoCodeUsagesSeeder::class,
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

        // ЭТАП 8: МАРКЕТИНГ, ЛОГИ И ПОДДЕРЖКА
        $this->command->info('📌 ЭТАП 8: Рассылки, логи и поддержка');
        $this->call([
            BroadcastSeeder::class,
            TestLogsSeeder::class,            
            SupportTemplateSeeder::class,
            UserAuthLogsSeeder::class,   
            UserEventsSeeder::class, // Убедись, что внутри этого сидера он тоже переименован в UserEvent!
        ]);

        $this->command->info('');
        $this->command->info('🎉 Все сидеры выполнены успешно!');
        $this->command->info('');

        // ============================================
        // 6. ИТОГОВАЯ СТАТИСТИКА
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
        $this->command->info('   │ 📁 Рубрик                 │ ' . str_pad(DiaryRubric::count(), 8, ' ', STR_PAD_LEFT) . ' │');
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
            // Обновили список таблиц, добавили недостающие (pages, profile_views, diary_likes и т.д.)
            DB::statement('TRUNCATE TABLE 
                admin_logs,
                albums,
                blog_categories,
                blog_posts,
                broadcasts,
                chat_participants,
                chats,
                diary_comment_likes,
                diary_comments,
                diary_likes,
                diary_rubrics,
                diary_subscriptions,
                diaries,
                fraud_alerts,
                geoip_locations,
                gifts,
                media,
                messages,
                pages,
                photo_comments,
                photos,
                profile_views,
                promo_code_usages,
                promo_codes,
                reports,
                stop_words,
                subscription_plans,
                support_templates,
                swipes,
                transactions,
                user_auth_logs,
                user_balances,
                user_blocks,
                user_cards,
                user_events,
                user_favorites,
                user_gifts,
                user_matches,
                user_preferences,
                user_profiles,
                user_subscriptions,
                settings,
                users
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
            GeoIPLocation::query()->delete(); 
            Media::query()->delete(); 
            PhotoComment::query()->delete();
            Photo::query()->delete();
            Album::query()->delete();
            Report::query()->delete();
            StopWord::query()->delete();
            SupportTemplate::query()->delete(); 
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
            
            DiaryCommentLike::query()->delete();
            DiaryComment::query()->delete();
            DiaryLike::query()->delete();
            DiarySubscription::query()->delete();
            Diary::query()->delete();
            DiaryRubric::query()->delete();

            UserCard::query()->delete();       
            PromoCode::query()->delete();      
            PromoCodeUsage::query()->delete(); 
            ProfileView::query()->delete();
            Page::query()->delete();

            UserAuthLog::query()->delete();       
            UserEvent::query()->delete(); // Заменили UserActivity на UserEvent
            
            $tables = [
                'users', 'user_profiles', 'user_preferences', 'user_balances', 'albums', 'photos', 
                'photo_comments', 'reports', 'stop_words', 'support_templates', 'swipes', 'user_matches', 
                'chats', 'chat_participants', 'messages', 'gifts', 'user_gifts', 
                'subscription_plans', 'user_subscriptions', 'transactions', 'fraud_alerts', 
                'admin_logs', 'broadcasts', 'settings', 'media', 'geoip_locations',
                'blog_categories', 'blog_posts', 'pages',
                'diary_comment_likes', 'diary_comments', 'diary_likes', 'diary_subscriptions', 'diaries', 'diary_rubrics',
                'user_cards', 'promo_codes', 'promo_code_usages', 'profile_views', 
                'user_auth_logs', 'user_events', 'user_blocks', 'user_favorites'
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