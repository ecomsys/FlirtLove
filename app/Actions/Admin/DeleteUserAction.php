<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Notifications\UserDeleted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeleteUserAction
{
    /**
     * Удалить пользователя со всеми данными
     */
    public function execute(int $userId): array
    {
        $user = User::find($userId);
        
        if (!$user) {
            return ['success' => false, 'message' => 'Пользователь не найден'];
        }

        if ($user->is_admin) {
            return ['success' => false, 'message' => 'Нельзя удалить администратора'];
        }

        $userName = $user->name;

        DB::transaction(function () use ($user) {
            // Отправляем уведомление
            $user->notify(new UserDeleted());
            
            Log::info('Пользователь удален администратором', [
                'user_id' => $user->id,
                'email' => $user->email,
                'admin_id' => auth()->id(),
            ]);

            // Удаляем пользователя (каскадно удалит все связанные данные)
            $user->delete();
        });

        return [
            'success' => true,
            'message' => "Пользователь {$userName} удален"
        ];
    }
}