<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
// use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, SoftDeletes; // , HasApiTokens,

    protected $fillable = [
        'name', 'email', 'password',
        'role', 'status', 'ban_reason', 'banned_until',
        'is_premium', 'premium_expires_at',
        'is_verified', 'has_completed_onboarding',
        'last_seen', 'last_login_at', 'last_login_ip', 'device_id',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_seen' => 'datetime',
            'premium_expires_at' => 'datetime',
            'banned_until' => 'datetime',
            'is_premium' => 'boolean',
            'is_verified' => 'boolean',
            'has_completed_onboarding' => 'boolean',
        ];
    }

    // ============================================
    // СВЯЗИ (ОТНОШЕНИЯ)
    // ============================================

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    public function albums(): HasMany
    {
        return $this->hasMany(Album::class);
    }

    // Твой крутой хелпер для дефолтного альбома
    public function defaultAlbum(): HasOne
    {
        return $this->hasOne(Album::class)->where('is_default', true);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PhotoComment::class);
    }

    // Чаты (Многие ко многим через сводную таблицу)
    public function chats(): BelongsToMany
    {
        return $this->belongsToMany(Chat::class, 'chat_participants')
            ->withPivot(['unread_count', 'last_read_at', 'is_hidden', 'is_muted', 'is_blocked'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function giftsSent(): HasMany
    {
        return $this->hasMany(UserGift::class, 'sender_id');
    }

    public function giftsReceived(): HasMany
    {
        return $this->hasMany(UserGift::class, 'receiver_id');
    }

    public function transactions(): HasMany  
    {
        return $this->hasMany(Transaction::class);
    }

    public function subscriptions(): HasMany 
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function reportsMade(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    public function reportsAgainst(): HasMany
    {
        return $this->hasMany(Report::class, 'reported_id');
    }

    public function swipesGiven(): HasMany
    {
        return $this->hasMany(Swipe::class, 'user_id');
    }

    public function swipesReceived(): HasMany
    {
        return $this->hasMany(Swipe::class, 'target_user_id');
    }

    // В нашей БД user1_id всегда меньше user2_id. 
    // Поэтому матчи делятся на две связи (где я инициатор записи, и где собеседник).
    public function matchesAsUser1(): HasMany
    {
        return $this->hasMany(UserMatch::class, 'user1_id');
    }

    public function matchesAsUser2(): HasMany
    {
        return $this->hasMany(UserMatch::class, 'user2_id');
    }    

    // Просмотры
    public function profileViewers() 
    { 
        return $this->hasMany(ProfileView::class, 'viewed_id'); 
    }

    // Кого заблокировали
    public function blockedUsers() 
    { 
        return $this->hasMany(UserBlock::class, 'blocker_id'); 
    } 

    // Кто заблокировал
    public function blockers() 
    { 
        return $this->hasMany(UserBlock::class, 'blocked_id'); 
    } 

    // верифицированные пользователи
    public function verifications() 
    { 
        return $this->hasMany(Verification::class); 
    }

    // ============================================
    // СКОПЫ (SCOPES)
    // ============================================

    // Выводить только обычных юзеров (исключать админов/модераторов)
    public function scopeExcludeStaff($query)
    {
        return $query->where('role', 'user');
    }

    // Выводить только активных (не забаненных и не деактивированных)
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // ============================================
    // АКСЕССОРЫ И ХЕЛПЕРЫ
    // ============================================

    /**
     * Проверка, является ли пользователь сотрудником (для админки).
     */
    public function isStaff(): bool
    {
        return in_array($this->role, ['admin', 'moderator', 'support']);
    }

    public function isBanned(): bool
    {
        if ($this->status !== 'banned') {
            return false;
        }
        // Если banned_until null — бан вечный. Если есть дата — проверяем, истек ли он.
        return is_null($this->banned_until) || $this->banned_until->isFuture();
    }

    /**
     * Проверка активной VIP-подписки.
     */
    public function getHasActivePremiumAttribute(): bool
    {
        return $this->is_premium 
            && !is_null($this->premium_expires_at) 
            && $this->premium_expires_at->isFuture();
    }

    /**
     * Проверка "Кто онлайн" (был за последние 5 минут).
     */
    public function getIsOnlineAttribute(): bool
    {
        return $this->last_seen && $this->last_seen->gt(now()->subMinutes(5));
    }

    /**
     * Получить URL аватарки.
     * Если фотки загружены (eager loaded) — берет из памяти.
     * Если нет — возвращаем пустую строку, чтобы <x-avatar> отрисовал заглушку с инициалами.
     */
    public function getAvatarUrlAttribute(): string
    {
        if ($this->relationLoaded('photos')) {
            $photos = $this->getRelation('photos');
            if ($photos && $photos->isNotEmpty()) {
                $photo = $photos->firstWhere('is_primary', true) ?? $photos->first();
                return $photo->path_thumb ?: $photo->path_medium ?: '';
            }
        }
        return '';
    }

    // ============================================
    // ПРОКСИ-АКСЕССОРЫ ДЛЯ НАСТРОЕК (Из твоей старой модели)
    // ============================================

    public function getLocaleAttribute(): string
    {
        return $this->preferences?->locale ?? config('app.locale');
    }

    public function getThemeAttribute(): string
    {
        return $this->preferences?->theme ?? 'light';
    }

    public function getPushEnabledAttribute(): bool
    {
        return $this->preferences?->push_enabled ?? true;
    }

    public function getEmailSettingsAttribute(): array
    {
        if (!$this->preferences) {
            return [
                'on_message' => true, 'on_like' => true, 'on_view' => false,
                'on_photo_moderated' => true, 'on_report' => true,
                'on_ban' => true, 'on_broadcast' => true
            ];
        }
        return $this->preferences->email_settings;
    }


    // ============================================
    // СОБЫТИЯ МОДЕЛИ (Booted)
    // ============================================

    protected static function booted()
    {
        static::created(function (User $user) {
            // При создании юзера — сразу создаем его пустой профиль и дефолтные настройки!
            $user->profile()->create();
            $user->preferences()->create();
            
            // Создаем альбом по умолчанию (предполагаем, что в модели Album есть этот статический метод)
            Album::createDefaultForUser($user);
        });
    }
}