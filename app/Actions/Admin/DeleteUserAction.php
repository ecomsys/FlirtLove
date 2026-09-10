<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\User;
use App\Notifications\UserDeleted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeleteUserAction
{
    public function execute(User $user, User $admin, ?string $reason = null): void
    {
        if ($user->isStaff()) {
            return; 
        }

        $before = [
            'status' => $user->getOriginal('status'), 
            'premium_expires_at' => $user->getOriginal('premium_expires_at'),
            'deleted_at' => $user->getOriginal('deleted_at')
        ];

        DB::transaction(function () use ($user, $admin, $before, $reason) {
            $user->update([
                'status' => 'deactivated',
                'premium_expires_at' => null,
            ]);
            
            $user->delete(); 

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

        // ФИКС: Отправляем уведомление ТОЛЬКО после успешного коммита транзакции!
        try {
            $user->notify(new UserDeleted());
        } catch (\Exception $e) {
            Log::error('Не удалось отправить уведомление об удалении аккаунта: ' . $e->getMessage());
        }
    }

    public function restore(User $user, User $admin): void
    {
        if (!$user->trashed()) return;

        $before = [
            'status' => $user->getOriginal('status'), 
            'deleted_at' => $user->getOriginal('deleted_at')
        ];

        $user->restore();
        $user->update(['status' => 'active']);
        // ФИКС: Убрали $user->refresh()

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