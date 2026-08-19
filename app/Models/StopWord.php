<?php

namespace App\Models;

use App\Enums\StopWordAction;
use App\Enums\StopWordCategory;
use Illuminate\Database\Eloquent\Model;

class StopWord extends Model
{
    protected $fillable = [
        'word',
        'category',
        'action',
        'replacement',
        'is_active',
    ];

    // ВАЖНО: Добавляем касты для Enum
    protected $casts = [
        'is_active' => 'boolean',
        'category' => StopWordCategory::class,
        'action' => StopWordAction::class,
    ];

    // ============================================
    // СКОПЫ
    // ============================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfCategory($query, StopWordCategory $category)
    {
        return $query->where('category', $category);
    }

    public function scopeOfAction($query, StopWordAction $action)
    {
        return $query->where('action', $action);
    }

    // ============================================
    // ХЕЛПЕРЫ ТИПОВ ДЕЙСТВИЙ
    // ============================================

    public function isMask(): bool
    {
        return $this->action === StopWordAction::Mask;
    }

    public function isReject(): bool
    {
        return $this->action === StopWordAction::Reject;
    }

    public function isAlert(): bool
    {
        return $this->action === StopWordAction::Alert;
    }

    // ============================================
    // АКСЕССОРЫ ДЛЯ UI
    // ============================================

    public function getActionBadgeAttribute(): array
    {
        return match ($this->action) {
            StopWordAction::Mask   => ['variant' => 'secondary', 'label' => StopWordAction::Mask->label()],
            StopWordAction::Reject => ['variant' => 'destructive', 'label' => StopWordAction::Reject->label()],
            StopWordAction::Alert  => ['variant' => 'warning', 'label' => StopWordAction::Alert->label()],
        };
    }
}



// Модель StopWord (Стоп-слова) — это базовый, но критически важный фильтр. 80% спамеров и мошенников используют стандартные фразы, 
// номера телефонов и ссылки на мессенджеры.

// В нашей архитектуре мы заложили поле action (что делать при нахождении слова) и replacement (на что заменять). 
// Я написал удобные хелперы для проверки действия и красивый бейдж для UI админки, чтобы было видно, какое слово просто маскируется, 
// а какое — блокирует отправку сообщения.

// Разбор архитектуры (Как это работает в проде):

// Хелперы isMask(), isReject(), isAlert(): В сервис-классе ContentFilter мы будем читать эти флаги.
// Если isMask() — мы просто делаем str_replace на replacement.
// Если isReject() — мы возвращаем false и не даем сохранить сообщение/описание, показывая юзеру ошибку "Текст содержит запрещенные элементы".
// Если isAlert() — мы пропускаем сообщение (чтобы спамер не понял, что мы его спалили), но одновременно кидаем запись в 
// таблицу fraud_alerts, чтобы антифрод-система наложила теневой бан.
// Кэширование: Сама модель не делает сложных запросов. При старте приложения мы один раз вызовем 
// StopWord::active()->get(), закэшируем это в Redis, и фильтр будет работать в оперативной памяти за миллисекунды,
// вообще не трогая базу данных.
