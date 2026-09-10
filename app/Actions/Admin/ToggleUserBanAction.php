<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\User;
use App\Notifications\UserBanned;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ToggleUserBanAction
{
    /**
     * Забанить или разбанить пользователя.
     *
     * @param User $user
     * @param User $admin
     * @param string $reason
     * @param string $type
     * @param bool $forceBan
     * @return array
     */
    public function execute(User $user, User $admin, string $reason = 'Нарушение правил сервиса', string $type = 'permanent', bool $forceBan = false): array
    {
        if ($user->isStaff()) {
            return ['success' => false, 'message' => 'Нельзя забанить сотрудника (админа/модератора)'];
        }

        $isCurrentlyBanned = ($user->status === 'banned' || $user->status === 'shadowbanned');

        if ($forceBan) {
            return $this->ban($user, $admin, $reason, $type);
        }

        if ($isCurrentlyBanned) {
            return $this->unban($user, $admin);
        }

        return $this->ban($user, $admin, $reason, $type);
    }
    
    protected function ban(User $user, User $admin, string $reason, string $type): array
    {
        $before = [
            'status' => $user->getOriginal('status'), 
            'ban_reason' => $user->getOriginal('ban_reason'), 
            'banned_until' => $user->getOriginal('banned_until')
        ];

        $banData = match ($type) {
            'shadow' => [
                'status' => 'shadowbanned',
                'ban_reason' => $reason,
                'banned_until' => null,
            ],
            'temp' => [
                'status' => 'banned',
                'ban_reason' => $reason,
                'banned_until' => now()->addDays(3),
            ],
            default => [
                'status' => 'banned',
                'ban_reason' => $reason,
                'banned_until' => null,
            ],
        };

        DB::transaction(function () use ($user, $banData) {
            $user->update($banData);
            $user->photos()->where('status', 'pending')->update(['status' => 'rejected', 'reject_reason' => 'user_banned']);
        });

        // ФИКС: Убрали $user->refresh(), update() уже обновил атрибуты в памяти!

        $after = [
            'status' => $banData['status'],
            'ban_reason' => $banData['ban_reason'],
            'banned_until' => $banData['banned_until']?->toDateTimeString(),
            'ban_type' => $type,
            'banned_at' => now()->toDateTimeString(),
            'context' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'admin_id' => $admin->id,
            ]
        ];

        $actionName = $type === 'shadow' ? 'user.shadowban' : 'user.ban';

        AdminLog::record(
            $actionName, 
            $user, 
            $admin, 
            $before, 
            $after, 
            participants: [$user->id]
        );
        
        if ($type !== 'shadow') {
            try {
                // ФИКС: Передаем причину во второй аргумент уведомления
                $user->notify(new UserBanned(true, $reason));
            } catch (\Exception $e) {
                Log::error('Ошибка отправки уведомления о бане: ' . $e->getMessage());
            }
        }

        $banLabel = match($type) {
            'shadow' => 'подвергнут теневому бану',
            'temp' => 'забанен на 3 дня',
            default => 'забанен навсегда'
        };

        return ['success' => true, 'is_banned' => true, 'message' => "Пользователь {$user->name} {$banLabel}"];
    }

    
    protected function unban(User $user, User $admin): array
    {
        $before = [
            'status' => $user->getOriginal('status'), 
            'ban_reason' => $user->getOriginal('ban_reason'), 
            'banned_until' => $user->getOriginal('banned_until')
        ];
        
        $user->update([
            'status' => 'active',
            'ban_reason' => null,
            'banned_until' => null,
        ]);
        
        // ФИКС: Убрали $user->refresh()
        
        $after = [
            'status' => 'active',
            'unbanned_at' => now()->toDateTimeString(),
            'unbanned_by' => $admin->id,
            'context' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'admin_id' => $admin->id,
            ]
        ];
        
        AdminLog::record(
            'user.unban', 
            $user, 
            $admin, 
            $before, 
            $after, 
            participants: [$user->id]
        );
        
        if ($before['status'] !== 'shadowbanned') {
            try {
                // ФИКС: Передаем текст в уведомление
                $user->notify(new UserBanned(false, "Ваш аккаунт разблокирован. Приносим извинения за неудобства."));
            } catch (\Exception $e) {
                Log::error('Ошибка отправки уведомления о разбане: ' . $e->getMessage());
            }
        }

        return ['success' => true, 'is_banned' => false, 'message' => "Пользователь {$user->name} разбанен"];
    }
}