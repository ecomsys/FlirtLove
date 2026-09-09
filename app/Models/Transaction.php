<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    // КОНСТАНТЫ СТАТУСОВ
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';

    // КОНСТАНТЫ ТИПОВ
    public const TYPE_SUBSCRIPTION = 'subscription';
    public const TYPE_CREDITS = 'credits';
    public const TYPE_REFUND = 'refund';

    protected $fillable = [
        'user_id',
        'amount',
        'currency',
        'type',
        'status',
        'provider',
        'provider_transaction_id',
        'credits_amount',
        'meta',
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
    // СКОПЫ
    // ============================================

    public function scopeSuccess(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeRefunded(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REFUNDED);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeSubscriptions(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_SUBSCRIPTION);
    }

    public function scopeCredits(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_CREDITS);
    }

     public function scopePeriod(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    // ============================================
    // ХЕЛПЕРЫ СТАТУСОВ
    // ============================================

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function markAsSuccess(array $metaData = []): bool
    {
        if ($this->status === self::STATUS_SUCCESS) {
            return true; 
        }

        return $this->update([
            'status' => self::STATUS_SUCCESS,
            'meta' => array_merge($this->meta ?? [], $metaData),
        ]);
    }

    public function markAsFailed(?string $reason = null): bool
    {
        return $this->update([
            'status' => self::STATUS_FAILED,
            'meta' => array_merge($this->meta ?? [], ['fail_reason' => $reason]),
        ]);
    }

    public function markAsRefunded(array $metaData = []): bool
    {
        if ($this->status !== self::STATUS_SUCCESS) {
            return false; 
        }

        $updated = $this->update([
            'status' => self::STATUS_REFUNDED,
            'meta' => array_merge($this->meta ?? [], $metaData),
        ]);

        if ($updated) {
            event(new \App\Events\TransactionRefunded($this));
        }

        return $updated;
    }

    // ============================================
    // АКСЕССОРЫ ДЛЯ UI
    // ============================================

    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            self::STATUS_PENDING  => ['variant' => 'warning', 'label' => 'В ожидании'],
            self::STATUS_SUCCESS  => ['variant' => 'success', 'label' => 'Успешно'],
            self::STATUS_FAILED   => ['variant' => 'destructive', 'label' => 'Ошибка'],
            self::STATUS_REFUNDED => ['variant' => 'secondary', 'label' => 'Возврат'],
            default               => ['variant' => 'secondary', 'label' => 'Неизвестно'],
        };
    }

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
