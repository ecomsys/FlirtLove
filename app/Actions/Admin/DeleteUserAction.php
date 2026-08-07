<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteUserAction
{
    /**
     * Мягкое удаление (деактивация) пользователя.
     * Отменяет подписку и пишет лог.
     */
    public function execute(User $user, User $admin): void
    {
        if ($user->isStaff()) {
            return; // Защита от удаления админов
        }

        $before = $user->only(['status', 'is_premium', 'premium_expires_at', 'deleted_at']);

        DB::transaction(function () use ($user, $admin, $before) {
            $user->update([
                'status' => 'deactivated',
                'is_premium' => false,
                'premium_expires_at' => null,
            ]);
            
            $user->delete(); // Soft Delete

            AdminLog::record('user.delete', $user, $admin, $before, ['status' => 'deactivated', 'deleted_at' => now()]);
        });
    }
}