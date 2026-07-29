<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key', 'value', 'group', 'label', 'type', 'options', 'is_public'
    ];

    protected $casts = [
        'options' => 'array',
        'is_public' => 'boolean',
    ];

    /**
     * Получить значение настройки (ВСЕГДА из кеша)
     */
    public static function get(string $key, $default = null)
    {
        $settings = static::getAllCached();
        $value = $settings[$key] ?? $default;

        // Автокаст: если в БД тип 'boolean', вернем настоящий bool
        $settingModel = static::where('key', $key)->first();
        if ($settingModel && $settingModel->type === 'boolean') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return $value;
    }

    /**
     * Установить настройку и сбросить кеш
     */
    public static function set(string $key, $value, array $attributes = [])
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            array_merge(['value' => $value], $attributes)
        );
        
        Cache::forget('settings');
        return $setting;
    }

    /**
     * Получить все настройки группы
     */
    public static function getGroup(string $group): array
    {
        return static::where('group', $group)->pluck('value', 'key')->toArray();
    }

    /**
     * Кеширование настроек навсегда (пока не сбросим вручную)
     */
    public static function getAllCached(): array
    {
        return Cache::rememberForever('settings', function () {
            return static::all()->pluck('value', 'key')->toArray();
        });
    }
}
