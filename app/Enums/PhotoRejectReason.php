<?php

namespace App\Enums;

enum PhotoRejectReason: string
{
    case Porn = 'porn';
    case Minor = 'minor';
    case Ad = 'ad';
    case Stolen = 'stolen';
    case LowQuality = 'low_quality';
    case Other = 'other';
    case MassReject = 'mass_reject'; // Для массового отклонения из Action

    /**
     * Возвращает человекочитаемый лейбл для UI
     */
    public function label(): string
    {
        return match ($this) {
            self::Porn => 'Порнография',
            self::Minor => 'Несовершеннолетний',
            self::Ad => 'Реклама / Контакты',
            self::Stolen => 'Чужое фото',
            self::LowQuality => 'Плохое качество',
            self::Other => 'Другое',
            self::MassReject => 'Массовое отклонение',
        };
    }

    /**
     * Возвращает массив для селектов в Blade [value => label]
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [
            $case->value => $case->label()
        ])->toArray();
    }
}