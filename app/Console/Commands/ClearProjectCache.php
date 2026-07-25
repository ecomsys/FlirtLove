<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

#[Signature('cache:project-clear')]
#[Description('Очистка всех кешей проекта')]

class ClearProjectCache extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Начинаем очистку кеша...');

        try {
            // Очистка стандартных кешей Laravel
            Artisan::call('cache:clear');
            $this->info('✅ Cache очищен');

            Artisan::call('config:clear');
            $this->info('✅ Config очищен');

            Artisan::call('view:clear');
            $this->info('✅ View очищен');

            Artisan::call('route:clear');
            $this->info('✅ Route очищен');

            // Очистка opcache (если включен)
            if (function_exists('opcache_reset')) {
                opcache_reset();
                $this->info('✅ Opcache очищен');
            }

            // Удаление папки bootstrap/cache
            $bootstrapCachePath = base_path('bootstrap/cache');
            if (File::exists($bootstrapCachePath)) {
                File::cleanDirectory($bootstrapCachePath);
                $this->info('✅ Bootstrap cache очищен');
            }

            $this->info('✅ Все кеши успешно очищены!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Ошибка при очистке кеша: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
