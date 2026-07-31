<?php

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

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraudAlert extends Model
{
    // Никаких SoftDeletes! Алерты должны храниться вечно для аналитики паттернов мошенников.

    protected $fillable = [
        'user_id',
        'trigger_type',  // Тип: same_device, mass_messaging, links_in_chat, prostitute
        'severity',      // low, medium, high
        'meta',          // JSON: Доказательства (лог сообщений, IP, сработавшее правило)
        'status',        // open, resolved, false_positive
        'admin_id',      // Кто разобрал алерт
        'resolved_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'resolved_at' => 'datetime',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    // Юзер, на которого сработал триггер (подозреваемый)
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Админ, который принял решение по алерту
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    // ============================================
    // СКОПЫ (Для очереди модерации в админке)
    // ============================================

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeFalsePositive($query)
    {
        return $query->where('status', 'false_positive');
    }

    public function scopeHighSeverity($query)
    {
        return $query->where('severity', 'high');
    }

    public function scopeOfTrigger($query, string $type)
    {
        return $query->where('trigger_type', $type);
    }

    // ============================================
    // ХЕЛПЕРЫ БИЗНЕС-ЛОГИКИ
    // ============================================

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Разобрать алерт (подтвердить нарушение).
     * Вызывается, когда админ решает забанить юзера на основе алерта.
     */
    public function resolve(int $adminId): bool
    {
        if (!$this->isOpen()) {
            return true;
        }

        return $this->update([
            'status' => 'resolved',
            'admin_id' => $adminId,
            'resolved_at' => now(),
        ]);
    }

    /**
     * Отметить как ложное срабатывание (False Positive).
     * Вызывается, если алгоритм ошибся и юзер чист.
     */
    public function markAsFalsePositive(int $adminId): bool
    {
        if (!$this->isOpen()) {
            return true;
        }

        return $this->update([
            'status' => 'false_positive',
            'admin_id' => $adminId,
            'resolved_at' => now(),
        ]);
    }

    // ============================================
    // АКСЕССОРЫ ДЛЯ UI
    // ============================================

    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            'open'           => ['variant' => 'warning', 'label' => 'Открыт'],
            'resolved'       => ['variant' => 'success', 'label' => 'Подтвержден'],
            'false_positive' => ['variant' => 'secondary', 'label' => 'Ложняк'],
            default          => ['variant' => 'secondary', 'label' => 'Неизвестно'],
        };
    }

    public function getSeverityBadgeAttribute(): array
    {
        return match ($this->severity) {
            'low'    => ['variant' => 'secondary', 'label' => 'Низкий'],
            'medium' => ['variant' => 'warning', 'label' => 'Средний'],
            'high'   => ['variant' => 'destructive', 'label' => 'Высокий'],
            default  => ['variant' => 'secondary', 'label' => 'Неизвестно'],
        };
    }
}