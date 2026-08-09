<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

#[Signature('cache:project-clear')]
#[Description('Очистка всех кешей проекта безопасным способом')]
class ClearProjectCache extends Command
{
    public function handle(): int
    {
        $this->info('Начинаем очистку кеша...');

        try {
            // 1. Очистка всех стандартных кешей Laravel (config, routes, views, events, compiled)
            // Это безопасная команда, которая удаляет только сгенерированные кеш-файлы.
            Artisan::call('optimize:clear');
            $this->info('Кеши Laravel (optimize) очищены');

            // 2. Очистка application cache (Redis/БД), т.к. optimize:clear не всегда чистит его
            Artisan::call('cache:clear');
            $this->info('Application cache (Redis/DB) очищен');

            // 3. Очистка кеша скомпилированных файлов (иногда зависает после обновления композера)
            Artisan::call('clear-compiled');
            $this->info('Скомпилированные файлы очищены');

            // 4. Очистка opcache (если включен на сервере)
            if (function_exists('opcache_reset')) {
                opcache_reset();
                $this->info('OPcache очищен');
            }

            $this->info('Все кеши успешно очищены!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Ошибка при очистке кеша: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}