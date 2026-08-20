<?php

namespace App\Enums;

enum FraudTriggerType: string
{
    case LinksInChat = 'links_in_chat';
    case MassMessaging = 'mass_messaging';
    case Prostitute = 'prostitute';
    case SameDevice = 'same_device';
    case ScamPhrase = 'scam_phrase';

    public function label(): string
    {
        return match ($this) {
            self::LinksInChat   => 'Ссылки в чате',
            self::MassMessaging => 'Массовая рассылка',
            self::Prostitute    => 'Проституция',
            self::SameDevice    => 'Мультиаккаунт',
            self::ScamPhrase    => 'Скам (деньги)',
        };
    }

    // Метод, возвращающий Tailwind-класс для цвета текста
    public function colorClass(): string
    {
        return match ($this) {
            self::LinksInChat   => 'text-yellow-500 bg-yellow-500/10',
            self::MassMessaging => 'text-blue-500 bg-blue-500/10',
            self::Prostitute    => 'text-red-500 bg-red-500/10',
            self::SameDevice    => 'text-purple-500 bg-purple-500/10',
            self::ScamPhrase    => 'text-red-700 bg-red-700/10',
        };
    }
}