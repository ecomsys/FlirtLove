<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Report extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'reported_user_id',
        'photo_id',
        'reason',
        'status',
        'type',
        'resolved_at',
        'moderator_id',
    ];

    protected $casts = [
        'status' => 'string',
        'type' => 'string',
        'resolved_at' => 'datetime',
    ];

    // Отношения

        /**
     * Локальный скоуп: Исключает жалобы, где замешаны админы
     * (ни как жалобщики, ни как нарушители).
     */
    public function scopeExcludeAdmins($query)
    {
        return $query->whereHas('user', fn($q) => $q->where('is_admin', false))
                     ->where(function ($query) {
                         $query->whereNull('reported_user_id') // Жалобы на фото пропускаем
                               ->orWhereHas('reportedUser', fn($q) => $q->where('is_admin', false));
                     });
    }
     
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    // Скоупы
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

    public function scopeUserReports($query)
    {
        return $query->where('type', 'user');
    }

    public function scopePhotoReports($query)
    {
        return $query->where('type', 'photo');
    }

    // В модели Report
    public static function canCreateReport(int $userId, int $reportedUserId): bool
    {
        // Защита от спама - не более 5 жалоб в час на одного пользователя
        $recentCount = self::where('user_id', $userId)
            ->where('created_at', '>=', now()->subHour())
            ->count();
        
        if ($recentCount >= 5) {
            return false;
        }
        
        // Нельзя жаловаться на одного пользователя более 3 раз в день
        $dailyCount = self::where('user_id', $userId)
            ->where('reported_user_id', $reportedUserId)
            ->whereDate('created_at', today())
            ->count();
        
        return $dailyCount < 3;
    }
}