<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Broadcast extends Model
{
    protected $fillable = [
        'admin_id',          // кто отправил или заплпнировал
        'type',              // in_app, push, email
        'title',             // загловок  
        'message',           // поле для пуш и колокольчика (обязательное)
        'email_body',        // поле только для емейл - html (опциональное)
        'data',              // JSON: deep links, иконки
        'target_audience',   // JSON: фильтры сегментации
        'status',            // draft, scheduled, sending, sent, failed
        'scheduled_at',      // отправка запланировна на ...
        'started_at',        // отправка началась ...
        'sent_at',           // отправлено ...
        'total_recipients',  // Счетчики статистики
        'sent_count',        // кол-во отправленных 
        'failed_count',      // кол-во упавших отправок
    ];

    protected $casts = [
        'data' => 'array',
        'target_audience' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'sent_at' => 'datetime',
        'total_recipients' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    // Кто создал рассылку (админ/модератор)
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    // ============================================
    // СКОПЫ (Для админки и крон-задач)
    // ============================================

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeSending($query)
    {
        return $query->where('status', 'sending');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * КРИТИЧЕСКИ ВАЖНЫЙ СКОП ДЛЯ КРОНА:
     * Найти все запланированные рассылки, время которых пришло.
     */
    public function scopeDueForDispatch($query)
    {
        return $query->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now());
    }

    // ============================================
    // ХЕЛПЕРЫ ЖИЗНЕННОГО ЦИКЛА (Для воркеров)
    // ============================================

    /**
     * Начать рассылку (блокируем от повторного запуска кроном).
     */
    public function markAsSending(int $totalRecipients): bool
    {
        return $this->update([
            'status' => 'sending',
            'started_at' => now(),
            'total_recipients' => $totalRecipients,
        ]);
    }

    /**
     * Завершить рассылку успешно.
     */
    public function markAsSent(): bool
    {
        return $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Отметить ошибку при рассылке.
     */
    public function markAsFailed(): bool
    {
        return $this->update([
            'status' => 'failed',
            'sent_at' => now(),
        ]);
    }

    /**
     * Увеличить счетчик успешных отправок (вызывается воркером на каждый пуш).
     */
    public function incrementSent(): void
    {
        $this->increment('sent_count');
    }

    /**
     * Увеличить счетчик ошибок (например, невалидный токен пуша).
     */
    public function incrementFailed(): void
    {
        $this->increment('failed_count');
    }

    // ============================================
    // АКСЕССОРЫ
    // ============================================

    /**
     * Прогресс отправки в процентах (для UI админки).
     */
    public function getProgressAttribute(): int
    {
        if ($this->total_recipients === 0) {
            return 0;
        }

        return (int) round((($this->sent_count + $this->failed_count) / $this->total_recipients) * 100);
    }

     /**
     * Возвращает массив частей аудитории для списока (UL > LI)
     */
       public function getAudiencePartsAttribute(): array
    {
        $audience = $this->target_audience ?? [];
        $parts = [];

        if (!empty($audience['gender'])) {
            $parts[] = $audience['gender'] === 'male' ? 'Мужчины' : 'Женщины';
        }
        if (isset($audience['is_premium'])) {
            $parts[] = ($audience['is_premium'] === true || $audience['is_premium'] === 'true') ? 'VIP' : 'Без VIP';
        }
        if (!empty($audience['city'])) {
            $parts[] = 'Город: ' . $audience['city'];
        }
        
        $ageStr = '';
        if (!empty($audience['age_from'])) $ageStr .= 'от ' . $audience['age_from'];
        if (!empty($audience['age_from']) && !empty($audience['age_to'])) $ageStr .= '-';
        elseif (!empty($audience['age_to'])) $ageStr .= 'до ';
        if (!empty($audience['age_to'])) $ageStr .= $audience['age_to'];
        if ($ageStr) $parts[] = 'Возраст: ' . $ageStr . ' лет';
        
        if (!empty($audience['device_os'])) {
            $osMap = ['ios' => 'iOS', 'android' => 'Android', 'web' => 'Web'];
            $parts[] = $osMap[$audience['device_os']] ?? $audience['device_os'];
        }
        if (!empty($audience['last_seen_days'])) {
            $parts[] = 'неактивные >' . $audience['last_seen_days'] . 'д';
        }
        if (isset($audience['has_photo'])) {
            $parts[] = ($audience['has_photo'] === true || $audience['has_photo'] === 'true') ? 'с фото' : 'без фото';
        }

        return $parts;
    }

    /**
     * Возвращает строку аудитории (используется для title="" и одиночного юзера)
     */
    public function getAudienceLabelAttribute(): string
    {
        $audience = $this->target_audience ?? [];

        if (!empty($audience['user_id'])) {
            return 'Юзер: ' . ($audience['user_name'] ?? 'ID ' . $audience['user_id']);
        }

        $parts = $this->audience_parts;
        return empty($parts) ? 'Все пользователи' : implode(', ', $parts);
    }
}


// scopeDueForDispatch: Это спаситель от багов. Крон запускается каждую минуту.
// Если рассылка занимает 5 минут, без этого скоупа (и статуса sending) крон запустил бы рассылку 5 раз подряд, 
// и юзеры получили бы по 5 одинаковых пушей.
// Хелперы markAsSending, markAsSent, incrementSent: Инкапсулируют логику воркеров. 
// В коде очереди ты просто напишешь $broadcast->markAsSending(1000); (нашли 1000 юзеров), 
// а в цикле отправки: $broadcast->incrementSent();.
// Аксессор getProgressAttribute: В админке (Livewire) ты сможешь вывести красивый прогресс-бар: 
// <progress value="{{ $broadcast->progress }}"></progress>. Он сам посчитает процент на основе счетчиков, 
// без лишних запросов.