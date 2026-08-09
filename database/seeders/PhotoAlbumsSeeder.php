<?php

namespace Database\Seeders;

use App\Models\Album;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Database\Seeder;

class PhotoAlbumsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('📸 Создаём альбомы и фото (с реальными URL)...');

        // Фильтруем только обычных юзеров (role = 'user')
        $users = User::where('role', 'user')->get();

        if ($users->isEmpty()) {
            $this->command->warn('⚠️ Нет пользователей для создания альбомов.');
            return;
        }

        $otherAlbumNames = [
            'Путешествия', 'Друзья', 'Семья', 'Хобби',
            'Спорт', 'Отдых', 'Работа', 'Природа',
        ];

        $totalPhotos = 0;
        $totalAlbumsCreated = 0;

        foreach ($users as $user) {
            // --- ДЕФОЛТНЫЙ АЛЬБОМ ---
            $defaultAlbum = Album::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'is_default' => true,
                ],
                [
                    'name' => 'Общие',
                    'description' => 'Основные фотографии пользователя',
                    'is_private' => false, // Новое поле
                ]
            );

            if ($defaultAlbum->wasRecentlyCreated) {
                $totalAlbumsCreated++;
            }

            // Если в дефолтном альбоме еще нет фото
            if ($defaultAlbum->photos()->count() === 0) {
                $defaultPhotoCount = rand(2, 4);
                for ($p = 0; $p < $defaultPhotoCount; $p++) {
                    $imgId = rand(1, 70);
                    
                    Photo::create([
                        'user_id' => $user->id,
                        'album_id' => $defaultAlbum->id,
                        'type' => 'profile', // Новое поле (тип фото)
                        'path_original' => "https://i.pravatar.cc/800?img={$imgId}",
                        'path_large' => "https://i.pravatar.cc/800?img={$imgId}",
                        'path_medium' => "https://i.pravatar.cc/500?img={$imgId}",
                        'path_thumb' => "https://i.pravatar.cc/300?img={$imgId}",
                        'status' => 'approved',
                        'moderated_at' => now(), // Новое поле (дата модерации)
                        'is_primary' => $p === 0, // Первое фото делаем аватаркой
                        'is_intimate' => false,
                        'position' => $p,
                    ]);
                    $totalPhotos++;
                }
                
                // Обновляем счетчик фото в альбоме (наш хелпер из модели Album)
                $defaultAlbum->refreshPhotosCount();
            }

            // --- ДОПОЛНИТЕЛЬНЫЕ АЛЬБОМЫ ---
            $extraCount = rand(1, 3);
            shuffle($otherAlbumNames);
            $selectedNames = array_slice($otherAlbumNames, 0, $extraCount);

            foreach ($selectedNames as $name) {
                $album = Album::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'name' => $name,
                    ],
                    [
                        'description' => "Альбом «{$name}» пользователя {$user->name}",
                        'is_default' => false,
                        'is_private' => (bool) rand(0, 1), // Новое поле (рандомно делаем приватные)
                    ]
                );

                if ($album->wasRecentlyCreated) {
                    $totalAlbumsCreated++;
                }

                if ($album->photos()->count() === 0) {
                    $photoCount = rand(1, 3);
                    for ($p = 0; $p < $photoCount; $p++) {
                        $imgId = rand(1, 70);
                        
                        Photo::create([
                            'user_id' => $user->id,
                            'album_id' => $album->id,
                            'type' => 'profile', // Новое поле
                            'path_original' => "https://i.pravatar.cc/800?img={$imgId}",
                            'path_large' => "https://i.pravatar.cc/800?img={$imgId}",
                            'path_medium' => "https://i.pravatar.cc/500?img={$imgId}",
                            'path_thumb' => "https://i.pravatar.cc/300?img={$imgId}",
                            'status' => 'approved',
                            'moderated_at' => now(), // Новое поле
                            'is_primary' => false,
                            'is_intimate' => $album->is_private ? true : (bool) rand(0, 1), // В приватных альбомах фотки 18+
                            'position' => $p,
                        ]);
                        $totalPhotos++;
                    }
                    
                    // Обновляем счетчик фото в альбоме
                    $album->refreshPhotosCount();
                }
            }
        }

        $this->command->info('   ✅ Создано новых альбомов: ' . $totalAlbumsCreated);
        $this->command->info('   ✅ Создано фото: ' . $totalPhotos);
    }
}