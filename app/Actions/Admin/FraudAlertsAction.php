<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\FraudAlert;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Chat;

class FraudAlertsAction
{
    /**
     * Подтвердить нарушение и забанить юзера (с использованием ToggleUserBanAction).
     *
     * @param int $alertId
     * @param string $banType shadow, temp, permanent
     * @return void
     */
    public function resolveAndBan(int $alertId, string $banType = 'permanent'): void
    {
        $alert = FraudAlert::findOrFail($alertId);
        
        DB::transaction(function () use ($alert, $banType) {
            // 1. Баним юзера, если он есть
            if ($alert->user_id && $alert->user) {
                $banAction = app(ToggleUserBanAction::class);
                $reason = "Антифрод алерт #{$alert->id}: " . $alert->trigger_label;
                $banAction->execute($alert->user, $reason, $banType, true);
            }

            // 2. Логируем само закрытие алерта
            $before = $alert->only(['id', 'status', 'trigger_type', 'severity']);
            
            $alert->resolve(Auth::id());
            
            $after = $alert->fresh()->only(['id', 'status', 'admin_id', 'resolved_at']);

            AdminLog::record(
                'fraud_alert.resolve',
                $alert,
                Auth::user(),
                $before,
                [
                    'description' => 'Алерт разобран и подтвержден',
                    'action' => 'resolved',
                    'ban_type_applied' => $alert->user ? $banType : 'none (user deleted)',
                    'after_status' => $after
                ]
            );
        });
    }

        /**
     * Вынести предупреждение и закрыть алерт.
     * Создает системное сообщение в чате поддержки юзера.
     */
    public function resolveWithWarning(int $alertId): void
    {
        $alert = FraudAlert::findOrFail($alertId);
        
        // Закрываем алерт
        $before = $alert->only(['id', 'status', 'trigger_type', 'severity']);
        $alert->resolve(Auth::id());
        $after = $alert->fresh()->only(['id', 'status', 'admin_id', 'resolved_at']);

        AdminLog::record(
            'fraud_alert.warning',
            $alert,
            Auth::user(),
            $before,
            [
                'description' => 'Алерт разобран, вынесено предупреждение',
                'action' => 'warning',
                'after_status' => $after
            ]
        );

        // Отправляем сообщение в чат поддержки
        if ($alert->user_id && $alert->user) {
            $admin = Auth::user();
            $chat = Chat::getOrCreateSupportChat($admin->id, $alert->user->id);
            
            $warningText = "⚠️ Внимание! Администрация вынесла вам предупреждение за нарушение правил сервиса (Причина: {$alert->trigger_label}). Пожалуйста, ознакомьтесь с правилами платформы. При повторных нарушениях аккаунт может быть заблокирован.";
            
            $chat->messages()->create([
                'sender_id' => null, // Системное сообщение
                'type' => 'system',
                'body' => $warningText,
            ]);
            
            $chat->update(['last_message_at' => now()]);
        }
    }

    /**
     * Отметить как ложное срабатывание (юзер чист).
     */
    public function markAsFalsePositive(int $alertId): void
    {
        $alert = FraudAlert::findOrFail($alertId);
        
        // 1. Логируем закрытие алерта как ложняка
        $before = $alert->only(['id', 'status', 'trigger_type', 'severity']);
        
        $alert->markAsFalsePositive(Auth::id());
        
        $after = $alert->fresh()->only(['id', 'status', 'admin_id', 'resolved_at']);

        AdminLog::record(
            'fraud_alert.false_positive',
            $alert,
            Auth::user(),
            $before,
            [
                'description' => 'Алерт отмечен как ложное срабатывание',
                'action' => 'false_positive',
                'after_status' => $after
            ]
        );
    }
}