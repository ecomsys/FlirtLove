<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Album extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    // ============================================
    // СТАТИЧЕСКИЕ ХЕЛПЕРЫ
    // ============================================

    /**
     * Создать альбом "Общие" для нового пользователя
     */
    public static function createDefaultForUser(User $user): self
    {
        return self::create([
            'user_id' => $user->id,
            'name' => 'Общие',
            'description' => 'Основные фотографии',
            'is_default' => true,
        ]);
    }

    /**
     * Получить или создать альбом по умолчанию
     */
    public static function getDefaultForUser(User $user): self
    {
        return self::firstOrCreate(
            ['user_id' => $user->id, 'is_default' => true],
            ['name' => 'Общие', 'description' => 'Основные фотографии']
        );
    }

    // ============================================
    // СВЯЗИ
    // ============================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Фото в альбоме.
     * ВАЖНО: Добавлена сортировка по умолчанию!
     * Сначала идет главная фото (is_primary = 1 -> 0), потом по позиции.
     */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class)
            ->orderByDesc('is_primary')
            ->orderBy('position');
    }
}