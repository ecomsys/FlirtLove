<?php

namespace App\Enums;

enum DiaryRejectReason: string
{
    case Spam = 'spam';
    case Porn = 'porn';
    case Scam = 'scam';
    case Insult = 'insult';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Spam => 'Спам / Реклама',
            self::Porn => 'Порнография / 18+',
            self::Scam => 'Мошенничество',
            self::Insult => 'Оскорбления',
            self::Other => 'Другое',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Spam => 'bg-yellow-500/10 text-yellow-500',
            self::Porn => 'bg-red-500/10 text-red-500',
            self::Scam => 'bg-orange-500/10 text-orange-500',
            self::Insult => 'bg-destructive/10 text-destructive',
            self::Other => 'bg-secondary text-secondary-foreground',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [
            $case->value => $case->label()
        ])->toArray();
    }
}