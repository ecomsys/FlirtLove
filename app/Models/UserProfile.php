<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id', 
        'gender', 'birth_date', 'dating_goal', 'city', 
        'status', 'bio', 'looking_for', 'interests',
        'body_type', 'eye_color', 'hair_color', 'height', 'weight',
        'relationship_status', 'children_status', 'pets', 'housing', 'has_car', 'smoking', 'alcohol',
        'body_decorations', 'languages', 'sports',
        'education', 'occupation', 'institution', 'institution_year', 'activity', 'position',
        'zodiac_sign',
        'location', 'address', 'country',
        'profile_views', 'likes_count'
    ];

    protected $casts = [
        'birth_date' => 'date',
        'interests' => 'array',
        
        // Множественный выбор (JSON-массивы с ID)
        'body_decorations' => 'array',
        'languages' => 'array',
        'sports' => 'array',
        
        // Счетчики
        'profile_views' => 'integer',
        'likes_count' => 'integer',
        
        // Год выпуска
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
    // ГЕОЛОКАЦИЯ (Переехала из User)
    // ============================================

    /**
     * Обновить гео-точку через PostGIS
     */
    public function setLocation(float $lat, float $lng): void
    {
        // Используем DB::raw для нативной функции PostGIS
        $this->location = DB::raw("ST_SetSRID(ST_MakePoint({$lng}, {$lat}), 4326)");
        $this->save();
    }

    /**
     * Достаем Latitude из PostGIS точки
     */
    public function getLatitudeAttribute(): ?float
    {
        if (!$this->location) return null;
        
        // Если PDO возвращает строку формата "POINT(lng lat)", парсим её
        if (is_string($this->location)) {
            $coords = sscanf($this->location, "POINT(%f %f)");
            return $coords[1]; // Lat идет второй
        }
        
        return null; // Если объект, зависит от драйвера БД. Обычно sscanf решает проблему.
    }

    /**
     * Достаем Longitude из PostGIS точки
     */
    public function getLongitudeAttribute(): ?float
    {
        if (!$this->location) return null;
        
        if (is_string($this->location)) {
            $coords = sscanf($this->location, "POINT(%f %f)");
            return $coords[0]; // Lng идет первой
        }
        
        return null;
    }

    

    /**
     * Скоуп для поиска анкет рядом
     */
    public function scopeNearby($query, float $lat, float $lng, int $radius = 50)
    {
        return $query->whereNotNull('location')
            ->whereRaw(
                "ST_DWithin(location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)", 
                [$lng, $lat, $radius * 1000]
            );
    }

    // ============================================
    // ХЕЛПЕРЫ
    // ============================================

    /**
     * Безопасное увеличение просмотров профиля.
     * Используем increment, чтобы не перезаписывать другие поля при одновременных запросах.
     */
    public function incrementViews(): void
    {
        $this->increment('profile_views');
    }

    /**
     * Аксессор для возраста (чтобы не считать его в контроллерах каждый раз)
     */
    public function getAgeAttribute(): ?int
    {
        return $this->birth_date?->age;
    }
}