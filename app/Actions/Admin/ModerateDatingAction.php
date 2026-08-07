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
     * Если это был лайк/суперлайк — удаляет связанный матч.
     */
    public function destroySwipe(Swipe $swipe, User $admin): void
    {
        $before = $swipe->only(['id', 'user_id', 'target_user_id', 'type', 'rewinded_at']);

        DB::transaction(function () use ($swipe, $admin, $before) {
            // Если удаляем позитивный свайп, удаляем и мэтч (если он есть)
            if ($swipe->isPositive() && $swipe->user_id && $swipe->target_user_id) {
                $u1 = min($swipe->user_id, $swipe->target_user_id);
                $u2 = max($swipe->user_id, $swipe->target_user_id);
                
                UserMatch::where('user1_id', $u1)->where('user2_id', $u2)->delete();
            }
            
            $swipe->delete();
            AdminLog::record('swipe.destroy', $swipe, $admin, $before, null);
        });

        Cache::forget('dating_admin_stats');
    }

      /**
     * Разрыв мэтча администратором (Принудительный Unmatch).
     * НЕ удаляем запись из БД, а меняем статус для истории и безопасности.
     */
    public function destroyMatch(UserMatch $match, User $admin): void
    {
        $before = $match->only(['status', 'unmatched_by', 'unmatched_at']);

        DB::transaction(function () use ($match, $admin, $before) {
            // Удаляем свайпы в обе стороны (чтобы они не висели в базе без смысла)
            Swipe::where(function ($q) use ($match) {
                $q->where('user_id', $match->user1_id)->where('target_user_id', $match->user2_id);
            })->orWhere(function ($q) use ($match) {
                $q->where('user_id', $match->user2_id)->where('target_user_id', $match->user1_id);
            })->delete();

            // РААЗРЫВ МЭТЧА (А НЕ УДАЛЕНИЕ)
            $match->update([
                'status' => 'unmatched',
                'unmatched_by' => $admin->id, // Фиксируем, что разрыв инициировал админ
                'unmatched_at' => now(),
            ]);

            AdminLog::record('match.unmatch', $match, $admin, $before, $match->fresh()->only(['status', 'unmatched_by', 'unmatched_at']));
        });

        Cache::forget('dating_admin_stats');
    }

    /**
     * Восстановление мэтча администратором.
     * Возвращает статус в 'active' и очищает поля разрыва.
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