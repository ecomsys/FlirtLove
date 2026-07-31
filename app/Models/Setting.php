<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key', 
        'value', 
        'group', 
        'label', 
        'description', // Новое поле для админки
        'type', 
        'options', 
        'is_public'
    ];

    protected $casts = [
        'options' => 'array',
        'is_public' => 'boolean',
    ];

    // ============================================
    // КЭШИРОВАНИЕ
    // ============================================

    /**
     * Получить все настройки из кэша (в виде коллекции моделей).
     * Кэшируем навсегда, пока не сбросим вручную.
     */
    public static function getAllCached(): \Illuminate\Support\Collection
    {
        return Cache::rememberForever('settings_all', function () {
            // keyBy('key') позволяет обращаться к настройке по ключу: $settings->get('site_name')
            return static::all()->keyBy('key');
        });
    }

    /**
     * Сбросить кэш настроек (вызывается автоматически при save/delete)
     */
    public static function flushCache(): void
    {
        Cache::forget('settings_all');
    }

    // ============================================
    // МАГИЧЕСКИЕ МЕТОДЫ ПОЛУЧЕНИЯ ЗНАЧЕНИЙ
    // ============================================

    /**
     * Получить значение настройки с автокастингом (БЕЗ ЗАПРОСОВ В БД).
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::getAllCached()->get($key);

        if (!$setting) {
            return $default;
        }

        // Автокаст: приводим значение к нужному типу прямо из кэша!
        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $setting->value,
            'json'    => json_decode($setting->value, true),
            default   => $setting->value,
        };
    }

    /**
     * Установить настройку и сбросить кэш
     */
    public static function set(string $key, mixed $value, array $attributes = []): self
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            array_merge(['value' => $value], $attributes)
        );
        
        static::flushCache();
        return $setting;
    }

    /**
     * Получить все настройки группы (например, для вкладки "Финансы")
     */
    public static function getGroup(string $group): \Illuminate\Support\Collection
    {
        return static::getAllCached()->filter(fn($item) => $item->group === $group);
    }

    /**
     * Получить публичные настройки для API (фронтенда)
     */
    public static function getPublic(): array
    {
        return static::getAllCached()
            ->filter(fn($item) => $item->is_public)
            ->mapWithKeys(fn($item) => [$item->key => self::get($item->key)])
            ->toArray();
    }

    // ============================================
    // СОБЫТИЯ МОДЕЛИ (Автоматический сброс кэша)
    // ============================================

    protected static function booted()
    {
        // Если админ изменил настройку в БД -> сбрасываем кэш
        static::saved(function () {
            static::flushCache();
        });

        static::deleted(function () {
            static::flushCache();
        });
    }
}