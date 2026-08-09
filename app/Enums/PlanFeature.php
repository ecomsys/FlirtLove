<?php

namespace App\Enums;

enum PlanFeature: string
{
    case Invisible = 'invisible';
    case LikesPerDay = 'likes_per_day';
    case SuperlikesPerDay = 'superlikes_per_day';
    case HideAds = 'hide_ads';
    case CanSeeWhoLiked = 'can_see_who_liked';

    public function label(): string
    {
        return match ($this) {
            self::Invisible => 'Режим невидимки',
            self::LikesPerDay => 'Лимит лайков в день',
            self::SuperlikesPerDay => 'Лимит суперлайков',
            self::HideAds => 'Скрытие рекламы',
            self::CanSeeWhoLiked => 'Просмотр лайкнувших',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Invisible => 'eye-off',
            self::LikesPerDay => 'heart',
            self::SuperlikesPerDay => 'star',
            self::HideAds => 'shield-off',
            self::CanSeeWhoLiked => 'users',
        };
    }

    public function formatValue(mixed $value): string
    {
        if (is_bool($value)) return $value ? 'Включено' : 'Недоступно';
        if ($this === self::LikesPerDay && $value >= 999) return 'Безлимит';
        if ($this === self::SuperlikesPerDay && $value >= 999) return 'Безлимит';
        return $value . ' шт.';
    }

    /**
     * НОВЫЙ МЕТОД: Указывает UI, как рендерить поле ввода.
     * true = чекбокс (boolean)
     * false = числовой инпут (integer)
     */
    public function isBoolean(): bool
    {
        return match ($this) {
            self::Invisible, self::HideAds, self::CanSeeWhoLiked => true,
            default => false,
        };
    }
}