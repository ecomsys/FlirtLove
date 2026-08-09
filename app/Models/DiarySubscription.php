<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiarySubscription extends Model
{
    protected $fillable = [
        'subscriber_id',
        'author_id',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    /**
     * Кто подписался (читатель)
     */
    public function subscriber()
    {
        return $this->belongsTo(User::class, 'subscriber_id');
    }

    /**
     * На кого подписался (автор)
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    // ============================================
    // ХЕЛПЕРЫ
    // ============================================

    /**
     * Быстрая проверка, подписан ли юзер А на юзера Б.
     * Используется в контроллерах перед кнопкой "Подписаться/Отписаться"
     */
    public static function isSubscribed(int $subscriberId, int $authorId): bool
    {
        return static::where('subscriber_id', $subscriberId)
            ->where('author_id', $authorId)
            ->exists();
    }
}