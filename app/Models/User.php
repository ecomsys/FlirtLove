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
// use Illuminate\Notifications\Notification;

#[Fillable([
    'name',
    'email',
    'password',
    'locale',       // локализация ru, en 
    'theme',        // тема dark, light 
    'gender',
    'birth_date',
    'dating_goal',  // цель знакомства (['friends', 'romantic', 'family', 'casual'])
    'city',
    'has_completed_onboarding',  // загрузка фоток при первой регистрации  
    'is_admin',
    'last_login_at',
    'last_login_ip',
    'is_banned'
])]

#[Hidden(['password', 'remember_token'])]


class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birth_date' => 'date',
            'has_completed_onboarding' => 'boolean',
            'is_admin' => 'boolean',
            'last_login_at' => 'datetime'
        ];
    }

    public function routeNotificationForMail($notification): string
    {
        return $this->email;
    }
    

    // ФОТО

    public function albums(): HasMany
    {
        return $this->hasMany(Album::class);
    }

    public function defaultAlbum(): HasOne
    {
        return $this->hasOne(Album::class)->where('is_default', true);
    }

    // При создании пользователя создаем альбом по умолчанию
    protected static function booted()
    {
        static::created(function (User $user) {
            Album::createDefaultForUser($user);
        });
    }

    /**
     * Фото
     */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    /**
     * Получить URL аватара (основного фото)
     */
    public function getAvatarUrlAttribute(): ?string
    {
        $primaryPhoto = $this->photos()->where('is_primary', true)->first();

        return $primaryPhoto ? $primaryPhoto->thumb_url : null;
    }

    /**
     * Получить основное фото
     */
    public function getPrimaryPhotoAttribute()
    {
        return $this->photos()->where('is_primary', true)->first();
    }

    /**
     * Комментарии пользователя
     */
    public function photoComments(): HasMany
    {
        return $this->hasMany(PhotoComment::class);
    }

    /**
     * Одобренные комментарии пользователя
     */
    public function approvedPhotoComments(): HasMany
    {
        return $this->hasMany(PhotoComment::class)->where('status', 'approved');
    }

    /**
     * Проверка, завершил ли пользователь онбординг (загрузка фото при регистрации)
     */
    public function hasCompletedOnboarding(): bool
    {
        return $this->has_completed_onboarding || $this->photos()->count() > 0;
    }

    // ЖАЛОБЫ

    /**
     * Жалобы, которые получил пользователь (на него жалуются)
     */
    public function receivedReports(): HasMany
    {
        return $this->hasMany(Report::class, 'reported_user_id');
    }

    /**
     * Жалобы, которые отправил пользователь (он жалуется на других)
     */
    public function sentReports(): HasMany
    {
        return $this->hasMany(Report::class, 'user_id');
    }

    /**
     * Получить количество жалоб на пользователя
     */
    public function getReportsReceivedCountAttribute(): int
    {
        return $this->receivedReports()->count();
    }

    /**
     * Получить количество жалоб от пользователя
     */
    public function getReportsSentCountAttribute(): int
    {
        return $this->sentReports()->count();
    }
}
