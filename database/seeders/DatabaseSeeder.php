<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🚀 Запуск сидеров...');
        $this->command->info('');

        // ============================================
        // 1. ОЧИСТКА БАЗЫ ДАННЫХ
        // ============================================
        $this->command->info('🗑️ Очистка базы данных...');
        $this->cleanDatabase();
        $this->command->info('✅ База данных очищена!');
        $this->command->info('');

        // ============================================
        // 2. ОЧИСТКА ПАПОК С ФОТО
        // ============================================
        $this->command->info('📁 Очистка папок с фото...');
        $this->cleanPhotoDirectories();
        $this->command->info('✅ Папки очищены!');
        $this->command->info('');

        // ============================================
        // 3. ОЧИСТКА КЕША НАСТРОЕК
        // ============================================
        Cache::forget('settings');
        $this->command->info('🗑️ Кеш настроек очищен');
        $this->command->info('');

        // ============================================
        // 4. ЗАПУСК СИДЕРОВ
        // ============================================
        $this->command->info('📦 Запуск сидеров...');
        $this->command->info('');

        // ✅ Сначала создаем Админа
        $this->call(AdminSeeder::class);
        
        // Затем создаем обычных юзеров и их данные
        $this->call(UserSeeder::class);
        $this->call(AddLocationToUsersSeeder::class);
        $this->call(PhotoAlbumsSeeder::class);
        $this->call(PhotoCommentSeeder::class);
        $this->call(ReportSeeder::class);
        $this->call(SettingSeeder::class);
        $this->call(BroadcastSeeder::class);
        $this->call(SwipeSeeder::class);
        $this->call(ChatSeeder::class);        
        $this->call(TestLogsSeeder::class);

        $this->command->info('');
        $this->command->info('🎉 Все сидеры выполнены успешно!');
        $this->command->info('');

        // ============================================
        // 5. ИТОГОВАЯ СТАТИСТИКА
        // ============================================
        $this->command->info('📊 Итоговая статистика:');
        $this->command->info('   ┌─────────────────────────┬──────────┐');
        $this->command->info('   │ Сущность                │ Количество │');
        $this->command->info('   ├─────────────────────────┼──────────┤');
        
        $this->command->info('   │ 👑 Админов              │ ' . str_pad(\App\Models\User::where('is_admin', true)->count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 👤 Пользователей        │ ' . str_pad(\App\Models\User::where('is_admin', false)->count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 📸 Фото                 │ ' . str_pad(\App\Models\Photo::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 💬 Комментариев         │ ' . str_pad(\App\Models\PhotoComment::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 🚩 Жалоб                │ ' . str_pad(\App\Models\Report::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ ⚙️ Настроек             │ ' . str_pad(\App\Models\Setting::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 📨 Рассылок             │ ' . str_pad(\App\Models\Broadcast::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 👉 Свайпов              │ ' . str_pad(\App\Models\Swipe::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ ❤️ Матчей               │ ' . str_pad(\App\Models\UserMatch::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 💌 Чатов                │ ' . str_pad(\App\Models\Chat::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   │ 💬 Сообщений            │ ' . str_pad(\App\Models\Message::count(), 8, ' ', STR_PAD_LEFT) . ' │');
        $this->command->info('   └─────────────────────────┴──────────┘');
    }

    /**
     * Очистка базы данных (универсальная)
     */
    private function cleanDatabase(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // ✅ PostgreSQL: правильный порядок очистки
            DB::statement('TRUNCATE TABLE 
                messages,
                chat_participants,
                chats,
                photo_comments,
                photos,
                albums,
                reports,
                swipes,
                user_matches,
                broadcasts,
                user_preferences,
                user_profiles,
                settings,
                users
                RESTART IDENTITY CASCADE'
            );
        } else {
            // ✅ MySQL: отключаем проверку ключей
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            
            // Удаляем в правильном порядке (сначала дочерние, потом родительские)
            \App\Models\Message::query()->delete();
            \App\Models\ChatParticipant::query()->delete();
            \App\Models\Chat::query()->delete();
            \App\Models\PhotoComment::query()->delete();
            \App\Models\Photo::query()->delete();
            \App\Models\Album::query()->delete();
            \App\Models\Report::query()->delete();
            \App\Models\Swipe::query()->delete();
            \App\Models\UserMatch::query()->delete();
            \App\Models\Broadcast::query()->delete();
            \App\Models\UserPreference::query()->delete();
            \App\Models\UserProfile::query()->delete();
            \App\Models\Setting::query()->delete();
            \App\Models\User::query()->delete();
            
            // Сброс автоинкремента
            $tables = [
                'users', 'user_profiles', 'user_preferences', 'albums',
                'photos', 'photo_comments', 'reports', 'broadcasts',
                'settings', 'swipes', 'user_matches', 'chats',
                'messages', 'chat_participants'
            ];
            
            foreach ($tables as $table) {
                DB::statement("ALTER TABLE {$table} AUTO_INCREMENT = 1");
            }
            
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * Очистка папок с фото
     */
    private function cleanPhotoDirectories(): void
    {
        $directories = ['photos/pending', 'photos/approved'];
        
        foreach ($directories as $dir) {
            if (Storage::disk('public')->exists($dir)) {
                Storage::disk('public')->deleteDirectory($dir);
                $this->command->line("   ✅ Удалена папка: {$dir}");
            }
        }
        
        // Создаем папки заново
        foreach ($directories as $dir) {
            if (!Storage::disk('public')->exists($dir)) {
                Storage::disk('public')->makeDirectory($dir);
                $this->command->line("   ✅ Создана папка: {$dir}");
            }
        }
    }
}