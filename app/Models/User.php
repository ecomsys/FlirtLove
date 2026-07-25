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
    'has_completed_onboarding',
    'is_admin',
    'last_login_at',
    'last_login_ip',
    'is_banned'
])]
#[Hidden(['password', 'remember_token'])]

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birth_date' => 'date',
            'has_completed_onboarding' => 'boolean',
            'is_admin' => 'boolean',
            'is_banned' => 'boolean',
            'is_premium' => 'boolean',
            'is_verified' => 'boolean',
            'last_login_at' => 'datetime',
            'interests' => 'array',
            'preferred_age_min' => 'integer',
            'preferred_age_max' => 'integer',
            'preferred_distance_km' => 'integer',
            'superlikes_remaining' => 'integer',
            'location' => 'string', 
        ];
    }

    public function routeNotificationForMail($notification): string
    {
        return $this->email;
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
        $primary = $this->photos()->where('is_primary', true)->first();
        return $primary ? $primary->thumb_url : null;
    }

    public function getPrimaryPhotoAttribute()
    {
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
        return $this->receivedReports()->count();
    }

    public function getReportsSentCountAttribute(): int
    {
        return $this->sentReports()->count();
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
        return $query->whereNotNull('location')
            ->whereRaw(
                "ST_DWithin(location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)",
                [$lng, $lat, $radius * 1000]
            );
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

    public function matches(): HasMany
    {
        return $this->hasMany(UserMatch::class, 'user1_id')
            ->orWhere('user2_id', $this->id);
    }

    // === ЛОГИКА РЕКОМЕНДАЦИЙ ===
    public function getRecommendedUsers(int $limit = 20)
    {
        if (!$this->location) {
            return collect();
        }

        $swipedIds = $this->swipesGiven()->pluck('target_user_id')->toArray();

        $matchedIds = UserMatch::where('user1_id', $this->id)->pluck('user2_id')->toArray()
            + UserMatch::where('user2_id', $this->id)->pluck('user1_id')->toArray();

        $excludeIds = array_merge([$this->id], $swipedIds, $matchedIds);

        $radius = $this->preferred_distance_km ?? 50;

        return User::where('is_banned', false)
            ->where('has_completed_onboarding', true)
            ->whereNotIn('id', $excludeIds)
            ->when($this->preferred_age_min, function ($q) {
                $q->whereRaw('EXTRACT(YEAR FROM age(birth_date)) >= ?', [$this->preferred_age_min]);
            })
            ->when($this->preferred_age_max, function ($q) {
                $q->whereRaw('EXTRACT(YEAR FROM age(birth_date)) <= ?', [$this->preferred_age_max]);
            })
            ->when($this->preferred_gender && $this->preferred_gender !== 'any', function ($q) {
                $q->where('gender', $this->preferred_gender);
            })
            ->nearby($this->latitude, $this->longitude, $radius)
            ->with(['photos' => function ($q) {
                $q->where('is_primary', true)->orWhere('status', 'approved');
            }])
            ->limit($limit)
            ->get();
    }

    public function swipe(User $targetUser, string $type): array
    {
        $existing = Swipe::where('user_id', $this->id)
            ->where('target_user_id', $targetUser->id)
            ->first();

        if ($existing) {
            return ['success' => false, 'message' => 'Вы уже оценили этого пользователя'];
        }

        $swipe = Swipe::create([
            'user_id' => $this->id,
            'target_user_id' => $targetUser->id,
            'type' => $type,
        ]);

        if (in_array($type, ['like', 'superlike'])) {
            $mutual = Swipe::where('user_id', $targetUser->id)
                ->where('target_user_id', $this->id)
                ->whereIn('type', ['like', 'superlike'])
                ->exists();

            if ($mutual) {
                $match = UserMatch::create([
                    'user1_id' => min($this->id, $targetUser->id),
                    'user2_id' => max($this->id, $targetUser->id),
                ]);

                return [
                    'success' => true,
                    'match' => true,
                    'message' => 'Взаимный лайк! У вас новый матч!',
                    'match_id' => $match->id,
                ];
            }

            return ['success' => true, 'match' => false, 'message' => 'Лайк отправлен'];
        }

        return ['success' => true, 'match' => false, 'message' => 'Дизлайк сохранён'];
    }

    public function getMatchesList()
    {
        return UserMatch::where('user1_id', $this->id)
            ->orWhere('user2_id', $this->id)
            ->with(['user1', 'user2'])
            ->latest()
            ->get()
            ->map(function ($match) {
                $other = $match->user1_id === $this->id ? $match->user2 : $match->user1;
                return [
                    'match_id' => $match->id,
                    'user' => $other,
                    'created_at' => $match->created_at,
                ];
            });
    }
}