<?php

// Модель UserSubscription — это архив всех покупок VIP-статусов.

// Здесь кроется самый важный нюанс автопродления (рекуррентных платежей), из-за которого многие 
// разработчики получают суды от пользователей: разница между отменой автопродления и истечением подписки. 
// Если юзер нажимает "Отменить подписку" в App Store, он отменяет будущие списания, но VIP-статус должен работать
//  до конца оплаченного периода!

// Поэтому мы пишем хелперы cancelAutoRenew() (отмена будущих списаний) и expire() (фактическое окончание срока), 
// а также скоупы для крон-задач.

// Разбор архитектуры (Защита от проблем с Apple/Google):

// scopeExpiringSoon и scopeOverdue: Это два самых важных скоупа для крон-задач (Scheduled Tasks). 
// Первый будет слать пуши за 24 часа до окончания: "Продлите VIP, чтобы не потерять невидимку!". 
// Второй будет запускаться каждую минуту и снимать VIP-статус у тех, у кого время вышло (менять статус на expired и снимать 
// is_premium в таблице users).
// cancelAutoRenew() vs expire(): Это разделение спасет тебя от блокировки в App Store. Юзер отменил подписку → мы вызываем 
// cancelAutoRenew() (ставим canceled_at и is_auto_renew = false). Но status остается active! Юзер пользуется VIP до ends_at. 
// Когда наступит ends_at, крон вызовет expire().
// Отсутствие SoftDeletes: Финансовая история подписок (когда купил, когда продлил, когда истекла) должна быть неизменной. 
// Удалять её нельзя. Если юзер удалит аккаунт, записи останутся для налоговой отчетности (мы настроили это в миграции 
// через отсутствие каскада).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'plan_id',
        'transaction_id',
        'starts_at',
        'ends_at',
        'is_auto_renew',
        'provider_subscription_id', // ID подписки в Stripe/YooKassa/Apple
        'status',                   // active, expired, canceled, failed
        'canceled_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'canceled_at' => 'datetime',
        'is_auto_renew' => 'boolean',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    // ============================================
    // СКОПЫ (Для крон-задач и админки)
    // ============================================

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('ends_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    /**
     * КРИТИЧЕСКИ ВАЖНЫЙ СКОП ДЛЯ КРОНА:
     * Найти подписки, которые истекают в ближайшие 24 часа.
     * Используем для пуш-уведомлений "Ваш VIP-статус заканчивается!".
     */
    public function scopeExpiringSoon($query, int $hours = 24)
    {
        return $query->where('status', 'active')
            ->whereBetween('ends_at', [now(), now()->addHours($hours)]);
    }

    /**
     * Найти подписки, у которых время уже вышло, но статус еще active.
     * Крон будет запускать это раз в минуту, чтобы переводить их в expired.
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'active')->where('ends_at', '<=', now());
    }

    // ============================================
    // ХЕЛПЕРЫ БИЗНЕС-ЛОГИКИ
    // ============================================

    /**
     * Проверить, активна ли подписка прямо сейчас.
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->ends_at->isFuture();
    }

    /**
     * Отменить автопродление (пользователь решил не платить больше).
     * ВАЖНО: Подписка продолжает работать до ends_at!
     * Это требование Apple App Store Review Guidelines.
     */
    public function cancelAutoRenew(): bool
    {
        if ($this->is_auto_renew) {
            return $this->update([
                'is_auto_renew' => false,
                'canceled_at' => now(),
            ]);
        }
        return true;
    }

    /**
     * Фактически завершить подписку (вызывается кроном, когда наступило ends_at).
     */
    public function expire(): bool
    {
        return $this->update([
            'status' => 'expired',
            'is_auto_renew' => false, // На всякий случай сбрасываем флаг
        ]);
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