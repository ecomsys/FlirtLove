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
    // АКСЕССОРЫ ДЛЯ JSON С ДЕФОЛТАМИ (Из твоей старой модели)
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
     * Структура строго соответствует чекбоксам в UI (как на LovePlanet).
     */
    public function getEmailSettingsAttribute(): array
    {
        $settings = $this->attributes['email_settings'] ?? null;
        if (is_string($settings)) {
            $settings = json_decode($settings, true);
        }

        return array_merge([
            // === МГНОВЕННЫЕ УВЕДОМЛЕНИЯ ===
            'on_message'    => true,  // Чекбокс: Новые сообщения
            'on_like'       => true,  // Чекбокс: Новые симпатии (лайки и мэтчи)
            'on_view'       => false, // Чекбокс: Новые просмотры (выкл по умолчанию, чтобы не спамить)
            'on_gift'       => true,  // Чекбокс: Новые подарки
            'on_event'      => true,  // Чекбокс: Новые события (Жалобы рассмотрены, чат удален модерацией, фото отклонено)
            'on_broadcast'  => true,  // Чекбокс: Подписка «Новости» (Массовые рассылки от админа)
            
            // === ДАЙДЖЕСТЫ (Отправляются крон-задачей раз в день/неделю) ===
            'sub_new_faces' => true,  // Чекбокс: Подписка «Новые лица»
            'sub_popular'   => false, // Чекбокс: Подписка «Популярные пользователи»
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