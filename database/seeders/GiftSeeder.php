<?php

namespace Database\Seeders;

use App\Enums\GiftCategory;
use App\Models\Gift;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GiftSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🎁 Заполняем каталог подарков...');

        $gifts = [
            // === Романтика (romantic) ===
            ['name' => 'Красная роза', 'category' => GiftCategory::Romantic->value, 'price' => 50, 'type' => 'main'],
            ['name' => 'Плюшевый мишка', 'category' => GiftCategory::Romantic->value, 'price' => 100, 'type' => 'main'],
            ['name' => 'Сердце', 'category' => GiftCategory::Romantic->value, 'price' => 30, 'type' => 'main'],
            ['name' => 'Шоколадка', 'category' => GiftCategory::Romantic->value, 'price' => 40, 'type' => 'main'],
            ['name' => 'Валентинка', 'category' => GiftCategory::Romantic->value, 'price' => 20, 'type' => 'main'],
            ['name' => 'Букет цветов', 'category' => GiftCategory::Romantic->value, 'price' => 150, 'type' => 'main'],
            ['name' => 'Кольцо с бриллиантом', 'category' => GiftCategory::Romantic->value, 'price' => 1000, 'type' => 'premium'],

            // === Авто (cars) ===
            ['name' => 'Мерседес', 'category' => GiftCategory::Cars->value, 'price' => 5000, 'type' => 'premium'],
            ['name' => 'Спорткар', 'category' => GiftCategory::Cars->value, 'price' => 3000, 'type' => 'premium'],
            ['name' => 'Яхта', 'category' => GiftCategory::Cars->value, 'price' => 10000, 'type' => 'premium'],
            ['name' => 'Вертолет', 'category' => GiftCategory::Cars->value, 'price' => 15000, 'type' => 'premium'],

            // === 18+ (adult) ===
            ['name' => 'Клубничка', 'category' => GiftCategory::Adult->value, 'price' => 80, 'type' => 'main'],
            ['name' => 'Шампанское', 'category' => GiftCategory::Adult->value, 'price' => 120, 'type' => 'main'],
            ['name' => 'Наручники', 'category' => GiftCategory::Adult->value, 'price' => 200, 'type' => 'main'],
            ['name' => 'Пломбир', 'category' => GiftCategory::Adult->value, 'price' => 60, 'type' => 'main'],

            // === Приколы (fun) ===
            ['name' => 'Ангел', 'category' => GiftCategory::Fun->value, 'price' => 90, 'type' => 'main'],
            ['name' => 'Чертенок', 'category' => GiftCategory::Fun->value, 'price' => 90, 'type' => 'main'],
            ['name' => 'Корона', 'category' => GiftCategory::Fun->value, 'price' => 500, 'type' => 'premium'],
            ['name' => 'Кубок', 'category' => GiftCategory::Fun->value, 'price' => 300, 'type' => 'main'],

            // === Для него (male) ===
            ['name' => 'Крутые часы', 'category' => GiftCategory::Male->value, 'price' => 700, 'type' => 'premium'],
            ['name' => 'Боксерская груша', 'category' => GiftCategory::Male->value, 'price' => 450, 'type' => 'main'],

            // === Для неё (female) ===
            ['name' => 'Помада', 'category' => GiftCategory::Female->value, 'price' => 150, 'type' => 'main'],
            ['name' => 'Туфли', 'category' => GiftCategory::Female->value, 'price' => 800, 'type' => 'premium'],

            // === Сервисы (services) ===
            ['name' => 'Поднятие в поиске', 'category' => GiftCategory::Fun->value, 'price' => 100, 'type' => 'services'],
            ['name' => 'Супер-лайк', 'category' => GiftCategory::Fun->value, 'price' => 50, 'type' => 'services'],
            ['name' => 'Инкогнито на 1 час', 'category' => GiftCategory::Fun->value, 'price' => 70, 'type' => 'services'],
        ];

        $bar = $this->command->getOutput()->createProgressBar(count($gifts));
        $createdCount = 0;

        foreach ($gifts as $gift) {
            $slug = Str::slug($gift['name']);

            // ИСПОЛЬЗУЕМ updateOrCreate для защиты от External Key при повторном запуске
            Gift::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $gift['name'],
                    'image_url' => '', 
                    'price' => $gift['price'],
                    'category' => $gift['category'],
                    'type' => $gift['type'], // <--- ДОБАВИЛИ TYPE СЮДА
                    'is_active' => ($createdCount % 10 !== 9), 
                ]
            );

            $createdCount++;
            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine(2);

        // ============================================
        // СТАТИСТИКА
        // ============================================
        $total = Gift::count();
        $active = Gift::where('is_active', true)->count();
        $inactive = Gift::where('is_active', false)->count();

        $this->command->info('✅ Создано подарков: ' . $total);
        $this->command->info('');
        $this->command->info('📊 Статистика каталога:');
        
        $this->command->info("   ┌─────────────────────┬──────────┐");
        $this->command->info("   │ Категория           │ Кол-во   │");
        $this->command->info("   ├─────────────────────┼──────────┤");
        $this->command->info("   │ Всего               │ " . str_pad($total, 8, ' ') . " │");
        $this->command->info("   │ Активных            │ " . str_pad($active, 8, ' ') . " │");
        $this->command->info("   │ Скрытых (inactive)  │ " . str_pad($inactive, 8, ' ') . " │");
        $this->command->info("   ├─────────────────────┼──────────┤");

        foreach (GiftCategory::cases() as $category) {
            $count = Gift::where('category', $category->value)->count();
            $label = str_pad(mb_substr($category->label(), 0, 19), 19, ' ');
            $countStr = str_pad((string) $count, 8, ' ');
            $this->command->info("   │ {$label} │ {$countStr} │");
        }

        $this->command->info("   └─────────────────────┴──────────┘");
    }
}