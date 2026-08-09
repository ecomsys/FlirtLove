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

        // Если флаг forceBan включен (вызвано из модалки) — мы ПРИНУДИТЕЛЬНО баним, 
        // даже если юзер уже в бане. Это перезапишет старый тип/причину бана на новый.
        if ($forceBan) {
            return $this->ban($user, $reason, $type);
        }

        // Если forceBan выключен (вызвано кнопкой "Разбанить" из списка) 
        // и юзер реально забанен — снимаем бан.
        if ($isCurrentlyBanned) {
            return $this->unban($user);
        }

        // Если forceBan выключен и юзер активен — баним (защита от случайных вызовов)
        return $this->ban($user, $reason, $type);
    }
    
    
    protected function ban(User $user, string $reason, string $type): array
    {
        $before = $user->only(['status', 'ban_reason', 'banned_until', 'is_verified']);

        $banData = match ($type) {
            'shadow' => [
                'status' => 'shadowbanned',
                'ban_reason' => $reason,
                'banned_until' => null, // Теневой бан обычно бессрочный (снимается вручную)
            ],
            'temp' => [
                'status' => 'banned',
                'ban_reason' => $reason,
                'banned_until' => now()->addDays(3), // Временный бан на 3 дня
            ],
            default => [ // 'permanent'
                'status' => 'banned',
                'ban_reason' => $reason,
                'banned_until' => null, // Навсегда
            ],
        };

        DB::transaction(function () use ($user, $banData) {
            $user->update($banData);
            // Снимаем с модерации все его фото
            $user->photos()->where('status', 'pending')->update(['status' => 'rejected', 'reject_reason' => 'user_banned']);
        });

        $user->refresh();
        $after = $user->only(['status', 'ban_reason', 'banned_until', 'is_verified']);

        AdminLog::record('user.ban', $user, auth()->user(), $before, $after);
        
        // ФИКС: Отправляем уведомление ТОЛЬКО если это не теневой бан
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
        $before = $user->only(['status', 'ban_reason', 'banned_until', 'is_verified']);
        
        $user->update([
            'status' => 'active',
            'ban_reason' => null,
            'banned_until' => null,
        ]);
        
        $user->refresh();
        $after = $user->only(['status', 'ban_reason', 'banned_until', 'is_verified']);
        
        AdminLog::record('user.unban', $user, auth()->user(), $before, $after);
        
        // ФИКС: Если снимаем теневой бан — молчим. Юзер не должен знать, что был в бане.
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