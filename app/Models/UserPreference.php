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
        'push_enabled', 'email_settings'
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
        'email_settings' => 'array',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ============================================
    // АКСЕССОРЫ ДЛЯ JSON С ДЕФОЛТАМИ
    // (Переехали из старой модели User)
    // ============================================

    /**
     * Расширенные фильтры поиска.
     * Если в БД пусто, подставляем дефолты.
     */
    public function getSearchFiltersAttribute(): array
    {
        $filters = $this->attributes['search_filters'] ?? null;
        if (is_string($filters)) {
            $filters = json_decode($filters, true);
        }

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

    /**
     * Настройки фильтра чата (кто может писать).
     */
    public function getChatFiltersAttribute(): array
    {
        $filters = $this->attributes['chat_filter_settings'] ?? null;
        if (is_string($filters)) {
            $filters = json_decode($filters, true);
        }

        return array_merge([
            'gender' => 'any', 
            'age_from' => 18, 
            'age_to' => 99,
            'is_verified_only' => false, 
            'is_premium_only' => false
        ], is_array($filters) ? $filters : []);
    }

    /**
     * Настройки email-уведомлений по категориям.
     */
    public function getEmailSettingsAttribute(): array
    {
        $settings = $this->attributes['email_settings'] ?? null;
        if (is_string($settings)) {
            $settings = json_decode($settings, true);
        }

        return array_merge([
            'on_message' => true, 
            'on_like' => true, 
            'on_view' => false,
            'on_photo_moderated' => true, 
            'on_report' => true,
            'on_ban' => true, 
            'on_broadcast' => true
        ], is_array($settings) ? $settings : []);
    }
}