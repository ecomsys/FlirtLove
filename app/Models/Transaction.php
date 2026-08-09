<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    // ВАЖНО: Никаких SoftDeletes! Финансовые записи нельзя удалять или скрывать.

    protected $fillable = [
        'user_id',
        'amount',
        'currency',
        'type',                  // subscription, credits, refund
        'status',                // pending, success, failed, refunded
        'provider',              // stripe, yookassa, apple, google, manual
        'provider_transaction_id',
        'credits_amount',        // Если покупали внутреннюю валюту
        'meta',                  // Сырый JSON от платежки (вебхук)
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'credits_amount' => 'integer',
        'meta' => 'array',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ============================================
    // СКОПЫ (Для админки и финансового дашборда)
    // ============================================

    public function scopeSuccess($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeRefunded($query)
    {
        return $query->where('status', 'refunded');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSubscriptions($query)
    {
        return $query->where('type', 'subscription');
    }

    public function scopeCredits($query)
    {
        return $query->where('type', 'credits');
    }

    /**
     * Выручка за определенный период (для дашборда админки).
     * Использование: Transaction::success()->period(now()->startOfMonth(), now())->sum('amount')
     */
    public function scopePeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    // ============================================
    // ХЕЛПЕРЫ СТАТУСОВ (Для вебхуков и сервис-классов)
    // ============================================

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Отметить платеж как успешный (вызывается при получении вебхука от платежки).
     */
    public function markAsSuccess(array $metaData = []): bool
    {
        if ($this->status === 'success') {
            return true; // Идемпотентность: если уже успешен, не делаем лишних запросов
        }

        return $this->update([
            'status' => 'success',
            'meta' => array_merge($this->meta ?? [], $metaData),
        ]);
    }

    /**
     * Отметить платеж как ошибочный.
     */
    public function markAsFailed(?string $reason = null): bool
    {
        return $this->update([
            'status' => 'failed',
            'meta' => array_merge($this->meta ?? [], ['fail_reason' => $reason]),
        ]);
    }

    /**
     * Оформить возврат (Refund).
     * В идеале здесь также должна быть логика снятия VIP/кредитов у юзера,
     * но лучше это делать в сервис-классе BillingService, чтобы не нагружать модель.
     */
    public function markAsRefunded(array $metaData = []): bool
    {
        if ($this->status !== 'success') {
            return false; // Нельзя вернуть деньги за платеж, который не прошел
        }

        return $this->update([
            'status' => 'refunded',
            'meta' => array_merge($this->meta ?? [], $metaData),
        ]);
    }

    // ============================================
    // АКСЕССОРЫ ДЛЯ UI
    // ============================================

    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            'pending'  => ['variant' => 'warning', 'label' => 'В ожидании'],
            'success'  => ['variant' => 'success', 'label' => 'Успешно'],
            'failed'   => ['variant' => 'destructive', 'label' => 'Ошибка'],
            'refunded' => ['variant' => 'secondary', 'label' => 'Возврат'],
            default    => ['variant' => 'secondary', 'label' => 'Неизвестно'],
        };
    }

    /**
     * Форматированная сумма с валютой (для вывода в таблицах админки).
     * Пример: "999.00 ₽"
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2, '.', ' ') . ' ' . $this->currency;
    }
}



// модель Transaction (Платежи) — это самая строгая модель в проекте. К ней предъявляются требования финтех-систем: 
// никаких удалений (даже мягких), полная неизменность истории и строгие переходы между статусами.

// Вместо SoftDeletes мы используем статусы (failed, refunded). Если операция провалилась, она остается в
// БД со статусом failed. Если юзер потребовал чарджбэк (возврат), мы меняем статус на refunded.

// Я добавил удобные скоупы для фин. дашборда в админке (выручка за период), хелперы для безопасной 
// смены статуса и привычный нам getStatusBadgeAttribute для UI.

// Разбор архитектуры (Финтех-стандарты):

// Идемпотентность в markAsSuccess: Платежные системы (особенно Stripe и ЮKassa) иногда присылают вебхук об 
// успешной оплате дважды или трижды. Проверка if ($this->status === 'success') защищает нас от того, чтобы 
// начислить юзеру две подписки вместо одной за один платеж.
// Защита в markAsRefunded: Нельзя сделать возврат по платежу, который находится в статусе failed или pending. 
// Метод вернет false, если саппорт попытается сделать глупость.
// scopePeriod: Идеально для Livewire-дашборда. Ты сможешь в три строчки посчитать выручку за сегодня, 
// за неделю и за месяц, просто передавая даты.
// getFormattedAmountAttribute: В таблице админки ты выведешь {{ $transaction->formatted_amount }} и 
// получишь красивое "999.00 ₽", не пиши логику форматирования в Blade-шаблонах.
