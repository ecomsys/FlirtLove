<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeoLocation extends Model
{
    protected $fillable = [
        'parent_id',
        'type',
        'name',
        'iso_code',
        'is_registration_blocked',
        'is_feed_blocked',
    ];

    protected $casts = [
        'is_registration_blocked' => 'boolean',
        'is_feed_blocked' => 'boolean',
    ];

    // ============================================
    // СВЯЗИ (Дерево)
    // ============================================

    public function parent(): BelongsTo
    {
        return $this->belongsTo(GeoLocation::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(GeoLocation::class, 'parent_id');
    }

    // ============================================
    // СКОПЫ
    // ============================================

    public function scopeCountries($query)
    {
        return $query->where('type', 'country');
    }

    public function scopeRegistrationBlocked($query)
    {
        return $query->where('is_registration_blocked', true);
    }

    public function scopeFeedBlocked($query)
    {
        return $query->where('is_feed_blocked', true);
    }
}