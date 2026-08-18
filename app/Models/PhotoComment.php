<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PhotoComment extends Model
{
    use SoftDeletes; // Обязательно для сохранения удаленных матов/оскорблений

    protected $fillable = [
        'photo_id',
        'user_id',
        'content',
        'status',
        'reject_reason',     // Единый паттерн с Photo
        'moderated_by',      // ID админа
        'moderated_at',      // Время проверки
        'parent_id',
        'likes_count',
        'reports_count',
        'replies_count',     // Новое: кэш количества ответов
        'is_pinned',
        'edited_at',         // Убрали is_edited, так как edited_at != null само по себе флаг
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'likes_count' => 'integer',
        'reports_count' => 'integer',
        'replies_count' => 'integer',
        'moderated_at' => 'datetime',
        'edited_at' => 'datetime',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Модератор, проверивший комментарий
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(PhotoComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(PhotoComment::class, 'parent_id');
    }

    // ============================================
    // СКОПЫ
    // ============================================

    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }

    public function scopeSpam(Builder $query): Builder
    {
        return $query->where('status', 'spam');
    }

    // ============================================
    // МЕТОДЫ И ХЕЛПЕРЫ
    // ============================================

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    // Твой крутой аксессор для UI (оставляем без изменений)
    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            'pending'  => ['variant' => 'warning', 'label' => 'Ожидает'],
            'approved' => ['variant' => 'success', 'label' => 'Одобрен'],
            'rejected' => ['variant' => 'destructive', 'label' => 'Отклонен'],
            'spam'     => ['variant' => 'destructive', 'label' => 'Спам'],
            default    => ['variant' => 'secondary', 'label' => 'Неизвестно'],
        };
    }

    /**
     * Одобрить комментарий (Обновлено под паттерн)
     */
    public function approve(int $adminId): void
    {
        $this->update([
            'status' => 'approved',
            'moderated_by' => $adminId,
            'moderated_at' => now(),
            'reject_reason' => null,
        ]);
    }

    /**
     * Отклонить комментарий (Обновлено под паттерн)
     */
    public function reject(int $adminId, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'moderated_by' => $adminId,
            'moderated_at' => now(),
            'reject_reason' => $reason,
        ]);
    }

    /**
     * Пометить как спам (Обновлено под паттерн)
     */
    public function markAsSpam(int $adminId): void
    {
        // Просто вызываем reject с причиной 'spam'
        $this->reject($adminId, 'spam');
    }
}