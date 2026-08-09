<?php

namespace App\Enums;

enum MediaCollection: string
{
    case Default = 'default';
    case Gifts = 'gifts';
    case Blog = 'blog';
    case Banners = 'banners';

    /**
     * Человекочитаемый лейбл для админки.
     */
    public function label(): string
    {
        return match ($this) {
            self::Default => 'Общие',
            self::Gifts => 'Подарки',
            self::Blog => 'Блог',
            self::Banners => 'Баннеры',
        };
    }

      /**
     * Цвет бейджа для UI (в админке).
     */
    public function color(): string
    {
        return match ($this) {
            self::Default => 'bg-secondary text-secondary-foreground',
            self::Gifts => 'bg-yellow-500/10 text-yellow-500',
            self::Blog => 'bg-blue-500/10 text-blue-500',
            self::Banners => 'bg-purple-500/10 text-purple-500',
        };
    }

        /**
     * Подсказка с правилами обработки (для вывода в админке).
     */
    public function dimensionsHint(): string
    {
        if ($this->shouldBeSquare()) {
            return "Правило: {$this->maxWidth()}x{$this->maxWidth()}px (Квадрат)";
        }
        return "Правило: ширина до {$this->maxWidth()}px";
    }

    /**
     * Максимальная ширина картинки при загрузке.
     */
    public function maxWidth(): int
    {
        return match ($this) {
            self::Gifts => 300,       // Подарки мелкие, 300px более чем достаточно
            self::Blog => 1200,       // Для блога нужны качественные широкие картинки
            self::Banners => 1600,   // Баннеры на весь экран
            default => 1000,         // Для остальных ограничиваем 1000px
        };
    }

    /**
     * Должна ли картинка быть строго квадратной?
     */
    public function shouldBeSquare(): bool
    {
        return match ($this) {
            self::Gifts => true,     // Подарки всегда квадратные (1:1)
            default => false,        // Блог и баннеры оставляем в оригинальных пропорциях
        };
    }

    /**
     * Качество сжатия WebP (от 0 до 100).
     */
    public function quality(): int
    {
        return match ($this) {
            self::Gifts => 70,       // Мелкие картинки можно сильнее сжать
            self::Blog => 85,        // Для блога качество повыше
            self::Banners => 90,     // Баннеры должны быть идеальными
            default => 80,
        };
    }

    /**
     * Максимальный размер загружаемого файла в КИЛОБАЙТАХ (для валидации).
     */
    public function maxFileSizeKb(): int
    {
        return match ($this) {
            default => 5120, // 5MB для всех файлов
        };
    }

    /**
     * Список для выпадающих меню в админке.
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [
            $case->value => $case->label()
        ])->toArray();
    }
}