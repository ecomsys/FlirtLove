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

        $before = $user->only(['status', 'is_premium', 'premium_expires_at', 'deleted_at']);

        DB::transaction(function () use ($user, $admin, $before, $reason) {
            $user->update([
                'status' => 'deactivated',
                'is_premium' => false,
                'premium_expires_at' => null,
                // Можно добавить поле deletion_reason в таблицу users, 
                // но для экономии полей достаточно хранить это только в AdminLog
            ]);
            
            $user->delete(); // Soft Delete

            // Записываем причину в лог
            $after = [
                'status' => 'deactivated', 
                'deleted_at' => now()->toDateTimeString(),
                'reason' => $reason ?: 'Причина не указана'
            ];
            
            AdminLog::record('user.delete', $user, $admin, $before, $after);
        });
    }
}