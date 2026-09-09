<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserAuthLog;
use Illuminate\Database\Seeder;

class UserAuthLogsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🔐 Генерируем историю заходов...');

        UserAuthLog::truncate();

        $users = User::where('role', 'user')->get();
        $devices = [
            ['os' => 'Windows 11', 'type' => 'desktop', 'agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)...'],
            ['os' => 'macOS', 'type' => 'desktop', 'agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)...'],
            ['os' => 'Android 13', 'type' => 'mobile', 'agent' => 'Mozilla/5.0 (Linux; Android 13; Pixel 7)...'],
            ['os' => 'iOS 17', 'type' => 'mobile', 'agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)...'],
        ];
        // Стандартные IP + пара подозрительных
        $ips = ['192.168.1.1', '95.153.132.10', '178.66.50.22', '45.12.30.5 (Подозрительный)', '185.220.101.1 (TOR/Proxy)']; 

        foreach ($users as $user) {
            // Генерим от 2 до 5 входов для каждого юзера
            $logsCount = rand(2, 5);

            for ($i = 0; $i < $logsCount; $i++) {
                $device = $devices[array_rand($devices)];
                
                UserAuthLog::create([
                    'user_id' => $user->id,
                    'ip_address' => $ips[array_rand($ips)],
                    'user_agent' => $device['agent'],
                    'device_os' => $device['os'],
                    'device_type' => $device['type'],
                    'is_successful' => true,
                    // Разброс дат: от 1 до 30 дней назад
                    'created_at' => now()->subDays(rand(1, 30))->subHours(rand(1, 23)),
                    'updated_at' => now()->subDays(rand(1, 30))->subHours(rand(1, 23)),
                ]);
            }
        }

        $this->command->info('✅ История заходов сгенерирована.');
    }
}