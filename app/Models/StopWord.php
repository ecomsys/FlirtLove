<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StopWord extends Model
{
    protected $fillable = [
        'word',         // Само слово, фраза или регулярка
        'category',     // mat, scam, prostitution, drugs, contacts
        'action',       // mask, reject, alert
        'replacement',  // На что заменять (по умолчанию '***')
        'is_active',    // Флаг включения/выключения
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ============================================
    // СКОПЫ
    // ============================================

    /**
     * Только активные слова (для загрузки в кэш ContentFilter).
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Фильтр по категории (для админки).
     */
    public function scopeOfCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Фильтр по действию (например, вывести все слова, которые блокируют сообщения).
     */
    public function scopeOfAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    // ============================================
    // ХЕЛПЕРЫ ТИПОВ ДЕЙСТВИЙ
    // ============================================

    public function isMask(): bool
    {
        return $this->action === 'mask';
    }

    public function isReject(): bool
    {
        return $this->action === 'reject';
    }

    public function isAlert(): bool
    {
        return $this->action === 'alert';
    }

    // ============================================
    // АКСЕССОРЫ ДЛЯ UI
    // ============================================

    /**
     * Бейдж для поля "Действие" в таблице админки.
     */
    public function getActionBadgeAttribute(): array
    {
        return match ($this->action) {
            'mask'   => ['variant' => 'secondary', 'label' => 'Маскировать'],
            'reject' => ['variant' => 'destructive', 'label' => 'Блокировать'],
            'alert'  => ['variant' => 'warning', 'label' => 'Тревога (Антифрод)'],
            default  => ['variant' => 'secondary', 'label' => 'Неизвестно'],
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
