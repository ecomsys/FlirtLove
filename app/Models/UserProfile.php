<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id', 
        'gender', 'birth_date', 'dating_goal', 'city', 'country',
        'headline', 'bio', 'looking_for', 'interests', 'self_portrait',
        'body_type', 'eye_color', 'hair_color', 'height', 'weight',
        'relationship_status', 'children_status', 'pets', 'housing', 'has_car', 'smoking', 'alcohol',
        'zodiac_sign',
        'body_decorations', 'languages', 'sports',
        'education', 'occupation', 'institution', 'institution_year', 'activity', 'position',
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
     * Достаем Latitude из PostGIS точки.
     * PDO обычно возвращает geography как строку в формате WKB (hex) или "POINT(lng lat)".
     * Если использовать пакет вроде mstaack/laravel-postgis, он сам распарсит в объект.
     * Но для нативного Laravel парсим строку.
     */
    public function getLatitudeAttribute(): ?float
    {
        if (empty($this->location)) return null;
        
        $locationStr = (string) $this->location;
        if (str_starts_with($locationStr, 'POINT(')) {
            $coords = sscanf($locationStr, "POINT(%f %f)");
            return $coords[1] ?? null; // Lat идет вторым
        }
        
        return null; 
    }

    /**
     * Достаем Longitude из PostGIS точки
     */
    public function getLongitudeAttribute(): ?float
    {
        if (empty($this->location)) return null;
        
        $locationStr = (string) $this->location;
        if (str_starts_with($locationStr, 'POINT(')) {
            $coords = sscanf($locationStr, "POINT(%f %f)");
            return $coords[0] ?? null; // Lng идет первым
        }
        
        return null; 
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
     * Фильтр по возрасту (от и до)
     */
    public function scopeBetweenAges($query, ?int $minAge = 18, ?int $maxAge = 99)
    {
        $minDate = now()->subYears($maxAge)->format('Y-m-d');
        $maxDate = now()->subYears($minAge)->format('Y-m-d');
        
        // Ищем тех, чья дата рождения попадает в диапазон
        return $query->whereBetween('birth_date', [$minDate, $maxDate]);
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