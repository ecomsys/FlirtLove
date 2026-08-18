<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\Swipe;
use App\Models\User;
use App\Models\UserMatch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ModerateDatingAction
{
    /**
     * Удаление свайпа администратором.
     * Если это был лайк/суперлайк — разрывает связанный мэтч (если он есть).
     */
    public function destroySwipe(Swipe $swipe, User $admin): void
    {
        $before = $swipe->only(['id', 'user_id', 'target_user_id', 'type', 'rewinded_at']);

        DB::transaction(function () use ($swipe, $admin, $before) {
            // Если удаляем позитивный свайп, нужно разорвать мэтч (если он был)
            if ($swipe->isPositive() && $swipe->user_id && $swipe->target_user_id) {
                $u1 = min($swipe->user_id, $swipe->target_user_id);
                $u2 = max($swipe->user_id, $swipe->target_user_id);
                
                // ИСПОЛЬЗУЕМ update, А НЕ delete! Сохраняем историю мэтча.
                UserMatch::where('user1_id', $u1)->where('user2_id', $u2)->update([
                    'status' => 'unmatched',
                    'unmatched_by' => $admin->id,
                    'unmatched_at' => now(),
                ]);
            }
            
            // Жестко удаляем сам свайп (админ захотел стереть факт оценки)
            $swipe->delete();
            AdminLog::record('swipe.destroy', $swipe, $admin, $before, null);
        });

        Cache::forget('dating_admin_stats');
    }

    /**
     * Разрыв мэтча администратором (Принудительный Unmatch).
     * НЕ удаляем запись из БД и НЕ удаляем свайпы!
     */
    public function destroyMatch(UserMatch $match, User $admin): void
    {
        $before = $match->only(['status', 'unmatched_by', 'unmatched_at']);

        DB::transaction(function () use ($match, $admin, $before) {
            // Просто меняем статус. Свайпы остаются в БД, чтобы юзеры не увидели друг друга в ленте снова.
            $match->update([
                'status' => 'unmatched',
                'unmatched_by' => $admin->id,
                'unmatched_at' => now(),
            ]);

            AdminLog::record('match.unmatch', $match, $admin, $before, $match->fresh()->only(['status', 'unmatched_by', 'unmatched_at']));
        });

        Cache::forget('dating_admin_stats');
    }

    /**
     * Восстановление мэтча администратором.
     */
    public function restoreMatch(UserMatch $match, User $admin): void
    {
        $before = $match->only(['status', 'unmatched_by', 'unmatched_at']);

        $match->update([
            'status' => 'active',
            'unmatched_by' => null,
            'unmatched_at' => null,
        ]);

        AdminLog::record('match.restore', $match, $admin, $before, $match->fresh()->only(['status', 'unmatched_by', 'unmatched_at']));
        Cache::forget('dating_admin_stats');
    }
}