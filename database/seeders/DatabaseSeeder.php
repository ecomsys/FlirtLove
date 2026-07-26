<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('  Запуск сидеров...');
        $this->command->info('');

        // ============================================
        //  ОЧИСТКА БАЗЫ ДАННЫХ
        // ============================================
        $this->command->info(' Очистка базы данных...');
        $this->cleanDatabase();
        $this->command->info('  База данных очищена!');
        $this->command->info('');

        // ============================================
        //  ОЧИСТКА ПАПОК С ФОТО
        // ============================================
        $this->command->info(' Очистка папок с фото...');
        $this->cleanPhotoDirectories();
        $this->command->info('  Папки очищены!');
        $this->command->info('');

        // ============================================
        //  ЗАПУСК СИДЕРОВ
        // ============================================
        $this->command->info(' Запуск сидеров...');
        $this->command->info('');

        $this->call(UserSeeder::class);
        $this->call(PhotoAlbumsSeeder::class);
        $this->call(PhotoCommentSeeder::class);
        $this->call(ReportSeeder::class);
        $this->call(SettingSeeder::class);
        $this->call(BroadcastSeeder::class);
        $this->call(AddLocationToUsersSeeder::class);
        $this->call(SwipeSeeder::class);

        $this->command->info('');
        $this->command->info('  Все сидеры выполнены успешно!');
        $this->command->info('  Итоговая статистика:');
        $this->command->info('   - Пользователей: ' . \App\Models\User::count());
        $this->command->info('   - Фото: ' . \App\Models\Photo::count());
        $this->command->info('   - Комментариев: ' . \App\Models\PhotoComment::count());
        $this->command->info('   - Жалоб: ' . \App\Models\Report::count());
        $this->command->info('   - Настроек: ' . \App\Models\Setting::count());
        $this->command->info('   - Уведомлений: ' . \App\Models\Broadcast::count());
        $this->command->info('   - Свайпов: ' . \App\Models\Swipe::count());
        $this->command->info('   - Метчей: ' . \App\Models\UserMatch::count());
    }

    /**
     * Очистка базы данных (универсальная)
     */
    private function cleanDatabase(): void
    {
        $driver = DB::connection()->getDriverName();

        // Отключаем проверку внешних ключей
        if ($driver === 'pgsql') {
            DB::statement('TRUNCATE TABLE photos RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE TABLE users RESTART IDENTITY CASCADE');            
            DB::statement('TRUNCATE TABLE photo_comments RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE TABLE reports RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE TABLE notifications RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE TABLE broadcasts RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE TABLE albums RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE TABLE settings RESTART IDENTITY CASCADE');    
            DB::statement('TRUNCATE TABLE swipes RESTART IDENTITY CASCADE');
            DB::statement('TRUNCATE TABLE user_matches RESTART IDENTITY CASCADE');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            
            \App\Models\Photo::truncate();
            \App\Models\User::truncate();
            \App\Models\PhotoComment::truncate();
            \App\Models\Report::truncate();
            \App\Models\Broadcast::truncate();           
            \App\Models\Album::truncate();
            \App\Models\Swipe::truncate();
            \App\Models\UserMatch::truncate();
            \App\Models\Setting::truncate();
            
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
                $this->command->info("   ✅ Удалена папка: {$dir}");
            }
        }
        
        foreach ($directories as $dir) {
            if (!Storage::disk('public')->exists($dir)) {
                Storage::disk('public')->makeDirectory($dir);
                $this->command->info("   ✅ Создана папка: {$dir}");
            }
        }
    }
}