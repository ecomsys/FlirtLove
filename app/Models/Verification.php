<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Verification extends Model
{
    protected $fillable = [
        'user_id',
        'photo_id',
        'status',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function photo()
    {
        return $this->belongsTo(Photo::class);
    }

    public function moderator()
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    // ============================================
    // СКОПЫ
    // ============================================

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    // ============================================
    // ХЕЛПЕРЫ МОДЕРАЦИИ
    // ============================================

    /**
     * Одобрить верификацию (вызывает админ)
     */
    public function markAsApproved(int $adminId): bool
    {
        $updated = $this->update([
            'status' => 'approved',
            'moderated_by' => $adminId,
            'moderated_at' => now(),
        ]);

        // Если заявка одобрена -> ставим флаг is_verified юзеру
        if ($updated) {
            $this->user->update(['is_verified' => true]);
        }

        return $updated;
    }

    /**
     * Отклонить верификацию
     */
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