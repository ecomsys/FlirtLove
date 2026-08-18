<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    protected $fillable = [
        'user_id', 
        'locale', 'theme',
        'preferred_age_min', 'preferred_age_max', 'preferred_gender', 'preferred_distance_km',
        'search_filters',
        'chat_filter_enabled', 'chat_filter_settings',
        'is_invisible', 'hide_intimate', 'disable_photo_comments', 'hide_from_search',
        'push_enabled', 'email_enabled', 'email_settings'
    ];

    protected $casts = [
        'preferred_age_min' => 'integer',
        'preferred_age_max' => 'integer',
        'preferred_distance_km' => 'integer',
        'search_filters' => 'array',
        'chat_filter_enabled' => 'boolean',
        'chat_filter_settings' => 'array',
        'is_invisible' => 'boolean',
        'hide_intimate' => 'boolean',
        'disable_photo_comments' => 'boolean',
        'hide_from_search' => 'boolean',
        'push_enabled' => 'boolean',
        'email_enabled' => 'boolean',
        'email_settings' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ============================================
    // АКСЕССОРЫ С ДЕФОЛТАМИ 
    // ============================================

    public function getSearchFiltersAttribute(): array
    {
        $raw = $this->attributes['search_filters'] ?? null;
        $filters = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : []);

        return array_merge([
            'body_type' => null, 
            'eye_color' => null, 
            'hair_color' => null,
            'height_from' => null, 
            'height_to' => null, 
            'education' => null,
            'zodiac_sign' => null, 
            'is_verified_only' => false, 
            'is_premium_only' => false
        ], is_array($filters) ? $filters : []);
    }

    public function getChatFilterSettingsAttribute(): array
    {
        $raw = $this->attributes['chat_filter_settings'] ?? null;
        $filters = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : []);

        return array_merge([
            'gender' => 'any', 
            'age_from' => 18, 
            'age_to' => 99,
            'is_verified_only' => false, 
            'is_premium_only' => false
        ], is_array($filters) ? $filters : []);
    }

    public function getEmailSettingsAttribute(): array
    {
        $raw = $this->attributes['email_settings'] ?? null;
        $settings = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : []);

        return array_merge([
            'on_message'    => true,  
            'on_like'       => true,  
            'on_view'       => false, 
            'on_gift'       => true,  
            'on_event'      => true,  
            'on_broadcast'  => true,  
            'sub_new_faces' => true,  
            'sub_popular'   => false, 
        ], is_array($settings) ? $settings : []);
    }
}