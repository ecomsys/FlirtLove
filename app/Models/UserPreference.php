<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class UserPreference extends Model
{
    protected $fillable = [
        'user_id', 
        'locale', 'theme',
        'preferred_age_min', 'preferred_age_max', 'preferred_gender', 'preferred_distance_km',
        'search_filters',
        'chat_filter_enabled', 'chat_filter_settings',
        'is_invisible', 'hide_intimate', 'disable_photo_comments', 'hide_from_search',
        'superlikes_remaining', 'superlikes_reset_at', 'credits', // Новые поля лимитов и валюты
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
        'superlikes_remaining' => 'integer',
        'superlikes_reset_at' => 'datetime',
        'credits' => 'integer',
        'push_enabled' => 'boolean',
        'email_enabled' => 'boolean',
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
    // АКСЕССОРЫ С ДЕФОЛТАМИ 
    // ============================================

        /**
     * Расширенные фильтры поиска (с дефолтами).
     */
    public function getSearchFiltersAttribute(): array
    {
        // Читаем напрямую из сырых атрибутов, минуя аксессоры и касты (защита от рекурсии)
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

    /**
     * Настройки фильтра чата (с дефолтами).
     * ВНИМАНИЕ: Имя метода строго getChatFilterSettingsAttribute (так как в БД поле chat_filter_settings)
     */
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

    /**
     * Настройки email-уведомлений (с дефолтами).
     */
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

    // ============================================
    // ХЕЛПЕРЫ ДЛЯ ВАЛЮТЫ И ЛИМИТОВ
    // ============================================

    /**
     * Начислить кредиты юзеру (за покупку или бонус).
     */
    public function addCredits(int $amount): bool
    {
        if ($amount <= 0) return false;
        
        // Делаем через increment, чтобы избежать состояния гонки (Race Condition)
        return DB::table('user_preferences')
            ->where('id', $this->id)
            ->increment('credits', $amount);
    }

    /**
     * Списать кредиты у юзера (для покупки подарков).
     * Возвращает true, если хватило баланса, false — если не хватило.
     */
    public function spendCredits(int $amount): bool
    {
        if ($amount <= 0) return false;

        // Обновляем только в том случае, если текущий баланс больше или равен сумме списания.
        // Это атомарный запрос, который защищает от отрицательного баланса при одновременных запросах.
        $affected = DB::table('user_preferences')
            ->where('id', $this->id)
            ->where('credits', '>=', $amount)
            ->decrement('credits', $amount);

        if ($affected) {
            // Обновляем модель в памяти, чтобы фронт сразу видел новый баланс
            $this->credits -= $amount;
        }

        return (bool) $affected;
    }

    /**
     * Сброс лимита суперлайков (вызывается крон-задачей раз в сутки).
     */
    public function resetSuperlikes(): void
    {
        $this->update([
            'superlikes_remaining' => 5, // Дефолтный лимит (или берем из настроек тарифа)
            'superlikes_reset_at' => now()->addDay(),
        ]);
    }
}