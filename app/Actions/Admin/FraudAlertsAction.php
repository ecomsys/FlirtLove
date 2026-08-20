<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\FraudAlert;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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