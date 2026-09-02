<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteUserAction
{
    /**
     * Мягкое удаление (деактивация) пользователя.
     *
     * @param User $user
     * @param User $admin
     * @param string|null $reason Причина удаления (для логов)
     */
    public function execute(User $user, User $admin, ?string $reason = null): void
    {
        if ($user->isStaff()) {
            return; // Защита от удаления админов
        }

        $before = [
            'status' => $user->getOriginal('status'), 
            'is_premium' => $user->getOriginal('is_premium'), 
            'deleted_at' => $user->getOriginal('deleted_at')
        ];

        DB::transaction(function () use ($user, $admin, $before, $reason) {
            $user->update([
                'status' => 'deactivated',
                'is_premium' => false,
                'premium_expires_at' => null,
            ]);
            
            $user->delete(); // Soft Delete

            $after = [
                'status' => 'deactivated', 
                'deleted_at' => now()->toDateTimeString(),
                'context' => [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'admin_id' => $admin->id,
                    'reason' => $reason ?: 'Причина не указана'
                ]
            ];
            
            AdminLog::record('user.delete', $user, $admin, $before, $after, participants: [$user->id]);
        });
    }

        /**
     * Восстановление деактивированного пользователя.
     */
    public function restore(User $user, User $admin): void
    {
        if (!$user->trashed()) return;

        $before = [
            'status' => $user->getOriginal('status'), 
            'deleted_at' => $user->getOriginal('deleted_at')
        ];

        $user->restore();
        $user->update(['status' => 'active']);
        $user->refresh();

        $after = [
            'status' => 'active', 
            'restored_at' => now()->toDateTimeString(),
            'context' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'admin_id' => $admin->id
            ]
        ];

        AdminLog::record('user.restore', $user, $admin, $before, $after, participants: [$user->id]);
    }
}