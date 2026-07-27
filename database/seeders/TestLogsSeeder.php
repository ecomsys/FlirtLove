<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class TestLogsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🧪 Генерируем тестовые логи...');

        // ✅ Берем ID только обычных юзеров
        $userIds = User::excludeAdmins()->pluck('id')->toArray();

        if (empty($userIds)) {
            $this->command->warn('Нет пользователей для генерации логов.');
            return;
        }

        $levels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];
        $messages = [
            'Пользователь вошел в систему',
            'Ошибка при загрузке файла',
            'База данных недоступна',
            'Неверный пароль',
            'Успешная регистрация',
            'Подписка активирована',
            'Фото загружено',
            'Комментарий добавлен',
            'Пользователь забанен',
            'Жалоба решена',
            'Настройки обновлены',
            'Кеш очищен',
            'Ошибка отправки письма',
            'Новый пользователь зарегистрировался',
            'Профиль обновлен',
            'Уведомление отправлено',
        ];

        $bar = $this->command->getOutput()->createProgressBar(100);

        for ($i = 0; $i < 100; $i++) {
            $level = $levels[array_rand($levels)];
            $message = $messages[array_rand($messages)];
            // ✅ Выбираем случайный ID из массива обычных юзеров
            $userId = $userIds[array_rand($userIds)];
            $context = [
                'user_id' => $userId,
                'ip' => '192.168.' . rand(1, 255) . '.' . rand(1, 255),
                'iteration' => $i + 1,
                'random' => rand(1, 1000),
            ];

            Log::$level("{$message} (пользователь #{$userId})", $context);
            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();
        $this->command->info('✅ Сгенерировано 100 тестовых логов!');
        $this->command->info('📊 Уровни ошибок:');
        $this->command->info('   - debug, info, notice, warning, error, critical, alert, emergency');
    }
}