<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use SoftDeletes; // КРИТИЧЕСКИ ВАЖНО! Сообщения нельзя удалять физически.

    protected $fillable = [
        'chat_id',
        'sender_id',
        'type',             // text, image, system, gift
        'body',
        'attachment_url',   // Ссылка на картинку (если type=image)
        'gift_id',          // Ссылка на подарок (если type=gift)
        'status',           // approved, pending, rejected (для модерации фоток)
        'reject_reason',
        'moderated_by',
        'moderated_at',
    ];

    protected $casts = [
        'moderated_at' => 'datetime',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // Модератор, проверивший фотку в сообщении
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    // Подарок (если type=gift)
    public function gift(): BelongsTo
    {
        return $this->belongsTo(Gift::class); 
    }

    // ============================================
    // СКОПЫ (Для админки и логики)
    // ============================================

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeImages($query)
    {
        return $query->where('type', 'image');
    }

    public function scopeGifts($query)
    {
        return $query->where('type', 'gift');
    }

    public function scopeSystem($query)
    {
        return $query->where('type', 'system');
    }

    // ============================================
    // ХЕЛПЕРЫ ТИПОВ
    // ============================================

    public function isText(): bool { return $this->type === 'text'; }
    public function isImage(): bool { return $this->type === 'image'; }
    public function isGift(): bool { return $this->type === 'gift'; }
    public function isSystem(): bool { return $this->type === 'system'; }

    // ============================================
    // ХЕЛПЕРЫ МОДЕРАЦИИ (Единый паттерн)
    // ============================================

    public function markAsApproved(int $adminId): bool
    {
        return $this->update([
            'status' => 'approved',
            'moderated_by' => $adminId,
            'moderated_at' => now(),
            'reject_reason' => null,
        ]);
    }

    public function markAsRejected(int $adminId, string $reason): bool
    {
        return $this->update([
            'status' => 'rejected',
            'moderated_by' => $adminId,
            'moderated_at' => now(),
            'reject_reason' => $reason,
        ]);
    }
}