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
     * @param string $reason
     * @param string $type
     * @param bool $forceBan Если true — только банит (игнорирует разбан). Нужно для массовых действий.
     * @return array
     */
    public function execute(User $user, string $reason = 'Нарушение правил сервиса', string $type = 'permanent', bool $forceBan = false): array
    {
        if ($user->isStaff()) {
            return ['success' => false, 'message' => 'Нельзя забанить сотрудника (админа/модератора)'];
        }

        $isCurrentlyBanned = ($user->status === 'banned' || $user->status === 'shadowbanned');

        if ($forceBan) {
            return $this->ban($user, $reason, $type);
        }

        if ($isCurrentlyBanned) {
            return $this->unban($user);
        }

        return $this->ban($user, $reason, $type);
    }
    
    protected function ban(User $user, string $reason, string $type): array
    {
        // ФИКС: Используем getOriginal для надежности
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

        $user->refresh();

        $after = [
            'status' => $banData['status'],
            'ban_reason' => $banData['ban_reason'],
            'banned_until' => $banData['banned_until']?->toDateTimeString(),
            'ban_type' => $type,
            'banned_at' => now()->toDateTimeString(),
            // ФИКС: Добавлен context для истории
            'context' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'admin_id' => auth()->id(),
            ]
        ];

        // ФИКС: Динамически меняем название экшена для теневого бана
        $actionName = $type === 'shadow' ? 'user.shadowban' : 'user.ban';

        AdminLog::record(
            $actionName, // <--- Было жестко 'user.ban'
            $user, 
            auth()->user(), 
            $before, 
            $after, 
            participants: [$user->id]
        );
        
        if ($type !== 'shadow') {
            try {
                $user->notify(new UserBanned(true, "Ваш аккаунт заблокирован. Причина: {$reason}"));
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

    
    protected function unban(User $user): array
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
        
        $user->refresh();
        
        $after = [
            'status' => 'active',
            'unbanned_at' => now()->toDateTimeString(),
            'unbanned_by' => auth()->id(),
            // ФИКС: Добавлен context для истории
            'context' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'admin_id' => auth()->id(),
            ]
        ];
        
        AdminLog::record(
            'user.unban', 
            $user, 
            auth()->user(), 
            $before, 
            $after, 
            participants: [$user->id]
        );
        
        if ($before['status'] !== 'shadowbanned') {
            try {
                $user->notify(new UserBanned(false, "Ваш аккаунт разблокирован. Приносим извинения за неудобства."));
            } catch (\Exception $e) {
                Log::error('Ошибка отправки уведомления о разбане: ' . $e->getMessage());
            }
        }

        return ['success' => true, 'is_banned' => false, 'message' => "Пользователь {$user->name} разбанен"];
    }
}