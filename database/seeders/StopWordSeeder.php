<?php

namespace Database\Seeders;

use App\Models\StopWord;
use Illuminate\Database\Seeder;

class StopWordSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🛑 Заполняем базу стоп-слов и правил антифрода...');

        $words = [
            // ============================================
            // 1. МАТ И ОСКОРБЛЕНИЯ (Маскируем звездочками)
            // ============================================
            ['word' => 'хуй', 'category' => 'mat', 'action' => 'mask', 'replacement' => '***'],
            ['word' => 'пизда', 'category' => 'mat', 'action' => 'mask', 'replacement' => '***'],
            ['word' => 'ебан', 'category' => 'mat', 'action' => 'mask', 'replacement' => '***'],
            ['word' => 'бляд', 'category' => 'mat', 'action' => 'mask', 'replacement' => '***'],
            ['word' => 'гандон', 'category' => 'mat', 'action' => 'mask', 'replacement' => '***'],
            ['word' => 'мудак', 'category' => 'mat', 'action' => 'mask', 'replacement' => '***'],
            ['word' => 'уебок', 'category' => 'mat', 'action' => 'mask', 'replacement' => '***'],
            ['word' => 'пидор', 'category' => 'mat', 'action' => 'mask', 'replacement' => '***'],
            ['word' => 'черт', 'category' => 'mat', 'action' => 'mask', 'replacement' => '***'], // Мягкий мат, тоже маскируем

            // ============================================
            // 2. КОНТАКТЫ И ССЫЛКИ (H2H - Honeypot / Западня)
            // Мы НЕ блокируем их (action: alert). Скаммер думает, что сообщение ушло, 
            // а мы кидаем алерт в fraud_alerts для теневого бана.
            // ============================================
            ['word' => 'телеграм', 'category' => 'contacts', 'action' => 'alert'],
            ['word' => 'telegram', 'category' => 'contacts', 'action' => 'alert'],
            ['word' => ' тг ', 'category' => 'contacts', 'action' => 'alert'], // С пробелами, чтобы не резать слово "тг" внутри других слов
            ['word' => 'ватсап', 'category' => 'contacts', 'action' => 'alert'],
            ['word' => 'whatsapp', 'category' => 'contacts', 'action' => 'alert'],
            ['word' => 'вайбер', 'category' => 'contacts', 'action' => 'alert'],
            ['word' => 'viber', 'category' => 'contacts', 'action' => 'alert'],
            ['word' => 'напиши мне в', 'category' => 'contacts', 'action' => 'alert'],
            ['word' => 'пишите в', 'category' => 'contacts', 'action' => 'alert'],
            ['word' => '@', 'category' => 'contacts', 'action' => 'alert'], // Собачка перед никами
            ['word' => 't.me/', 'category' => 'contacts', 'action' => 'alert'], // Ссылка на ТГ

            // ============================================
            // 3. МОШЕННИЧЕСТВО И СПАМ (Тоже Honeypot)
            // ============================================
            ['word' => 'криптовалюта', 'category' => 'scam', 'action' => 'alert'],
            ['word' => 'биткоин', 'category' => 'scam', 'action' => 'alert'],
            ['word' => 'инвестици', 'category' => 'scam', 'action' => 'alert'],
            ['word' => 'заработок', 'category' => 'scam', 'action' => 'alert'],
            ['word' => 'казино', 'category' => 'scam', 'action' => 'alert'],
            ['word' => 'ставк', 'category' => 'scam', 'action' => 'alert'],
            ['word' => 'перевод', 'category' => 'scam', 'action' => 'alert'],
            ['word' => 'кинь деньги', 'category' => 'scam', 'action' => 'alert'],

            // ============================================
            // 4. ПРОСТИТУЦИЯ (Жесткий Reject - даже не сохраняем)
            // ============================================
            ['word' => 'досуг', 'category' => 'prostitution', 'action' => 'reject'],
            ['word' => 'индивидуалка', 'category' => 'prostitution', 'action' => 'reject'],
            ['word' => 'проститутк', 'category' => 'prostitution', 'action' => 'reject'],
            ['word' => 'пута', 'category' => 'prostitution', 'action' => 'reject'],
            ['word' => 'массаж с продолжением', 'category' => 'prostitution', 'action' => 'reject'],
            ['word' => 'интим за деньги', 'category' => 'prostitution', 'action' => 'reject'],

            // ============================================
            // 5. НАРКОТИКИ (Жесткий Reject)
            // ============================================
            ['word' => 'наркотик', 'category' => 'drugs', 'action' => 'reject'],
            ['word' => 'кокаин', 'category' => 'drugs', 'action' => 'reject'],
            ['word' => 'мефедрон', 'category' => 'drugs', 'action' => 'reject'],
            ['word' => 'скорость', 'category' => 'drugs', 'action' => 'reject'], // В контексте сленга
            ['word' => 'гашиш', 'category' => 'drugs', 'action' => 'reject'],
            ['word' => 'травк', 'category' => 'drugs', 'action' => 'reject'],

            // ============================================
            // 6. НЕЦЕНЗУРНАЯ ЛЕКСИКА (18+ термины, маскируем в публичных местах)
            // ============================================
            ['word' => 'хуйня', 'category' => 'mat', 'action' => 'mask', 'replacement' => '***ня'],
            ['word' => 'пиздец', 'category' => 'mat', 'action' => 'mask', 'replacement' => '***ец'],
            
            // ============================================
            // 7. РЕГУЛЯРНЫЕ ВЫРАЖЕНИЯ (Для телефонов и карт)
            // В Laravel мы будем preg_match, поэтому можно хранить паттерн
            // ============================================
            ['word' => '/\+7\s?\(?\d{3}\)?\s?\d{3}-?\d{2}-?\d{2}/', 'category' => 'contacts', 'action' => 'alert'], // Телефоны РФ
            ['word' => '/\d{16}/', 'category' => 'scam', 'action' => 'alert'], // Номер карты (16 цифр подряд)
        ];

        $created = 0;
        $updated = 0;

        $bar = $this->command->getOutput()->createProgressBar(count($words));

        foreach ($words as $wordData) {
            // Дефолтные значения, если не заданы
            $wordData['replacement'] = $wordData['replacement'] ?? '***';
            $wordData['is_active'] = $wordData['is_active'] ?? true;

            $model = StopWord::updateOrCreate(
                ['word' => $wordData['word']], // Уникальный ключ — само слово илиregex
                $wordData
            );

            if ($model->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine(2);

        // ============================================
        // СТАТИСТИКА
        // ============================================
        $stats = [
            'total' => StopWord::count(),
            'active' => StopWord::where('is_active', true)->count(),
            'mat' => StopWord::where('category', 'mat')->count(),
            'contacts' => StopWord::where('category', 'contacts')->count(),
            'scam' => StopWord::where('category', 'scam')->count(),
            'prostitution' => StopWord::where('category', 'prostitution')->count(),
            'drugs' => StopWord::where('category', 'drugs')->count(),
            'mask' => StopWord::where('action', 'mask')->count(),
            'reject' => StopWord::where('action', 'reject')->count(),
            'alert' => StopWord::where('action', 'alert')->count(),
        ];

        $this->command->info('✅ Стоп-слова созданы/обновлены:');
        $this->command->info("   - Создано: {$created}");
        $this->command->info("   - Обновлено: {$updated}");
        $this->command->info('');
        $this->command->info('📊 Статистика каталога:');
        $this->command->info("   ┌─────────────────────────┬──────────┐");
        $this->command->info("   │ Показатель              │ Кол-во   │");
        $this->command->info("   ├─────────────────────────┼──────────┤");
        $this->command->info("   │ Всего слов              │ {$stats['total']}        │");
        $this->command->info("   │ Активных                │ {$stats['active']}        │");
        $this->command->info("   ├─────────────────────────┼──────────┤");
        $this->command->info("   │ Мат (Маскировка)        │ {$stats['mat']}        │");
        $this->command->info("   │ Контакты (Западня)      │ {$stats['contacts']}        │");
        $this->command->info("   │ Мошенничество (Западня) │ {$stats['scam']}        │");
        $this->command->info("   │ Проституция (Блок)      │ {$stats['prostitution']}        │");
        $this->command->info("   │ Наркотики (Блок)        │ {$stats['drugs']}        │");
        $this->command->info("   ├─────────────────────────┼──────────┤");
        $this->command->info("   │ Действие: Mask          │ {$stats['mask']}        │");
        $this->command->info("   │ Действие: Reject        │ {$stats['reject']}        │");
        $this->command->info("   │ Действие: Alert 🔥      │ {$stats['alert']}        │");
        $this->command->info("   └─────────────────────────┴──────────┘");
        
        $this->command->warn('💡 Подсказка: Слова с action=alert пропускают текст, но кидают алерт в fraud_alerts!');
    }
}