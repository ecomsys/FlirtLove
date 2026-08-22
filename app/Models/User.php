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

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'phone',
        'role', 'status', 'ban_reason', 'banned_until',
        
        'is_premium', 'premium_expires_at',
        'is_vip', 'vip_expires_at',

        'is_verified', 'has_completed_onboarding',
        'last_seen', 'last_login_at', 'last_login_ip', 'device_id', 'device_os'
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_seen' => 'datetime',            
            'banned_until' => 'datetime',
            'is_premium' => 'boolean',
            'premium_expires_at' => 'datetime',
            'is_vip' => 'boolean',
            'vip_expires_at' => 'datetime',
            'is_verified' => 'boolean',
            'has_completed_onboarding' => 'boolean',            
        ];
    }

    // ============================================
    // СВЯЗИ (ОТНОШЕНИЯ)
    // ============================================
        // Связи
    public function subscriptions(): HasMany { return $this->hasMany(UserSubscription::class); }
    public function boosts(): HasMany { return $this->hasMany(UserBoost::class); }


    public function adminLogs(): HasMany
    {
        return $this->hasMany(AdminLog::class, 'admin_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    // НОВАЯ СВЯЗЬ: Кошелек и лимиты
    public function balance(): HasOne
    {
        return $this->hasOne(UserBalance::class);
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

    public function comments(): HasMany
    {
        return $this->hasMany(PhotoComment::class);
    }

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

    public function matchesAsUser1(): HasMany
    {
        return $this->hasMany(UserMatch::class, 'user1_id');
    }

    public function matchesAsUser2(): HasMany
    {
        return $this->hasMany(UserMatch::class, 'user2_id');
    }    

    public function profileViewers(): HasMany 
    { 
        return $this->hasMany(ProfileView::class, 'viewed_id'); 
    }

    public function blockedUsers(): HasMany 
    { 
        return $this->hasMany(UserBlock::class, 'blocker_id'); 
    } 

    public function blockers(): HasMany 
    { 
        return $this->hasMany(UserBlock::class, 'blocked_id'); 
    } 

    public function verifications(): HasMany 
    { 
        return $this->hasMany(Verification::class); 
    }

    // Аксессоры для чистой проверки в коде (O(1) скорость)
    public function getHasActivePremiumAttribute(): bool {
        return $this->is_premium && $this->premium_expires_at?->isFuture();
    }
    public function getHasActiveVipAttribute(): bool {
        return $this->is_vip && $this->vip_expires_at?->isFuture();
    }

    // ============================================
    // СКОПЫ (SCOPES)
    // ============================================

    public function scopeExcludeStaff($query)
    {
        return $query->where('role', 'user');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // ============================================
    // АКСЕССОРЫ И ХЕЛПЕРЫ
    // ============================================

    public function isStaff(): bool
    {
        return in_array($this->role, ['admin', 'moderator', 'support']);
    }

    public function isBanned(): bool
    {
        if ($this->status !== 'banned') {
            return false;
        }
        return is_null($this->banned_until) || $this->banned_until->isFuture();
    } 

    public function getIsOnlineAttribute(): bool
    {
        return $this->last_seen && $this->last_seen->gt(now()->subMinutes(5));
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->relationLoaded('photos')) {
            $photos = $this->getRelation('photos');
            
            if ($photos && $photos->isNotEmpty()) {
                $photo = $photos->firstWhere(fn($p) => $p->is_primary && $p->status === 'approved')
                    ?? $photos->firstWhere('status', 'approved')
                    ?? $photos->firstWhere('is_primary', true)
                    ?? $photos->first();
                
                return $photo ? ($photo->thumb_url ?: '') : '';
            }
        }
        
        return '';
    }

    // Прокси-аксессоры для настроек (остаются тут)
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

    public function getEmailEnabledAttribute(): bool
    {
        return $this->preferences?->email_enabled ?? true;
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

    // Юзеры, на которых я подписан (их дневники я читаю)
    public function subscribedAuthors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'diary_subscriptions', 'subscriber_id', 'author_id');
    }

    // Мои подписчики (кто читает мои дневники)
    public function diarySubscribers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'diary_subscriptions', 'author_id', 'subscriber_id');
    }

    // ============================================
    // СОБЫТИЯ МОДЕЛИ (Booted)
    // ============================================

    protected static function booted()
    {
        static::created(function (User $user) {
            $user->profile()->create();
            $user->preferences()->create();
            
            // Создаем кошелек с дефолтными значениями (наследуется из миграции)
            $user->balance()->create();
            
            Album::createDefaultForUser($user);
        });
    }
}