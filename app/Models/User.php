<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;

#[Fillable([
    'name', 'email', 'password',
    'is_admin', 'is_banned', 'is_premium', 'is_verified', 
    'has_completed_onboarding', 'is_deactivated',
    'superlikes_remaining', 'last_login_at', 'last_login_ip', 
    'last_seen', 'premium_expires_at'
])]
#[Hidden(['password', 'remember_token'])]

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_seen' => 'datetime',
            'premium_expires_at' => 'datetime',
            
            // Булевы флаги
            'is_admin' => 'boolean',
            'is_banned' => 'boolean',
            'is_premium' => 'boolean',
            'is_verified' => 'boolean',
            'has_completed_onboarding' => 'boolean',
            'is_deactivated' => 'boolean',
            
            // Счетчики
            'superlikes_remaining' => 'integer',
        ];
    }

    public function routeNotificationForMail($notification): string
    {
        return $this->email;
    }

    // ============================================
    // СВЯЗИ (ОТНОШЕНИЯ)
    // ============================================

    // Наши новые главные связи
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    // Связи из старой модели (пока оставляем как есть)
    public function chats() 
    {
        // Позже мы перепишем это на belongsToMany, но пока пусть работает
        return Chat::where('user1_id', $this->id)->orWhere('user2_id', $this->id);
    }
    
    public function chatParticipants(): HasMany
    {
        return $this->hasMany(ChatParticipant::class);
    }

    public function albums(): HasMany
    {
        return $this->hasMany(Album::class);
    }

    public function defaultAlbum(): HasOne
    {
        return $this->hasOne(Album::class)->where('is_default', true);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function photoComments(): HasMany
    {
        return $this->hasMany(PhotoComment::class);
    }

    public function receivedReports(): HasMany
    {
        return $this->hasMany(Report::class, 'reported_user_id');
    }

    public function sentReports(): HasMany
    {
        return $this->hasMany(Report::class, 'user_id');
    }

    public function swipesGiven(): HasMany
    {
        return $this->hasMany(Swipe::class, 'user_id');
    }

    public function swipesReceived(): HasMany
    {
        return $this->hasMany(Swipe::class, 'target_user_id');
    }

    public function matches() // Пока оставляем, потом перепишем
    {
        return UserMatch::where('user1_id', $this->id)->orWhere('user2_id', $this->id);
    }

    // ============================================
    // ХЕЛПЕРЫ И СКОПЫ
    // ============================================

    public function getLocaleAttribute(): string
    {
        return $this->preferences?->locale ?? config('app.locale');
    }

    public function getThemeAttribute(): string
    {
        return $this->preferences?->theme ?? 'light';
    }

    public function scopeExcludeAdmins($query)
    {
        return $query->where('is_admin', false);
    }

    public function getHasActivePremiumAttribute(): bool
    {
        return $this->is_premium && ($this->premium_expires_at === null || $this->premium_expires_at->isFuture());
    }

    public function getIsOnlineAttribute(): bool
    {
        return $this->last_seen && $this->last_seen->gt(now()->subMinutes(5));
    }

    // Удобные прокси-аксессоры, чтобы не писать $user->profile->bio в коде
    // ВНИМАНИЕ: Используй их только там, где профиль уже загружен (eager loaded)!
    public function getBioAttribute()
    {
        return $this->profile?->bio;
    }

    public function getGenderAttribute()
    {
        return $this->profile?->gender;
    }
    
     
      /**
     * Получить URL аватарки.
     * Если фотки загружены (eager loaded) — берет из памяти.
     * Если нет или они пустые — возвращаем пустую строку,
     * чтобы компонент <x-avatar> отрисовал красивую заглушку с инициалами.
     */
    public function getAvatarUrlAttribute(): string
    {
        // Проверяем, загружена ли связь в память
        if ($this->relationLoaded('photos')) {
            $photos = $this->getRelation('photos');
            if ($photos && $photos->isNotEmpty()) {
                $photo = $photos->first();
                // Используем ?: чтобы отлавливать пустые строки
                return $photo->thumb_url ?: $photo->medium_url ?: '';
            }
        }
        
        // Возвращаем пустоту, чтобы <x-avatar> сделал заглушку из имени
        return '';
    }

    // ============================================
    // СОБЫТИЯ МОДЕЛИ
    // ============================================

    protected static function booted()
    {
        static::created(function (User $user) {
            // При создании юзера — сразу создаем его пустой профиль и дефолтные настройки!
            $user->profile()->create();
            $user->preferences()->create();
            
            // Создаем альбом по умолчанию
            Album::createDefaultForUser($user);
        });
    }
}