<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RebuildDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:rebuild';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Полное пересоздание базы: migrate:fresh + world:install + db:seed';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('🚀 Начинаем полный пересбор базы данных...');
        $this->newLine();

        // 1. Миграции
        $this->info('1/4: Сброс и накатка миграций (migrate:fresh)...');
        $this->call('migrate:fresh');

        // 2. Гео-данные
        $this->info('2/4: Заливка стран и городов (world:install)...');
        // Используем Artisan::call с --no-interaction, чтобы команда сама отвечала "yes"
        Artisan::call('world:install', ['--no-interaction' => true], $this->getOutput());

        // 3. Сидеры
        $this->info('3/4: Запуск всех сидеров (db:seed)...');
        $this->call('db:seed');

        // 4. Запуск сервера
        $this->info('4/4: Запуск сервера');
        $this->call('serve');
        
    }
}