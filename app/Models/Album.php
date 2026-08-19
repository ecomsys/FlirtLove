<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Album extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'is_default',
        'is_private',     // Новое поле: приватный альбом
        'photos_count',   // Новое поле: кэш количества фото
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_private' => 'boolean',
        'photos_count' => 'integer',
    ];

    // ============================================
    // СТАТИЧЕСКИЕ ХЕЛПЕРЫ
    // ============================================

    /**
     * Создать альбом "Общие" для нового пользователя.
     * Вызывается при регистрации (в User::booted).
     */
    public static function createDefaultForUser(User $user): self
    {
        return self::create([
            'user_id' => $user->id,
            'name' => 'Общие',
            'description' => 'Основные фотографии',
            'is_default' => true,
            'is_private' => false,
        ]);
    }

    /**
     * Получить или создать альбом по умолчанию.
     * Полезно, если по какой-то причине альбом не создался при регистрации.
     */
    public static function getDefaultForUser(User $user): self
    {
        return self::firstOrCreate(
            ['user_id' => $user->id, 'is_default' => true],
            ['name' => 'Общие', 'description' => 'Основные фотографии', 'is_private' => false]
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
     * ВАЖНО: Сортировка по умолчанию!
     * Сначала идет главная фото (is_primary = 1 -> 0), потом по позиции.
     * Твой код - просто золото, оставляем без изменений.
     */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class)
            ->orderByDesc('is_primary')
            ->orderBy('position');
    }

    // ============================================
    // ХЕЛПЕРЫ ДЛЯ ДЕНОРМАЛИЗАЦИИ
    // ============================================

    /**
     * Обновить кэш количества фото в альбоме.
     * Будем вызывать в Observer модели Photo при создании/удалении.
     */
    public function refreshPhotosCount(): void
    {
        // Считаем только неудаленные фото (Soft Scopes)
        $this->photos_count = $this->photos()->count();
        $this->save();
    }
}