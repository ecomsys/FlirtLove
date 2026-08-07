<?php

namespace App\Enums;

enum CommentRejectReason: string
{
    case Profanity = 'profanity';
    case Insult = 'insult';
    case OffTopic = 'off_topic';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Profanity => 'Мат / Нецензурная лексика',
            self::Insult => 'Оскорбление',
            self::OffTopic => 'Не по теме',
            self::Other => 'Другое',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Profanity => 'bg-red-500/10 text-red-500',
            self::Insult => 'bg-destructive/10 text-destructive',
            self::OffTopic => 'bg-yellow-500/10 text-yellow-500',
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