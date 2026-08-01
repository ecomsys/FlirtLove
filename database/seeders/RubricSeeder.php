<?php

namespace Database\Seeders;

use App\Models\Rubric;
use Illuminate\Database\Seeder;

class RubricSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('📚 Создаем рубрики для дневников...');

        $rubrics = [
            ['name' => 'Мысли', 'slug' => 'thoughts', 'sort_order' => 1],
            ['name' => 'О любви', 'slug' => 'about-love', 'sort_order' => 2],
            ['name' => 'Поэзия', 'slug' => 'poetry', 'sort_order' => 3],
            ['name' => 'Путешествия', 'slug' => 'travel', 'sort_order' => 4],
            ['name' => 'Юмор', 'slug' => 'humor', 'sort_order' => 5],
            ['name' => 'Кулинария', 'slug' => 'cooking', 'sort_order' => 6],
            ['name' => 'Музыка', 'slug' => 'music', 'sort_order' => 7],
            ['name' => 'Дневник', 'slug' => 'diary', 'sort_order' => 8], // Дефолтная рубрика
        ];

        $created = 0;
        $updated = 0;

        foreach ($rubrics as $rubricData) {
            $rubricData['is_active'] = true; // Все по умолчанию активны

            $model = Rubric::updateOrCreate(
                ['slug' => $rubricData['slug']],
                $rubricData
            );

            if ($model->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        $this->command->info("   ✅ Рубрик создано: {$created}, обновлено: {$updated}");
    }
}