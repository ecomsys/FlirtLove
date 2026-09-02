<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ManageUserRolesAction
{
    /**
     * Повысить юзера до сотрудника.
     */
    public function promote(User $user, string $newRole, User $admin): bool
    {
        if (!in_array($newRole, ['admin', 'moderator', 'support'])) {
            return false;
        }

        if ($user->isStaff()) {
            return false; // Уже сотрудник
        }

        $oldRole = $user->getOriginal('role');
        $user->update(['role' => $newRole]);
        $user->refresh();

        $after = [
            'role' => $newRole, 
            'promoted_by' => $admin->id,
            'context' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'old_role' => $oldRole,
                'new_role' => $newRole,
                'admin_id' => $admin->id
            ]
        ];

        AdminLog::record('user.role_change', $user, $admin, ['role' => $oldRole], $after, participants: [$user->id]);

        Log::info("Админ повысил пользователя", ['user_id' => $user->id, 'new_role' => $newRole, 'admin_id' => $admin->id]);

        return true;
    }

    /**
     * Понизить сотрудника до обычного юзера (с мгновенным логаутом).
     */
    public function demote(User $user, User $admin): bool
    {
        if (!$user->isStaff()) {
            return false;
        }

        $oldRole = $user->getOriginal('role');
        $user->update(['role' => 'user']);

        // Моментальный логаут (убиваем все сессии)
        $sessionsKilled = DB::table('sessions')->where('user_id', $user->id)->delete();

        $after = [
            'role' => 'user', 
            'demoted_by' => $admin->id,
            'context' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'old_role' => $oldRole,
                'new_role' => 'user',
                'admin_id' => $admin->id,
                'sessions_killed' => $sessionsKilled
            ]
        ];

        AdminLog::record('user.role_change', $user, $admin, ['role' => $oldRole], $after, participants: [$user->id]);

        Log::info("Админ разжаловал пользователя", ['user_id' => $user->id, 'admin_id' => $admin->id]);

        return true;
    }

    /**
     * Пакетное обновление ролей.
     */
    public function batchUpdate(array $selectedRoles, User $admin, array $founderIds): int
    {
        $updatedCount = 0;
        $changedUserIds = [];

        DB::transaction(function () use ($selectedRoles, $admin, $founderIds, &$updatedCount, &$changedUserIds) {
            foreach ($selectedRoles as $userId => $newRole) {
                if (!in_array($newRole, ['admin', 'moderator', 'support'])) continue;

                $user = User::find($userId);
                if (!$user || !$user->isStaff()) continue;

                // Иммунитет для владельцев и себя
                if (in_array($user->id, $founderIds) || $user->id === $admin->id) continue;

                if ($user->role === $newRole) continue;

                $oldRole = $user->getOriginal('role');
                $user->update(['role' => $newRole]);

                $after = [
                    'role' => $newRole, 
                    'changed_by' => $admin->id,
                    'context' => [
                        'user_id' => $user->id,
                        'user_name' => $user->name,
                        'old_role' => $oldRole,
                        'new_role' => $newRole,
                        'admin_id' => $admin->id
                    ]
                ];

                AdminLog::record('user.role_change', $user, $admin, ['role' => $oldRole], $after, participants: [$user->id]);

                $changedUserIds[] = $user->id;
                $updatedCount++;
            }
        });

        // Убиваем сессии у тех, чьи роли изменились
        if (!empty($changedUserIds)) {
            DB::table('sessions')->whereIn('user_id', $changedUserIds)->delete();
            Log::info("Массовое обновление ролей", ['admin_id' => $admin->id, 'affected_users' => $changedUserIds]);
        }

        return $updatedCount;
    }
}