<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\User;
use App\Models\UserBlock;

class ManageUserBlocksAction
{
    /**
     * Принудительно снять блокировку админом.
     */
    public function unblock(UserBlock $block, User $admin): void
    {
        $blockerId = $block->blocker_id;
        $blockedId = $block->blocked_id;
        $reason = $block->reason;

        $block->delete();

        $after = [
            'status' => 'unblocked', 
            'unblocked_by' => $admin->id,
            'context' => [
                'block_id' => $block->id,
                'blocker_id' => $blockerId,
                'blocked_id' => $blockedId,
                'original_reason' => $reason,
                'admin_id' => $admin->id
            ]
        ];

        // ФИКС: Передаем ID обоих юзеров, чтобы лог упал в таб "Логи" обоих
        $participants = array_filter([$blockerId, $blockedId]);

        // Логируем саму модель блокировки
        AdminLog::record('user_block.delete', $block, $admin, null, $after, participants: $participants);
    }
}