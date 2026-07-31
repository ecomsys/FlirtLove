<?php

namespace Database\Seeders;

use App\Models\Gift;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GiftSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🎁 Заполняем каталог подарков...');

        // Очищаем старые подарки (без truncate, чтобы не сбрасывать ID)
        $deletedCount = Gift::count();
        if ($deletedCount > 0) {
            Gift::query()->delete();
            $this->command->info("🗑️ Удалено {$deletedCount} старых подарков");
        }

        $gifts = [
            // === Романтика (Дешевые и средние) ===
            ['name' => 'Красная роза', 'category' => 'romantic', 'price' => 50],
            ['name' => 'Плюшевый мишка', 'category' => 'romantic', 'price' => 100],
            ['name' => 'Сердце', 'category' => 'romantic', 'price' => 30],
            ['name' => 'Шоколадка', 'category' => 'romantic', 'price' => 40],
            ['name' => 'Валентинка', 'category' => 'romantic', 'price' => 20],
            ['name' => 'Букет цветов', 'category' => 'romantic', 'price' => 150],
            ['name' => 'Кольцо с бриллиантом', 'category' => 'romantic', 'price' => 1000],

            // === Авто и Статус (Дорогие) ===
            ['name' => 'Мерседес', 'category' => 'cars', 'price' => 5000],
            ['name' => 'Спорткар', 'category' => 'cars', 'price' => 3000],
            ['name' => 'Яхта', 'category' => 'cars', 'price' => 10000],
            ['name' => 'Вертолет', 'category' => 'cars', 'price' => 15000],

            // === 18+ (Приватные) ===
            ['name' => 'Клубничка', 'category' => 'intimate', 'price' => 80],
            ['name' => 'Шампанское', 'category' => 'intimate', 'price' => 120],
            ['name' => 'Наручники', 'category' => 'intimate', 'price' => 200],
            ['name' => 'Пломбир', 'category' => 'intimate', 'price' => 60],

            // === Символы и Эмоции ===
            ['name' => 'Ангел', 'category' => 'symbols', 'price' => 90],
            ['name' => 'Чертенок', 'category' => 'symbols', 'price' => 90],
            ['name' => 'Корона', 'category' => 'symbols', 'price' => 500],
            ['name' => 'Кубок', 'category' => 'symbols', 'price' => 300],
        ];

        $bar = $this->command->getOutput()->createProgressBar(count($gifts));
        $createdCount = 0;

        foreach ($gifts as $gift) {
            Gift::create([
                'name' => $gift['name'],
                'slug' => Str::slug($gift['name']),
                // Используем placeholder для тестов (на проде будут реальные пути или CDN)
                'image_url' => "https://via.placeholder.com/150x150/FF69B4/FFFFFF?text=" . urlencode($gift['name']),
                'price' => $gift['price'],
                'category' => $gift['category'],
                // Делаем каждый 10-й подарок неактивным (скрытым из магазина)
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
        $stats = [
            'total' => Gift::count(),
            'active' => Gift::where('is_active', true)->count(),
            'inactive' => Gift::where('is_active', false)->count(),
            'romantic' => Gift::where('category', 'romantic')->count(),
            'cars' => Gift::where('category', 'cars')->count(),
            'intimate' => Gift::where('category', 'intimate')->count(),
            'symbols' => Gift::where('category', 'symbols')->count(),
        ];

        $this->command->info('✅ Создано подарков: ' . $stats['total']);
        $this->command->info('');
        $this->command->info('📊 Статистика каталога:');
        $this->command->info("   ┌─────────────────────┬──────────┐");
        $this->command->info("   │ Категория           │ Кол-во   │");
        $this->command->info("   ├─────────────────────┼──────────┤");
        $this->command->info("   │ Всего               │ {$stats['total']}        │");
        $this->command->info("   │ Активных            │ {$stats['active']}        │");
        $this->command->info("   │ Скрытых (inactive)  │ {$stats['inactive']}        │");
        $this->command->info("   ├─────────────────────┼──────────┤");
        $this->command->info("   │ Романтика           │ {$stats['romantic']}        │");
        $this->command->info("   │ Авто и Статус       │ {$stats['cars']}        │");
        $this->command->info("   │ 18+                 │ {$stats['intimate']}        │");
        $this->command->info("   │ Символы             │ {$stats['symbols']}        │");
        $this->command->info("   └─────────────────────┴──────────┘");
    }
}