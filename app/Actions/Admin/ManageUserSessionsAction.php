<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ManageUserSessionsAction
{
    /**
     * Завершить конкретную сессию юзера.
     */
    public function killSession(User $user, string $sessionId, User $admin): bool
    {
        $session = DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $user->id) // Защита: чтобы не убили чужую сессию
            ->first();

        if (!$session) {
            return false;
        }

        // ФИКС: Транзакция гарантирует, что сессия убита И лог записан. Иначе откат.
        DB::transaction(function () use ($session, $user, $admin) {
            
            DB::table('sessions')->where('id', $session->id)->delete();

            $after = [
                'status' => 'killed', 
                'killed_by' => $admin->id,
                'killed_at' => now()->toDateTimeString(),
                'context' => [
                    'user_id' => $user->id,
                    'session_id' => $session->id,
                    'ip_address' => $session->ip_address,
                    'admin_id' => $admin->id
                ]
            ];

            AdminLog::record('user.session_killed', $user, $admin, null, $after, participants: [$user->id]);
        });

        return true;
    }

    /**
     * Завершить ВСЕ сессии юзера (кнопка паники).
     */
    public function killAllSessions(User $user, User $admin): int
    {
        $deletedCount = 0;

        // ФИКС: Обернули в транзакцию
        DB::transaction(function () use ($user, $admin, &$deletedCount) {
            
            // ФИКС: delete() возвращает количество удаленных строк! 
            // Нам не нужен отдельный запрос COUNT(*), который к тому же подвержен Race Condition.
            $deletedCount = DB::table('sessions')->where('user_id', $user->id)->delete();

            if ($deletedCount > 0) {
                $after = [
                    'status' => 'all_killed', 
                    'killed_by' => $admin->id,
                    'killed_at' => now()->toDateTimeString(),
                    'context' => [
                        'user_id' => $user->id,
                        'admin_id' => $admin->id,
                        'killed_count' => $deletedCount
                    ]
                ];

                AdminLog::record('user.all_sessions_killed', $user, $admin, null, $after, participants: [$user->id]);
            }
        });

        return $deletedCount;
    }
}