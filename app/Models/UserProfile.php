<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id', 
        'gender', 'age', 'birth_date', 'dating_goal', 'city', 'country',
        'headline', 'bio', 'looking_for', 'interests', 'self_portrait',
        'body_type', 'eye_color', 'hair_color', 'height', 'weight',
        'relationship_status', 'children_status', 'pets', 'housing', 'has_car', 'smoking', 'alcohol',
        'zodiac_sign',
        'body_decorations', 'languages', 'sports',
        'education', 'institution', 'institution_year', 'activity', 'position',
        'location', 'address',
    ];

    protected $casts = [
        'birth_date' => 'date',
        
        // JSON поля (Множественный выбор и теги)
        'interests' => 'array',
        'self_portrait' => 'array', // Наш новый блок "Автопортрет"
        'body_decorations' => 'array',
        'languages' => 'array',
        'sports' => 'array',
        
        // Числовые значения
        'institution_year' => 'integer',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ============================================
    // ГЕОЛОКАЦИЯ (PostGIS)
    // ============================================

    /**
     * Обновить гео-точку через PostGIS
     */
    public function setLocation(float $lat, float $lng): void
    {
        // Используем DB::raw для нативной функции PostGIS
        $this->location = DB::raw("ST_SetSRID(ST_MakePoint({$lng}, {$lat}), 4326)::geography");
        $this->save();
    }

    /**
     * САФИ СПОСОБ: Получить координаты через SQL-селект.
     * Использование: $profile = UserProfile::withCoordinates()->find($id);
     * $profile->latitude; $profile->longitude;
     */
    public function scopeWithCoordinates($query)
    {
        return $query->addSelect([
            'latitude' => DB::raw('ST_Y(location::geometry)'),
            'longitude' => DB::raw('ST_X(location::geometry)')
        ]);
    }

    /**
     * Скоуп для поиска анкет рядом (в радиусе)
     */
    public function scopeNearby($query, float $lat, float $lng, int $radius = 50)
    {
        return $query->whereNotNull('location')
            ->whereRaw(
                "ST_DWithin(location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)", 
                [$lng, $lat, $radius * 1000] // Переводим км в метры
            );
    }

    // ============================================
    // СКОПЫ ДЛЯ ПОИСКА (МАТЧИНГА)
    // ============================================

    /**
     * Фильтр по полу (мужчины, женщины)
     */
    public function scopeOfGender($query, ?string $gender)
    {
        if (!$gender || $gender === 'any') {
            return $query;
        }
        return $query->where('gender', $gender);
    }

       /**
     * Фильтр по возрасту (от и до). Защита от null.
     */
    // Переписываем скоуп на молниеносный:
    public function scopeBetweenAges($query, ?int $minAge = 18, ?int $maxAge = 99)
    {
        $minAge = $minAge ?? 18;
        $maxAge = $maxAge ?? 99;
        
        if ($minAge > $maxAge) {
            [$minAge, $maxAge] = [$maxAge, $minAge];
        }

        // Теперь использует ИНДЕКС!
        return $query->whereBetween('age', [$minAge, $maxAge]);
    }

    // ============================================
    // АКСЕССОРЫ И ХЕЛПЕРЫ
    // ============================================

    /**
     * Аксессор для возраста (чтобы не считать его в контроллерах каждый раз)
     * Возвращает null, если дата рождения не указана.
     */
    public function getAgeAttribute(): ?int
    {
        return $this->birth_date?->age;
    }
    
    /**
     * Проверка, заполнен ли профиль достаточно для показа в ленте.
     * (Например, обязательно наличие пола и даты рождения).
     */
    public function isCompleteEnough(): bool
    {
        return !is_null($this->gender) && !is_null($this->birth_date);
    }
}