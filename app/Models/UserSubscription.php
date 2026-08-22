<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSubscription extends Model
{
    protected $fillable = [
        'user_id', 'plan_id', 'transaction_id', 'tier', 
        'starts_at', 'ends_at', 'is_auto_renew', 'provider_subscription_id', 
        'status', 'canceled_at'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'canceled_at' => 'datetime',
        'is_auto_renew' => 'boolean',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function plan(): BelongsTo { return $this->belongsTo(SubscriptionPlan::class); }
    public function transaction(): BelongsTo { return $this->belongsTo(Transaction::class); }

    public function scopeActive($query) { return $query->where('status', 'active')->where('ends_at', '>', now()); }
    public function scopeOverdue($query) { return $query->where('status', 'active')->where('ends_at', '<=', now()); }

    public function isActive(): bool { return $this->status === 'active' && $this->ends_at->isFuture(); }

    // Отмена автопродления (статус остается active до конца срока!)
    public function cancelAutoRenew(): bool {
        if (!$this->is_auto_renew) return true;
        return $this->update(['is_auto_renew' => false, 'canceled_at' => now()]);
    }

    // Фактическое завершение (когда вышло время)
    public function expire(): bool {
        return $this->update(['status' => 'expired', 'is_auto_renew' => false]);
    }


    // ============================================
    // АКСЕССОРЫ ДЛЯ UI
    // ============================================

    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            'active'   => ['variant' => 'success', 'label' => 'Активна'],
            'expired'  => ['variant' => 'secondary', 'label' => 'Истекла'],
            'canceled' => ['variant' => 'warning', 'label' => 'Отменена'],
            'failed'   => ['variant' => 'destructive', 'label' => 'Ошибка списания'],
            default    => ['variant' => 'secondary', 'label' => 'Неизвестно'],
        };
    }
}
