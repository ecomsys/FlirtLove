<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class UserEvent extends Model
{
    // Указываем новую таблицу явно, чтобы избежать багов из-за старого названия
    protected $table = 'user_events'; 

    protected $fillable = [
        'user_id', 'type', 'properties'
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ============================================
    // ХЕЛПЕР ДЛЯ СОЗДАНИЯ СОБЫТИЯ
    // ============================================

    /**
     * Быстрый логгер событий.
     * Использование: UserEvent::log($user, 'photo_updated', ['photo_id' => 5]);
     * 
     * Текст выводится на фронте через __('events.' . $type)
     * DB::table()->insert() используется для максимальной скорости на миллионниках.
     */
    public static function log(User $user, string $type, array $properties = []): void
    {
        DB::table('user_events')->insert([
            'user_id'    => $user->id,
            'type'       => $type,
            'properties' => json_encode($properties), // кодируем вручную для DB фасада
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}