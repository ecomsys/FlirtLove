<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\FraudAlert;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Chat;
use Illuminate\Support\Facades\Cache;

class FraudAlertsAction
{
    public function resolveAndBan(int $alertId, string $banType = 'permanent'): void
    {
        $alert = FraudAlert::findOrFail($alertId);
        
        DB::transaction(function () use ($alert, $banType) {
            if ($alert->user_id) {
                $user = $alert->user;
                if ($user) {
                    $banAction = app(ToggleUserBanAction::class);
                    $reason = "Антифрод алерт #{$alert->id}: " . $alert->trigger_label;
                    $banAction->execute($user, Auth::user(), $reason, $banType, true);
                }
            }

            $before = [
                'status' => $alert->getOriginal('status'), 
                'severity' => $alert->getOriginal('severity')
            ];
            
            $alert->resolve(Auth::id());
            
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

            $participants = $alert->user_id ? [$alert->user_id] : [];

            AdminLog::record('fraud_alert.resolve', $alert, Auth::user(), $before, $after, participants: $participants);
        });
        
        $this->clearCaches();
    }

    public function resolveWithWarning(int $alertId): void
    {
        $alert = FraudAlert::findOrFail($alertId);
        
        DB::transaction(function () use ($alert) {
            $before = [
                'status' => $alert->getOriginal('status'), 
                'severity' => $alert->getOriginal('severity')
            ];
            
            $alert->resolve(Auth::id());
            
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

            if ($alert->user_id) {
                $admin = Auth::user();
                $chat = Chat::getOrCreateSupportChat($admin->id, $alert->user_id);
                
                $warningText = "⚠️ Внимание! Администрация вынесла вам предупреждение за нарушение правил сервиса (Причина: {$alert->trigger_label}). Пожалуйста, ознакомьтесь с правилами платформы. При повторных нарушениях аккаунт может быть заблокирован.";
                
                $chat->messages()->create([
                    'sender_id' => null,
                    'type' => 'system',
                    'body' => $warningText,
                ]);
                
                $chat->update(['last_message_at' => now()]);
            }
        });
        
        $this->clearCaches();
    }

    public function markAsFalsePositive(int $alertId): void
    {
        $alert = FraudAlert::findOrFail($alertId);
        
        DB::transaction(function () use ($alert) {
            $before = [
                'status' => $alert->getOriginal('status'), 
                'severity' => $alert->getOriginal('severity')
            ];
            
            $alert->markAsFalsePositive(Auth::id());
            
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

            $participants = $alert->user_id ? [$alert->user_id] : [];

            AdminLog::record('fraud_alert.false_positive', $alert, Auth::user(), $before, $after, participants: $participants);
        });
        
        $this->clearCaches();
    }

    private function clearCaches(): void
    {
        Cache::forget('admin_sidebar_stats');
        Cache::forget('admin_fraud_counts');
    }
}