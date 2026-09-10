<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id', 
        'gender', 'birth_date', 'dating_goal', 'city_id', 'country_id',
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
        'self_portrait' => 'array',
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

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    // ============================================
    // ГЕОЛОКАЦИЯ (PostGIS)
    // ============================================

    /**
     * Безопасно обновить гео-точку через PostGIS.
     * Используем newQuery()->update(), чтобы не сохранить случайно другие "грязные" поля модели.
     */
    public function setLocation(float $lat, float $lng): void
    {
        $this->newQuery()
            ->where('id', $this->id)
            ->update([
                'location' => DB::raw("ST_SetSRID(ST_MakePoint({$lng}, {$lat}), 4326)::geography")
            ]);
        
        // Обновляем атрибут в памяти текущей модели, чтобы он не был stale
        $this->location = DB::raw("ST_SetSRID(ST_MakePoint({$lng}, {$lat}), 4326)::geography");
    }

    /**
     * Безопасный способ получить координаты через SQL-селект.
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
     * Фильтр по полу
     */
    public function scopeOfGender($query, ?string $gender)
    {
        if (!$gender || $gender === 'any') {
            return $query;
        }
        return $query->where('gender', $gender);
    }

    // ============================================
    // СКОПЫ ДЛЯ ПОИСКА (МАТЧИНГА)
    // ============================================

    /**
     * Фильтр по возрасту (Киллер-фича для индексов).
     * Конвертируем возраст в дату рождения, чтобы использовать индекс birth_date!
     */
    public function scopeBetweenAges($query, ?int $minAge = 18, ?int $maxAge = 99)
    {
        $minAge = $minAge ?? 18;
        $maxAge = $maxAge ?? 99;
        
        if ($minAge > $maxAge) {
            [$minAge, $maxAge] = [$maxAge, $minAge];
        }

        // Вычисляем даты: кому на сегодня уже есть minAge лет, и кому не больше maxAge лет
        $maxDate = Carbon::now()->subYears($minAge)->format('Y-m-d'); // Самая поздняя дата рождения (самые молодые)
        $minDate = Carbon::now()->subYears($maxAge)->format('Y-m-d'); // Самая ранняя дата рождения (самые старые)

        // Использует ИНДЕКС birth_date!
        return $query->whereBetween('birth_date', [$minDate, $maxDate]);
    }


    // ============================================
    // АКСЕССОРЫ И ХЕЛПЕРЫ
    // ============================================
   
    
    /**
     * Вычисляем возраст на лету (для UI).
     */
    public function getAgeAttribute(): ?int
    {
        return $this->birth_date ? Carbon::parse($this->birth_date)->age : null;
    }

    /**
     * Проверка, заполнен ли профиль достаточно для показа в ленте.
     */
    public function isCompleteEnough(): bool
    {
        return !is_null($this->gender) && !is_null($this->birth_date);
    }
}