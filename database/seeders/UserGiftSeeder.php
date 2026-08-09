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
            UserGift::query()->forceDelete();
            $this->command->info("🗑️ Удалено {$deletedCount} старых записей о подарках");
        }

        $messages = [
            'Симпатия от чистого сердца!', 'С небольшим намеком...', 'Просто так!',
            'За прекрасный вечер!', 'Ты мне очень нравишься 🥰', 'Давай познакомимся?',
            'Это между нами...', 'Никому не говори!', null,
        ];

        $totalToCreate = 50;
        $bar = $this->command->getOutput()->createProgressBar($totalToCreate);

        for ($i = 0; $i < $totalToCreate; $i++) {
            $sender = $users->random();
            $receiver = $users->where('id', '!=', $sender->id)->random();
            $gift = $gifts->random();

            // 70% подарков прочитаны, 30% - нет (используем mt_rand без приведения к bool)
            $isRead = mt_rand(1, 100) > 30;
            $readAt = $isRead ? now()->subDays(rand(0, 5)) : null;

            // 30% подарков приватные. Если категория интимная - 100% приватный.
            $isPrivate = $gift->category === 'intimate' ? true : (mt_rand(1, 100) <= 30);

            $userGift = UserGift::create([
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
                'gift_id' => $gift->id,
                
                'snapshot_name' => $gift->name,
                'snapshot_image_url' => $gift->image_url,
                'snapshot_price' => $gift->price,
                
                'message' => $messages[array_rand($messages)],
                'is_private' => $isPrivate,
                'is_read' => $isRead,
                'read_at' => $readAt,
                'created_at' => now()->subDays(rand(0, 20)),
                'updated_at' => now()->subDays(rand(0, 5)),
            ]);

            // 15% подарков "удалены" получателем (исправлена логика рандома)
            if (mt_rand(1, 100) <= 15) {
                $userGift->delete();
            }

            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine(2);

        // ============================================
        // СТАТИСТИКА
        // ============================================
        // Везде используем withTrashed, чтобы посчитать даже мягко удаленные
        $stats = [
            'total' => UserGift::withTrashed()->count(),
            'unread' => UserGift::withTrashed()->where('is_read', false)->count(),
            'private' => UserGift::withTrashed()->where('is_private', true)->count(),
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