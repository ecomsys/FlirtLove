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
        $this->command->info('📸 Создаём альбомы и фото (идемпотентно)...');

         $users = User::excludeAdmins()->get();

        $otherAlbumNames = [
            'Путешествия', 'Друзья', 'Семья', 'Хобби',
            'Спорт', 'Отдых', 'Работа', 'Природа',
        ];

        $totalPhotos = 0;
        $totalAlbumsCreated = 0;

        foreach ($users as $user) {
            // --- ДЕФОЛТНЫЙ АЛЬБОМ ---
            // Обновляем или создаём (если уже есть, то не дублируем)
            $defaultAlbum = Album::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'is_default' => true,
                ],
                [
                    'name' => 'Общие',
                    'description' => 'Основные фотографии пользователя',
                ]
            );

            // Считаем только если альбом был создан сейчас, а не обновлён
            if ($defaultAlbum->wasRecentlyCreated) {
                $totalAlbumsCreated++;
            }

            // Фото в дефолтный альбом (если их ещё нет)
            if ($defaultAlbum->photos()->count() === 0) {
                $defaultPhotoCount = rand(1, 2);
                for ($p = 0; $p < $defaultPhotoCount; $p++) {
                    $imgId = rand(1, 70);
                    Photo::create([
                        'user_id' => $user->id,
                        'album_id' => $defaultAlbum->id,
                        'path' => "https://i.pravatar.cc/800?img={$imgId}",
                        'path_original' => "https://i.pravatar.cc/800?img={$imgId}",
                        'path_large' => "https://i.pravatar.cc/800?img={$imgId}",
                        'path_medium' => "https://i.pravatar.cc/800?img={$imgId}",
                        'path_thumb' => "https://i.pravatar.cc/300?img={$imgId}",
                        'is_primary' => $p === 0,
                        'is_intimate' => false,
                        'status' => 'approved',
                    ]);
                    $totalPhotos++;
                }
            }

            // --- ДОПОЛНИТЕЛЬНЫЕ АЛЬБОМЫ ---
            $extraCount = rand(1, 3);
            shuffle($otherAlbumNames);
            $selectedNames = array_slice($otherAlbumNames, 0, $extraCount);

            foreach ($selectedNames as $name) {
                // Проверяем, нет ли уже такого альбома у пользователя
                $album = Album::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'name' => $name,
                    ],
                    [
                        'description' => "Альбом «{$name}» пользователя {$user->name}",
                        'is_default' => false,
                    ]
                );

                if ($album->wasRecentlyCreated) {
                    $totalAlbumsCreated++;
                }

                // Фото в дополнительный альбом (если ещё нет)
                if ($album->photos()->count() === 0) {
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
        }

        $this->command->info('   ✅ Создано новых альбомов: ' . $totalAlbumsCreated);
        $this->command->info('   ✅ Создано/добавлено фото: ' . $totalPhotos);
        $this->command->info('   🔁 Старые альбомы и фото сохранены, дубли не созданы.');
    }
}