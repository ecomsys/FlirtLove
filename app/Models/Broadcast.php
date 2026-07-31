<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Broadcast extends Model
{
    protected $fillable = [
        'admin_id',
        'type',              // in_app, push, email
        'title',
        'message',
        'data',              // JSON: deep links, иконки
        'target_audience',   // JSON: фильтры сегментации
        'status',            // draft, scheduled, sending, sent, failed
        'scheduled_at',
        'started_at',
        'sent_at',
        'total_recipients',  // Счетчики статистики
        'sent_count',
        'failed_count',
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