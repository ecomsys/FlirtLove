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

        $users = User::where('is_admin', false)->get();

        foreach ($users as $user) {
            $photoCount = rand(1, 3);
            
            for ($p = 0; $p < $photoCount; $p++) {
                $imgId = rand(1, 70);
                
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
                ]);
            }
        }

        $this->command->info('   ✅ Создано фото: ' . Photo::count());
    }
}