<?php

namespace Database\Seeders;

use App\Models\Album;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Database\Seeder;

// php artisan db:seed --class=PhotoAlbumsSeeder 

class PhotoAlbumsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('📸 Создаем альбомы и фото...');

        $users = User::where('is_admin', false)->get();

        $otherAlbumNames = [
            'Путешествия',
            'Друзья',
            'Семья',
            'Хобби',
            'Спорт',
            'Отдых',
            'Работа',
            'Природа',
        ];

        $totalPhotos = 0;
        $totalAlbums = 0;

        foreach ($users as $user) {
            // ✅ Всегда создаем альбом "Общие" как дефолтный
            $defaultAlbum = Album::create([
                'user_id' => $user->id,
                'name' => 'Общие',
                'description' => 'Основные фотографии пользователя',
                'is_default' => true,
            ]);
            $totalAlbums++;

            // Добавляем фото в дефолтный альбом (1-2 шт.)
            $defaultPhotoCount = rand(1, 2);
            for ($p = 0; $p < $defaultPhotoCount; $p++) {
                $imgId = rand(1, 70);
                $photo = Photo::create([
                    'user_id' => $user->id,
                    'album_id' => $defaultAlbum->id,
                    'path' => "https://i.pravatar.cc/800?img={$imgId}",
                    'path_original' => "https://i.pravatar.cc/800?img={$imgId}",
                    'path_large' => "https://i.pravatar.cc/800?img={$imgId}",
                    'path_medium' => "https://i.pravatar.cc/800?img={$imgId}",
                    'path_thumb' => "https://i.pravatar.cc/300?img={$imgId}",
                    'is_primary' => $p === 0, // первое фото в дефолтном альбоме - основное
                    'is_intimate' => false,
                    'status' => 'approved',
                ]);
                $totalPhotos++;
            }

            // Создаем дополнительные альбомы (1-3 шт.)
            $extraCount = rand(1, 3);
            shuffle($otherAlbumNames);
            $selectedNames = array_slice($otherAlbumNames, 0, $extraCount);

            foreach ($selectedNames as $name) {
                $album = Album::create([
                    'user_id' => $user->id,
                    'name' => $name,
                    'description' => "Альбом «{$name}» пользователя {$user->name}",
                    'is_default' => false,
                ]);
                $totalAlbums++;

                // Количество фото в альбоме (1-2)
                $photoCount = rand(1, 2);
                for ($p = 0; $p < $photoCount; $p++) {
                    $imgId = rand(1, 70);
                    Photo::create([
                        'user_id' => $user->id,
                        'album_id' => $album->id,
                        'path' => "https://i.pravatar.cc/800?img={$imgId}",
                        'path_original' => "https://i.pravatar.cc/800?img={$imgId}",
                        'path_large' => "https://i.pravatar.cc/800?img={$imgId}",
                        'path_medium' => "https://i.pravatar.cc/800?img={$imgId}",
                        'path_thumb' => "https://i.pravatar.cc/300?img={$imgId}",
                        'is_primary' => false,
                        'is_intimate' => false,
                        'status' => 'approved',
                    ]);
                    $totalPhotos++;
                }
            }
        }

        $this->command->info('   ✅ Создано альбомов: ' . $totalAlbums);
        $this->command->info('   ✅ Создано фото: ' . $totalPhotos);
    }
}