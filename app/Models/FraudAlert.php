<?php

namespace App\Models;

use App\Enums\FraudAlertSeverity;
use App\Enums\FraudAlertStatus;
use App\Enums\FraudTriggerType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraudAlert extends Model
{
    // Никаких SoftDeletes! Алерты должны храниться вечно для аналитики паттернов мошенников.

    protected $fillable = [
        'user_id',
        'trigger_type',
        'severity',
        'meta',
        'status',
        'admin_id',
        'resolved_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'resolved_at' => 'datetime',
        'status' => FraudAlertStatus::class,
        'severity' => FraudAlertSeverity::class,
        'trigger_type' => FraudTriggerType::class, // НОВЫЙ КАСТ
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    // ============================================
    // СКОПЫ
    // ============================================

    public function scopeOpen($query) { return $query->where('status', FraudAlertStatus::Open); }
    public function scopeResolved($query) { return $query->where('status', FraudAlertStatus::Resolved); }
    public function scopeFalsePositive($query) { return $query->where('status', FraudAlertStatus::FalsePositive); }
    public function scopeHighSeverity($query) { return $query->where('severity', FraudAlertSeverity::High); }
    
    // Скоп теперь строго принимает Enum
    public function scopeOfTrigger($query, FraudTriggerType $type) { return $query->where('trigger_type', $type); }

    // ============================================
    // ХЕЛПЕРЫ БИЗНЕС-ЛОГИКИ
    // ============================================

    public function isOpen(): bool
    {
        // Теперь нам не нужны проверки на строку, так как каст гарантирует Enum
        return $this->status === FraudAlertStatus::Open;
    }

    public function resolve(int $adminId): bool
    {
        if (!$this->isOpen()) return true;
        return $this->update([
            'status' => FraudAlertStatus::Resolved,
            'admin_id' => $adminId,
            'resolved_at' => now(),
        ]);
    }

    public function markAsFalsePositive(int $adminId): bool
    {
        if (!$this->isOpen()) return true;
        return $this->update([
            'status' => FraudAlertStatus::FalsePositive,
            'admin_id' => $adminId,
            'resolved_at' => now(),
        ]);
    }

    // ============================================
    // АКСЕССОРЫ ДЛЯ UI
    // ============================================

    public function getTriggerLabelAttribute(): string
    {
        // Если вдруг в базе старые данные, которые не совпали с Enum, fallback спасет от 500 ошибки
        return $this->trigger_type?->label() ?? ucfirst(str_replace('_', ' ', $this->getRawOriginal('trigger_type')));
    }

    public function getStatusBadgeAttribute(): array
    {
        return [
            'variant' => $this->status->badgeVariant(),
            'label' => $this->status->label()
        ];
    }

    public function getSeverityBadgeAttribute(): array
    {
        return [
            'variant' => $this->severity->badgeVariant(),
            'label' => $this->severity->label()
        ];
    }
}

// модель FraudAlert — это иммунная система платформы. В дейтинге скаммеры и боты — это главная причина оттока нормальных юзеров. 
// Если девушка заходит в аппку и получает 10 сообщений от ботов "кинь на карту 500 рублей", она удалит приложение навсегда.

// Эта модель собирает сработки правил (например, регулярка нашла ссылку на Telegram в чате, или ИИ распознал запрещенку).

// Я добавил удобные скоупы для очереди модерации (открытые/высокий приоритет), хелперы для разбора алертов админом и 
// привычный нам getStatusBadgeAttribute для UI.

// Разбор архитектуры (Как это работает в проде):

// Разделение статусов и severity: severity (опасность) позволяет настроить автоматизацию. 
// Например, если severity = 'high' (ИИ нашел ЦП), воркер автоматически банит юзера и создает алерт со статусом open. 
// Модератору остается только перепроверить и нажать resolve(). Если severity = 'low', юзер не банится, 
// а просто попадает в очередь на ручную проверку.
// Хелперы resolve() и markAsFalsePositive(): Защищают от багов. Нельзя разобрать алерт, который уже разобран 
// (проверка if (!$this->isOpen())). Это исключает двойные начисления зарплаты модераторам или путаницу в логах.
// Анализ паттернов: Поскольку мы не удаляем алерты, ты сможешь делать запросы: "Покажи мне все false_positive по триггеру 
// links_in_chat". Если их будет 90%, значит твоя регулярка поиска ссылок кривая, и ты сможешь её поправить.
