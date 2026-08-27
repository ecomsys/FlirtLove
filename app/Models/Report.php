<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Report extends Model
{
    use SoftDeletes; // Жалобы не удаляются физически никогда!

    protected $fillable = [
        'reporter_id',      // Кто жаловался
        'reported_id',      // На кого жаловались
        'reportable_type',  // Класс сущности (Photo, Message, User)
        'reportable_id',    // ID сущности
        'reason',           // Slug причины (spam, porn, scam, insult)
        'description',      // Текстовое описание от жалобщика
        'status',           // pending, resolved, rejected
        'resolution',       // Что сделал админ: ban, warn, shadowban, no_action
        'resolution_note',  // Внутренний комментарий модератора
        'admin_id',         // Кто из админов разобрал жалобу
        'resolved_at',      // Когда разобрали
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    // Кто подал жалобу
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    // На кого подали жалобу
    public function reported() {
        return $this->belongsTo(User::class, 'reported_id')->withTrashed();
    }

    // Какой админ разбирал жалобу
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    // Полиморфная связь: на что пожаловались (фото, сообщение, профиль)
    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    // ============================================
    // СКОПЫ (Твои, немного адаптированные)
    // ============================================

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    // Скоуп для фильтрации по типу сущности (замена старым scopeUserReports/scopePhotoReports)
    public function scopeForType($query, string $type)
    {
        return $query->where('reportable_type', $type);
    }

    // ============================================
    // ХЕЛПЕРЫ БИЗНЕС-ЛОГИКИ
    // ============================================

    /**
     * Закрыть жалобу с вынесением решения.
     * @param int $adminId
     * @param string $resolution - ban, warn, shadowban, no_action
     * @param string|null $note - комментарий для других модераторов
     */
    public function resolve(int $adminId, string $resolution, ?string $note = null): bool
    {
        $status = ($resolution === 'no_action') ? 'rejected' : 'resolved';

        return $this->update([
            'status' => $status,
            'resolution' => $resolution,
            'resolution_note' => $note,
            'admin_id' => $adminId,
            'resolved_at' => now(),
        ]);
    }

    /**
     * Заново открыть жалобу (если модератор ошибся).
     */
    public function reopen(): bool
    {
        return $this->update([
            'status' => 'pending',
            'resolution' => null,
            'resolution_note' => null,
            'admin_id' => null,
            'resolved_at' => null,
        ]);
    }

    /**
     * Аксессор для UI: красивый бейдж статуса жалобы (как в PhotoComment)
     */
    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            'pending'  => ['variant' => 'warning', 'label' => 'Ожидает'],
            'resolved' => ['variant' => 'success', 'label' => 'Разобрано'],
            'rejected' => ['variant' => 'secondary', 'label' => 'Отклонено'],
            default    => ['variant' => 'secondary', 'label' => 'Неизвестно'],
        };
    }
}