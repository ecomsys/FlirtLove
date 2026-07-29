<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Notifications\UserBanned;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ToggleUserBanAction
{
    /**
     * Забанить или разбанить пользователя.
     * Идеально подходит для переиспользования в Livewire, API, Console Commands.
     *
     * @param User $user Модель пользователя
     * @param string $reason Причина бана (отображается в логах и уведомлении)
     * @return array ['success' => bool, 'is_banned' => bool, 'message' => string]
     */
    public function execute(User $user, string $reason = 'Нарушение правил сервиса'): array
    {
        // 1. Защита: админов банить нельзя
        if ($user->is_admin) {
            return [
                'success' => false, 
                'message' => 'Нельзя забанить администратора'
            ];
        }

        $willBeBanned = !$user->is_banned;

        // 2. Атомарная операция: всё или ничего
        DB::transaction(function () use ($user, $willBeBanned, $reason) {
            if ($willBeBanned) {
                // === ЛОГИКА БАНА ===
                $user->update([
                    'is_banned' => true,
                    'is_deactivated' => true,   // Замораживаем аккаунт (не сможет войти)
                    'is_verified' => false,     // Снимаем галочку верификации
                ]);
                
                // Отклоняем все фото, которые висят на модерации, чтобы не засорять очередь
                $user->photos()->where('status', 'pending')->update(['status' => 'rejected']);
                
                $notificationReason = "Ваш аккаунт заблокирован. Причина: {$reason}";
            } else {
                // === ЛОГИКА РАЗБАНА ===
                $user->update([
                    'is_banned' => false,
                    'is_deactivated' => false,
                ]);
                
                // (Опционально) Возвращаем отклоненные фото обратно на модерацию при разбане
                // Если не хочешь этого поведения, просто закомментируй следующую строку:
                $user->photos()->where('status', 'rejected')->update(['status' => 'pending']);
                
                $notificationReason = "Ваш аккаунт разблокирован. Приносим извинения за неудобства.";
            }

            // 3. Уведомляем пользователя
            $user->notify(new UserBanned($willBeBanned, $notificationReason));

            // 4. Логируем действие для безопасности
            Log::info('Статус бана пользователя изменен', [
                'user_id' => $user->id,
                'email' => $user->email,
                'is_banned' => $willBeBanned,
                'reason' => $reason,
                'admin_id' => auth()->id() ?? 'system',
            ]);
        });

        // 5. Возвращаем удобный для фронтенда ответ
        return [
            'success' => true,
            'is_banned' => $willBeBanned,
            'message' => $willBeBanned 
                ? "Пользователь {$user->name} успешно забанен" 
                : "Пользователь {$user->name} успешно разбанен"
        ];
    }
}