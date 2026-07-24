<?php

namespace App\Console\Commands;


use App\Models\User;
use App\Models\Album;
use App\Models\Photo;
use Illuminate\Support\Facades\DB;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('photos:migrate-to-albums')]
#[Description('Перенос существующих фото в альбомы по умолчанию')]


// # 1. Запускаем миграции
// php artisan migrate

// # 2. Переносим старые фото в альбомы
// php artisan photos:migrate-to-albums

class MigratePhotosToAlbums extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
     {
        $this->info('Начинаем миграцию фото в альбомы...');

        $users = User::whereHas('photos')->get();

        foreach ($users as $user) {
            $defaultAlbum = Album::getDefaultForUser($user);

            $count = Photo::where('user_id', $user->id)
                ->whereNull('album_id')
                ->update(['album_id' => $defaultAlbum->id]);

            $this->line("Пользователь {$user->name}: перенесено {$count} фото в альбом '{$defaultAlbum->name}'");
        }

        $this->info('Миграция завершена!');
    }
}
