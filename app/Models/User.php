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
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

#[Fillable([
    'name',
    'email',
    'password',
    'locale',
    'theme',
    'gender',
    'birth_date',
    'dating_goal',
    'city',
    'bio',
    'looking_for',
    'has_completed_onboarding',
    'is_admin',
    'is_banned',
    'is_premium',
    'last_login_at',
    'last_login_ip',
    'profile_views',
    'likes_count',
    'last_seen',
    'premium_expires_at',
    'height',
    'weight',
    'education',
    'occupation',
    'zodiac_sign',
    'interests',
    'latitude',
    'longitude',
    'address',
    'country',
    'preferred_age_min',
    'preferred_age_max',
    'preferred_gender',
    'preferred_distance_km',
    'superlikes_remaining',
    'chat_filter_enabled',
    'chat_filter_settings',
    'search_filters',
    'is_invisible',
    'hide_intimate',
    'disable_photo_comments',
    'hide_from_search',
    'is_deactivated',
    'push_enabled',
    'email_settings',
    'profile_details'
])]
#[Hidden(['password', 'remember_token'])]

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'birth_date' => 'date',
            'has_completed_onboarding' => 'boolean',
            'is_admin' => 'boolean',
            'is_banned' => 'boolean',
            'is_premium' => 'boolean',
            'is_verified' => 'boolean',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_seen' => 'datetime',
            'premium_expires_at' => 'datetime',
            'interests' => 'array',
            'chat_filter_settings' => 'array',
            'search_filters' => 'array',
            'email_settings' => 'array',
            'profile_details' => 'array',
            'preferred_age_min' => 'integer',
            'preferred_age_max' => 'integer',
            'preferred_distance_km' => 'integer',
            'superlikes_remaining' => 'integer',
            'profile_views' => 'integer',
            'likes_count' => 'integer',
            'is_invisible' => 'boolean',
            'hide_intimate' => 'boolean',
            'disable_photo_comments' => 'boolean',
            'hide_from_search' => 'boolean',
            'push_enabled' => 'boolean',
            'is_deactivated' => 'boolean',
        ];
    }

    public function routeNotificationForMail($notification): string
    {
        return $this->email;
    }

    public function scopeExcludeAdmins($query)
    {
        return $query->where('is_admin', false);
    }

    // === ХЕЛПЕРЫ ===
    public function getHasActivePremiumAttribute(): bool
    {
        return $this->is_premium && ($this->premium_expires_at === null || $this->premium_expires_at->isFuture());
    }
    public function getIsOnlineAttribute(): bool
    {
        return $this->last_seen && $this->last_seen->gt(now()->subMinutes(5));
    }

    public function getSearchFiltersAttribute(): array
    {
        $filters = $this->attributes['search_filters'] ?? null;
        if (is_string($filters)) {
            $filters = json_decode($filters, true);
        }

        return array_merge([
            'height_from' => null, 'height_to' => null, 'education' => null,
            'zodiac_sign' => null, 'is_verified_only' => false, 'is_premium_only' => false
        ], is_array($filters) ? $filters : []);
    }

    public function getChatFiltersAttribute(): array
    {
        $filters = $this->attributes['chat_filter_settings'] ?? null;
        if (is_string($filters)) {
            $filters = json_decode($filters, true);
        }

        return array_merge([
            'gender' => 'any', 'age_from' => 18, 'age_to' => 99,
            'is_verified_only' => false, 'is_premium_only' => false
        ], is_array($filters) ? $filters : []);
    }

    public function getEmailSettingsAttribute(): array
    {
        $settings = $this->attributes['email_settings'] ?? null;
        if (is_string($settings)) {
            $settings = json_decode($settings, true);
        }

        return array_merge([
            'on_message' => true, 'on_like' => true, 'on_view' => false,
            'on_photo_moderated' => true, 'on_report' => true,
            'on_ban' => true, 'on_broadcast' => true
        ], is_array($settings) ? $settings : []);
    }

    public function getProfileDetailsAttribute(): array
    {
        $details = $this->attributes['profile_details'] ?? null;
        if (is_string($details)) {
            $details = json_decode($details, true);
        }

        return array_merge([
            'body_type' => 0, 'eye_color' => 0, 'hair_color' => 0, 'body_decorations' => [],
            'relationship_status' => 0, 'children_status' => 0, 'pets' => 0, 'housing' => 0,
            'has_car' => 0, 'education_level' => 0, 'institution' => null, 'graduation_year' => null,
            'industry' => null, 'occupation' => null, 'income' => 0, 'smoking' => 0,
            'alcohol' => 0, 'languages' => [], 'sports' => []
        ], is_array($details) ? $details : []);
    }
    
    // === ЧАТЫ ===
    public function chats(): Builder
    {
        return Chat::where('user1_id', $this->id)->orWhere('user2_id', $this->id);
    }
    public function chatParticipants(): HasMany
    {
        return $this->hasMany(ChatParticipant::class);
    }

    // === АЛЬБОМЫ, ФОТО ===
    public function albums(): HasMany
    {
        return $this->hasMany(Album::class);
    }
    public function defaultAlbum(): HasOne
    {
        return $this->hasOne(Album::class)->where('is_default', true);
    }

    protected static function booted()
    {
        static::created(function (User $user) {
            Album::createDefaultForUser($user);
        });
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->relationLoaded('photos')) {
            $primary = $this->photos->firstWhere('is_primary', true);
        } else {
            $primary = $this->photos()->where('is_primary', true)->first();
        }
        return $primary ? $primary->thumb_url : null;
    }

    public function getPrimaryPhotoAttribute()
    {
        if ($this->relationLoaded('photos')) {
            return $this->photos->firstWhere('is_primary', true);
        }
        return $this->photos()->where('is_primary', true)->first();
    }

    public function photoComments(): HasMany
    {
        return $this->hasMany(PhotoComment::class);
    }
    public function approvedPhotoComments(): HasMany
    {
        return $this->hasMany(PhotoComment::class)->where('status', 'approved');
    }
    public function hasCompletedOnboarding(): bool
    {
        return $this->has_completed_onboarding || $this->photos()->count() > 0;
    }

    // === ЖАЛОБЫ ===
    public function receivedReports(): HasMany
    {
        return $this->hasMany(Report::class, 'reported_user_id');
    }
    public function sentReports(): HasMany
    {
        return $this->hasMany(Report::class, 'user_id');
    }

    public function getReportsReceivedCountAttribute(): int
    {
        return $this->relationLoaded('receivedReports') ? $this->receivedReports->count() : $this->receivedReports()->count();
    }
    public function getReportsSentCountAttribute(): int
    {
        return $this->relationLoaded('sentReports') ? $this->sentReports->count() : $this->sentReports()->count();
    }

    // === ГЕОЛОКАЦИЯ ===
    public function setLocation(float $lat, float $lng): void
    {
        $this->location = DB::raw("ST_SetSRID(ST_MakePoint({$lng}, {$lat}), 4326)");
        $this->latitude = $lat;
        $this->longitude = $lng;
        $this->save();
    }

    public function scopeNearby($query, float $lat, float $lng, int $radius = 50)
    {
        return $query->whereNotNull('location')->whereRaw("ST_DWithin(location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)", [$lng, $lat, $radius * 1000]);
    }

    // === СВАЙПЫ И МАТЧИ ===
    public function swipesGiven(): HasMany
    {
        return $this->hasMany(Swipe::class, 'user_id');
    }
    public function swipesReceived(): HasMany
    {
        return $this->hasMany(Swipe::class, 'target_user_id');
    }
    public function matches(): Builder
    {
        return UserMatch::where('user1_id', $this->id)->orWhere('user2_id', $this->id);
    }

    // === ЛОГИКА РЕКОМЕНДАЦИЙ ===
    public function getRecommendedUsers(int $limit = 20)
    {
        if (!$this->location) return collect();

        $swipedIds = $this->swipesGiven()->pluck('target_user_id')->toArray();
        $matchedIds = array_merge(UserMatch::where('user1_id', $this->id)->pluck('user2_id')->toArray(), UserMatch::where('user2_id', $this->id)->pluck('user1_id')->toArray());
        $excludeIds = array_merge([$this->id], $swipedIds, $matchedIds);
        $radius = $this->preferred_distance_km ?? 50;
        $filters = $this->search_filters;

        return User::where('is_banned', false)
            ->excludeAdmins()
            ->where('has_completed_onboarding', true)
            ->whereNotIn('id', $excludeIds)
            ->when($this->preferred_age_min, function ($q) {
                $q->where('birth_date', '<=', now()->subYears($this->preferred_age_min)->format('Y-m-d'));
            })
            ->when($this->preferred_age_max, function ($q) {
                $q->where('birth_date', '>=', now()->subYears($this->preferred_age_max + 1)->format('Y-m-d'));
            })
            ->when($this->preferred_gender && $this->preferred_gender !== 'any', function ($q) {
                $q->where('gender', $this->preferred_gender);
            })
            ->when(!empty($filters['height_from']), fn($q) => $q->where('height', '>=', $filters['height_from']))
            ->when(!empty($filters['height_to']), fn($q) => $q->where('height', '<=', $filters['height_to']))
            ->when(!empty($filters['education']), fn($q) => $q->where('education', $filters['education']))
            ->when(!empty($filters['zodiac_sign']), fn($q) => $q->where('zodiac_sign', $filters['zodiac_sign']))
            ->when(!empty($filters['is_verified_only']), fn($q) => $q->where('is_verified', true))
            ->when(!empty($filters['is_premium_only']), fn($q) => $q->where('is_premium', true))
            ->nearby($this->latitude, $this->longitude, $radius)
            ->with(['photos' => function ($q) {
                $q->where('is_primary', true)->orWhere('status', 'approved');
            }])
            ->limit($limit)->get();
    }

    public function swipe(User $targetUser, string $type): array
    {
        if (Swipe::where('user_id', $this->id)->where('target_user_id', $targetUser->id)->exists()) {
            return ['success' => false, 'message' => 'Вы уже оценили этого пользователя'];
        }

        Swipe::create(['user_id' => $this->id, 'target_user_id' => $targetUser->id, 'type' => $type]);

        if (in_array($type, ['like', 'superlike'])) {
            $targetUser->increment('likes_count');

            if (Swipe::where('user_id', $targetUser->id)->where('target_user_id', $this->id)->whereIn('type', ['like', 'superlike'])->exists()) {
                $match = UserMatch::create(['user1_id' => min($this->id, $targetUser->id), 'user2_id' => max($this->id, $targetUser->id)]);
                Chat::getOrCreateBetween($this, $targetUser);
                return ['success' => true, 'match' => true, 'message' => 'Взаимный лайк! У вас новый матч!', 'match_id' => $match->id];
            }
            return ['success' => true, 'match' => false, 'message' => 'Лайк отправлен'];
        }
        return ['success' => true, 'match' => false, 'message' => 'Дизлайк сохранён'];
    }

    public function getMatchesList()
    {
        return UserMatch::where('user1_id', $this->id)->orWhere('user2_id', $this->id)->with(['user1', 'user2'])->latest()->get()->map(function ($match) {
            $other = $match->user1_id === $this->id ? $match->user2 : $match->user1;
            return ['match_id' => $match->id, 'user' => $other, 'created_at' => $match->created_at];
        });
    }
}
