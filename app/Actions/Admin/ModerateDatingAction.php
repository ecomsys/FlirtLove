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
        $before = [
            'type' => $swipe->getOriginal('type'), 
            'rewinded_at' => $swipe->getOriginal('rewinded_at')
        ];

        $unmatchedMatch = false;

        DB::transaction(function () use ($swipe, $admin, $before, &$unmatchedMatch) {
            if ($swipe->isPositive() && $swipe->user_id && $swipe->target_user_id) {
                $u1 = min($swipe->user_id, $swipe->target_user_id);
                $u2 = max($swipe->user_id, $swipe->target_user_id);
                
                $affected = UserMatch::where('user1_id', $u1)->where('user2_id', $u2)->update([
                    'status' => 'unmatched',
                    'unmatched_by' => $admin->id,
                    'unmatched_at' => now(),
                ]);

                if ($affected > 0) {
                    $unmatchedMatch = true;
                }
            }

            // ФИКС: Обернули ID юзеров в ключ context, чтобы calculateDiff их не вырезал
            $after = [
                'status' => 'destroyed', 
                'deleted_by' => $admin->id, 
                'deleted_at' => now()->toDateTimeString(),
                'match_unmatched' => $unmatchedMatch,
                'context' => [
                    'user_id' => $swipe->user_id,
                    'target_user_id' => $swipe->target_user_id
                ]
            ];

            $participants = array_filter([$swipe->user_id, $swipe->target_user_id]);

            AdminLog::record('swipe.destroy', $swipe, $admin, $before, $after, participants: $participants);

            $swipe->delete();
        });

        Cache::forget('dating_admin_stats');
    }

    /**
     * Разрыв мэтча администратором (Принудительный Unmatch).
     */
    public function destroyMatch(UserMatch $match, User $admin): void
    {
        $before = ['status' => $match->getOriginal('status')];

        DB::transaction(function () use ($match, $admin, $before) {
            $match->update([
                'status' => 'unmatched',
                'unmatched_by' => $admin->id,
                'unmatched_at' => now(),
            ]);

            $match->refresh();

            $after = [
                'status' => 'unmatched', 
                'unmatched_by' => $admin->id, 
                'unmatched_at' => now()->toDateTimeString(),
                // ФИКС: Обернули ID юзеров в ключ context
                'context' => [
                    'user1_id' => $match->user1_id, 
                    'user2_id' => $match->user2_id
                ]
            ];

            $participants = array_filter([$match->user1_id, $match->user2_id]);

            AdminLog::record('match.unmatch', $match, $admin, $before, $after, participants: $participants);
        });

        Cache::forget('dating_admin_stats');
    }

    /**
     * Восстановление мэтча администратором.
     */
    public function restoreMatch(UserMatch $match, User $admin): void
    {
        $before = ['status' => $match->getOriginal('status')];

        $match->update([
            'status' => 'active',
            'unmatched_by' => null,
            'unmatched_at' => null,
        ]);

        $match->refresh();

        $after = [
            'status' => 'active', 
            'restored_by' => $admin->id, 
            'restored_at' => now()->toDateTimeString(),
            // ФИКС: Обернули ID юзеров в ключ context
            'context' => [
                'user1_id' => $match->user1_id, 
                'user2_id' => $match->user2_id
            ]
        ];

        $participants = array_filter([$match->user1_id, $match->user2_id]);

        AdminLog::record('match.restore', $match, $admin, $before, $after, participants: $participants);
        
        Cache::forget('dating_admin_stats');
    }
}