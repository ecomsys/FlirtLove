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
        // Сначала находим сессию, чтобы забрать IP для лога
        $session = DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $user->id) // Защита: чтобы не убили чужую сессию
            ->first();

        if (!$session) {
            return false;
        }

        DB::table('sessions')->where('id', $sessionId)->delete();

        $after = [
            'status' => 'killed', 
            'killed_by' => $admin->id,
            'killed_at' => now()->toDateTimeString(),
            'context' => [
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'ip_address' => $session->ip_address,
                'admin_id' => $admin->id
            ]
        ];

        AdminLog::record('user.session_killed', $user, $admin, null, $after, participants: [$user->id]);

        return true;
    }

    /**
     * Завершить ВСЕ сессии юзера (кнопка паники).
     */
    public function killAllSessions(User $user, User $admin): int
    {
        $count = DB::table('sessions')->where('user_id', $user->id)->count();

        if ($count === 0) {
            return 0;
        }

        DB::table('sessions')->where('user_id', $user->id)->delete();

        $after = [
            'status' => 'all_killed', 
            'killed_by' => $admin->id,
            'killed_at' => now()->toDateTimeString(),
            'context' => [
                'user_id' => $user->id,
                'admin_id' => $admin->id,
                'killed_count' => $count
            ]
        ];

        AdminLog::record('user.all_sessions_killed', $user, $admin, null, $after, participants: [$user->id]);

        return $count;
    }
}