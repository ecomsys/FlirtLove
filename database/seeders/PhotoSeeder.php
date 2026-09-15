<?php

namespace Database\Seeders;

use App\Models\Photo;
use App\Models\User;
use Illuminate\Database\Seeder;

class PhotoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('📸 Создаем фото...');

        // Массивы для генерации реалистичных данных
        $titles = [
            'Лето на море',
            'Прогулка по парку',
            'Мой кот Барсик',
            'С друзьями на даче',
            'Вечерняя Москва',
            'Утренняя пробежка',
            'Закат на побережье',
            'На тренировке',
            'Отпуск 2023',
            'Новый год!',
            'Рабочий процесс',
            'За рулём',
            'В горах',
            'Выходной с семьёй'
        ];

        $descriptions = [
            'Отлично провели время, вспомнить есть что!',
            'Погода была просто идеальная для фотосессии.',
            'Мой любимец снова решил попозировать камере.',
            'Собрались всей командой, давно не виделись.',
            'Ночной город всегда выглядит загадочно.',
            'Стараюсь держать себя в форме каждый день.',
            'Никакой фильтр не передаст этой красоты.',
            'В отпуске время летит незаметно.',
            'Заканчиваю большой проект, чуть-чуть осталось.',
            'Подарили мне эту классную штуку, сразу сфоткал.',
            'Воздух в горах кристально чистый.',
            'Лучший выходной за долгое время!'
        ];

        $users = User::excludeAdmins()->get();

        foreach ($users as $user) {
            $photoCount = rand(1, 3);
            
            for ($p = 0; $p < $photoCount; $p++) {
                $imgId = rand(1, 70);
                
                // Даем название в 70% случаев, чтобы некоторые фото были без названия
                $hasTitle = rand(0, 100) > 30;
                
                Photo::create([
                    'user_id' => $user->id,
                    'path' => "https://i.pravatar.cc/800?img={$imgId}",
                    'path_original' => "https://i.pravatar.cc/800?img={$imgId}",
                    'path_large' => "https://i.pravatar.cc/800?img={$imgId}",
                    'path_medium' => "https://i.pravatar.cc/800?img={$imgId}",
                    'path_thumb' => "https://i.pravatar.cc/300?img={$imgId}",
                    'is_primary' => $p === 0,
                    'is_intimate' => false,
                    'status' => 'approved',
                    'title' => $hasTitle ? $titles[array_rand($titles)] : null,
                    'description' => $hasTitle ? $descriptions[array_rand($descriptions)] : null,
                ]);
            }
        }

        $this->command->info('   ✅ Создано фото: ' . Photo::count());
    }
}