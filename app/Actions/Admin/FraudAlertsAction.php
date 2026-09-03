<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\FraudAlert;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Chat;
use Illuminate\Support\Facades\Cache;

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
            // 1. Баним юзера, если он есть (ToggleUserBanAction сама напишет лог в participants)
            if ($alert->user_id && $alert->user) {
                $banAction = app(ToggleUserBanAction::class);
                $reason = "Антифрод алерт #{$alert->id}: " . $alert->trigger_label;
                $banAction->execute($alert->user, $reason, $banType, true);
            }

            // 2. Логируем само закрытие алерта
            $before = [
                'status' => $alert->getOriginal('status'), 
                'severity' => $alert->getOriginal('severity')
            ];
            
            $alert->resolve(Auth::id());
            $alert->refresh();
            
            $after = [
                'status' => 'resolved', 
                'resolved_by' => Auth::id(), 
                'resolved_at' => now()->toDateTimeString(),
                'context' => [
                    'alert_id' => $alert->id,
                    'user_id' => $alert->user_id,
                    'trigger_type' => $alert->trigger_type,
                    'trigger_label' => $alert->trigger_label,
                    'severity' => $alert->severity,
                    'ban_type_applied' => $alert->user ? $banType : 'none (user deleted)'
                ]
            ];

            // ФИКС: Передаем user_id в participants
            $participants = $alert->user_id ? [$alert->user_id] : [];

            AdminLog::record('fraud_alert.resolve', $alert, Auth::user(), $before, $after, participants: $participants);
        });
        Cache::forget('admin_sidebar_stats');
    }

    /**
     * Вынести предупреждение и закрыть алерт.
     * Создает системное сообщение в чате поддержки юзера.
     */
    public function resolveWithWarning(int $alertId): void
    {
        $alert = FraudAlert::findOrFail($alertId);
        
        $before = [
            'status' => $alert->getOriginal('status'), 
            'severity' => $alert->getOriginal('severity')
        ];
        
        $alert->resolve(Auth::id());
        $alert->refresh();
        
        $after = [
            'status' => 'resolved', 
            'resolved_by' => Auth::id(), 
            'resolved_at' => now()->toDateTimeString(),
            'action_taken' => 'warning',
            'context' => [
                'alert_id' => $alert->id,
                'user_id' => $alert->user_id,
                'trigger_type' => $alert->trigger_type,
                'trigger_label' => $alert->trigger_label,
                'severity' => $alert->severity
            ]
        ];

        $participants = $alert->user_id ? [$alert->user_id] : [];

        AdminLog::record('fraud_alert.warning', $alert, Auth::user(), $before, $after, participants: $participants);

        // Отправляем сообщение в чат поддержки
        if ($alert->user_id && $alert->user) {
            $admin = Auth::user();
            $chat = Chat::getOrCreateSupportChat($admin->id, $alert->user->id);
            
            $warningText = "⚠️ Внимание! Администрация вынесла вам предупреждение за нарушение правил сервиса (Причина: {$alert->trigger_label}). Пожалуйста, ознакомьтесь с правилами платформы. При повторных нарушениях аккаунт может быть заблокирован.";
            
            $chat->messages()->create([
                'sender_id' => null,
                'type' => 'system',
                'body' => $warningText,
            ]);
            
            $chat->update(['last_message_at' => now()]);
        }
          Cache::forget('admin_sidebar_stats');
    }

    /**
     * Отметить как ложное срабатывание (юзер чист).
     */
    public function markAsFalsePositive(int $alertId): void
    {
        $alert = FraudAlert::findOrFail($alertId);
        
        $before = [
            'status' => $alert->getOriginal('status'), 
            'severity' => $alert->getOriginal('severity')
        ];
        
        $alert->markAsFalsePositive(Auth::id());
        $alert->refresh();
        
        $after = [
            'status' => 'false_positive', 
            'resolved_by' => Auth::id(), 
            'resolved_at' => now()->toDateTimeString(),
            'context' => [
                'alert_id' => $alert->id,
                'user_id' => $alert->user_id,
                'trigger_type' => $alert->trigger_type,
                'trigger_label' => $alert->trigger_label,
                'severity' => $alert->severity
            ]
        ];

        // Даже если это ложняк, пишем в лог юзера, чтобы саппорт видел, что система ошибалась
        $participants = $alert->user_id ? [$alert->user_id] : [];

        AdminLog::record('fraud_alert.false_positive', $alert, Auth::user(), $before, $after, participants: $participants);

          Cache::forget('admin_sidebar_stats');
    }
}