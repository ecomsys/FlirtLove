<?php

namespace Database\Seeders;

use App\Models\Gift;
use App\Models\User;
use App\Models\UserGift;
use Illuminate\Database\Seeder;

class UserGiftSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('💝 Генерируем историю отправленных подарков...');

        $users = User::where('role', 'user')->get();
        $gifts = Gift::where('is_active', true)->get();

        if ($users->count() < 2) {
            $this->command->warn('⚠️ Нужно минимум 2 пользователя для отправки подарков!');
            return;
        }

        if ($gifts->isEmpty()) {
            $this->command->warn('⚠️ Нет активных подарков! Сначала прогоните GiftSeeder.');
            return;
        }

        $deletedCount = UserGift::count();
        if ($deletedCount > 0) {
            UserGift::query()->delete();
            $this->command->info("🗑️ Удалено {$deletedCount} старых записей о подарках");
        }

        $messages = [
            'Симпатия от чистого сердца!', 'С небольшим намеком...', ' просто так!',
            'За прекрасный вечер!', 'Ты мне очень нравишься 🥰', 'Давай познакомимся?',
            null, // Иногда без сообщения
        ];

        $totalToCreate = 40; // Создадим 40 случайных фактов отправки
        $bar = $this->command->getOutput()->createProgressBar($totalToCreate);
        $createdCount = 0;

        for ($i = 0; $i < $totalToCreate; $i++) {
            $sender = $users->random();
            $receiver = $users->where('id', '!=', $sender->id)->random();
            $gift = $gifts->random();

            // 70% подарков прочитаны, 30% - нет (для теста счетчика непрочитанных)
            $isRead = (bool) rand(0, 100) >= 30;
            $readAt = $isRead ? now()->subDays(rand(0, 5)) : null;

            // 20% подарков приватные (18+ или скрытые от других)
            $isPrivate = $gift->category === 'intimate' ? true : (bool) rand(0, 4) === 0;

            // 10% подарков "удалены" получателем (Soft Delete для теста корзины)
            $deletedAt = (bool) rand(0, 9) === 0 ? now()->subDays(rand(1, 3)) : null;

            UserGift::create([
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
                'gift_id' => $gift->id,
                
                // === СНЭПШОТ (Копируем данные каталога на момент отправки) ===
                'snapshot_name' => $gift->name,
                'snapshot_image_url' => $gift->image_url,
                'snapshot_price' => $gift->price,
                
                'message' => $messages[array_rand($messages)],
                'is_private' => $isPrivate,
                'is_read' => $isRead,
                'read_at' => $readAt,
                'created_at' => now()->subDays(rand(0, 20)),
                'updated_at' => now()->subDays(rand(0, 5)),
                'deleted_at' => $deletedAt, // Soft Delete
            ]);

            $createdCount++;
            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine(2);

        // ============================================
        // СТАТИСТИКА
        // ============================================
        // Используем withTrashed, чтобы посчитать даже удаленные
        $stats = [
            'total' => UserGift::withTrashed()->count(),
            'unread' => UserGift::where('is_read', false)->count(),
            'private' => UserGift::where('is_private', true)->count(),
            'deleted' => UserGift::onlyTrashed()->count(),
            'total_credits_spent' => UserGift::withTrashed()->sum('snapshot_price'),
        ];

        $this->command->info('✅ Создано фактов отправки подарков: ' . $stats['total']);
        $this->command->info('');
        $this->command->info('📊 Статистика подарков:');
        $this->command->info("   ┌─────────────────────────┬──────────┐");
        $this->command->info("   │ Показатель             │ Значение │");
        $this->command->info("   ├─────────────────────────┼──────────┤");
        $this->command->info("   │ Всего отправлено        │ {$stats['total']}        │");
        $this->command->info("   │ Непрочитанных           │ {$stats['unread']}        │");
        $this->command->info("   │ Приватных (18+)         │ {$stats['private']}        │");
        $this->command->info("   │ Удалено юзерами         │ {$stats['deleted']}        │");
        $this->command->info("   │ Потрачено кредитов      │ {$stats['total_credits_spent']}      │");
        $this->command->info("   └─────────────────────────┴──────────┘");
    }
}