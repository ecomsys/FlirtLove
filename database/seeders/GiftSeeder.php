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

        $deletedCount = Gift::count();
        if ($deletedCount > 0) {
            Gift::query()->delete();
            $this->command->info("🗑️ Удалено {$deletedCount} старых подарков");
        }

        $gifts = [
            // === Романтика (romantic) ===
            ['name' => 'Красная роза', 'category' => GiftCategory::Romantic->value, 'price' => 50],
            ['name' => 'Плюшевый мишка', 'category' => GiftCategory::Romantic->value, 'price' => 100],
            ['name' => 'Сердце', 'category' => GiftCategory::Romantic->value, 'price' => 30],
            ['name' => 'Шоколадка', 'category' => GiftCategory::Romantic->value, 'price' => 40],
            ['name' => 'Валентинка', 'category' => GiftCategory::Romantic->value, 'price' => 20],
            ['name' => 'Букет цветов', 'category' => GiftCategory::Romantic->value, 'price' => 150],
            ['name' => 'Кольцо с бриллиантом', 'category' => GiftCategory::Romantic->value, 'price' => 1000],

            // === Авто (cars) ===
            ['name' => 'Мерседес', 'category' => GiftCategory::Cars->value, 'price' => 5000],
            ['name' => 'Спорткар', 'category' => GiftCategory::Cars->value, 'price' => 3000],
            ['name' => 'Яхта', 'category' => GiftCategory::Cars->value, 'price' => 10000],
            ['name' => 'Вертолет', 'category' => GiftCategory::Cars->value, 'price' => 15000],

            // === 18+ (adult) ===
            ['name' => 'Клубничка', 'category' => GiftCategory::Adult->value, 'price' => 80],
            ['name' => 'Шампанское', 'category' => GiftCategory::Adult->value, 'price' => 120],
            ['name' => 'Наручники', 'category' => GiftCategory::Adult->value, 'price' => 200],
            ['name' => 'Пломбир', 'category' => GiftCategory::Adult->value, 'price' => 60],

            // === Приколы (fun) ===
            ['name' => 'Ангел', 'category' => GiftCategory::Fun->value, 'price' => 90],
            ['name' => 'Чертенок', 'category' => GiftCategory::Fun->value, 'price' => 90],
            ['name' => 'Корона', 'category' => GiftCategory::Fun->value, 'price' => 500],
            ['name' => 'Кубок', 'category' => GiftCategory::Fun->value, 'price' => 300],

            // === Для него (male) ===
            ['name' => 'Крутые часы', 'category' => GiftCategory::Male->value, 'price' => 700],
            ['name' => 'Боксерская груша', 'category' => GiftCategory::Male->value, 'price' => 450],

            // === Для неё (female) ===
            ['name' => 'Помада', 'category' => GiftCategory::Female->value, 'price' => 150],
            ['name' => 'Туфли', 'category' => GiftCategory::Female->value, 'price' => 800],
        ];

        $bar = $this->command->getOutput()->createProgressBar(count($gifts));
        $createdCount = 0;

        foreach ($gifts as $gift) {
            Gift::create([
                'name' => $gift['name'],
                'slug' => Str::slug($gift['name']),
                'image_url' => '', // Никаких внешних ссылок, только наш медиа-склад!
                'price' => $gift['price'],
                'category' => $gift['category'],
                'is_active' => ($createdCount % 10 !== 9), 
            ]);

            $createdCount++;
            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine(2);

        // ============================================
        // СТАТИСТИКА
        // ============================================
        $this->command->info('✅ Создано подарков: ' . Gift::count());
        $this->command->info('');
        $this->command->info('📊 Статистика каталога:');
        
        $total = Gift::count();
        $active = Gift::where('is_active', true)->count();
        $inactive = Gift::where('is_active', false)->count();

        $this->command->info("   ┌─────────────────────┬──────────┐");
        $this->command->info("   │ Категория           │ Кол-во   │");
        $this->command->info("   ├─────────────────────┼──────────┤");
        $this->command->info("   │ Всего               │ {$total}        │");
        $this->command->info("   │ Активных            │ {$active}        │");
        $this->command->info("   │ Скрытых (inactive)  │ {$inactive}        │");
        $this->command->info("   ├─────────────────────┼──────────┤");

        // Динамически выводим статистику по всем категориям из Enum
        foreach (GiftCategory::cases() as $category) {
            $count = Gift::where('category', $category->value)->count();
            $label = mb_substr($category->label(), 0, 19);
            $label = str_pad($label, 19, ' ');
            $countStr = str_pad((string) $count, 8, ' ');
            $this->command->info("   │ {$label} │ {$countStr} │");
        }

        $this->command->info("   └─────────────────────┴──────────┘");
    }
}