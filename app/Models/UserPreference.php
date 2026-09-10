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
        'push_enabled', 'email_enabled', 'email_settings',
        'show_quick_replies',
        'chat_widget_enabled',
        'chat_sound_enabled',
        'visibility_gender',
        'visibility_age_min',
        'visibility_age_max',
        'push_auto_recommendations',
        'email_auto_recommendations',
        'allow_auto_messages',
    ];

    protected $casts = [
        'preferred_age_min' => 'integer',
        'preferred_age_max' => 'integer',
        'preferred_distance_km' => 'integer',
        'chat_filter_enabled' => 'boolean',
        'is_invisible' => 'boolean',
        'hide_intimate' => 'boolean',
        'disable_photo_comments' => 'boolean',
        'hide_from_search' => 'boolean',
        'show_quick_replies' => 'boolean',
        'chat_widget_enabled' => 'boolean',
        'chat_sound_enabled' => 'boolean',
        'push_enabled' => 'boolean',
        'email_enabled' => 'boolean',
        'visibility_age_min' => 'integer',
        'visibility_age_max' => 'integer',
        'push_auto_recommendations' => 'boolean',
        'email_auto_recommendations' => 'boolean',
        'allow_auto_messages' => 'boolean',
        // Убрали search_filters, chat_filter_settings и email_settings из кастов,
        // так как для них написаны кастомные аксессоры с array_merge.
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ============================================
    // СКОПЫ ДЛЯ АВТОМАТИЗАЦИИ И УВЕДОМЛЕНИЙ
    // ============================================

    public function scopeWantsPushRecommendations($query)
    {
        return $query->where('push_enabled', true)
                     ->where('push_auto_recommendations', true);
    }

    public function scopeWantsEmailRecommendations($query)
    {
        return $query->where('email_enabled', true)
                     ->where('email_auto_recommendations', true);
    }

    public function scopeWantsAutoMessages($query)
    {
        return $query->where('allow_auto_messages', true);
    }

    // ============================================
    // АКСЕССОРЫ С ДЕФОЛТАМИ 
    // ============================================

    public function getSearchFiltersAttribute($value): array
    {
        $filters = is_string($value) ? json_decode($value, true) : (is_array($value) ? $value : []);

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
        ], $filters ?? []);
    }

    public function getChatFilterSettingsAttribute($value): array
    {
        $filters = is_string($value) ? json_decode($value, true) : (is_array($value) ? $value : []);

        return array_merge([
            'gender' => 'any', 
            'age_from' => 18, 
            'age_to' => 99,
            'is_verified_only' => false, 
            'is_premium_only' => false
        ], $filters ?? []);
    }

    public function getEmailSettingsAttribute($value): array
    {
        $settings = is_string($value) ? json_decode($value, true) : (is_array($value) ? $value : []);

        return array_merge([
            'on_message'    => true,  
            'on_like'       => true,  
            'on_view'       => false, 
            'on_gift'       => true,  
            'on_event'      => true,  
            'on_broadcast'  => true,  
            'sub_new_faces' => true,  
            'sub_popular'   => false, 
        ], $settings ?? []);
    }
}