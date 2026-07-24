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

    // Получить значение настройки
    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    // Установить настройку
    public static function set(string $key, $value, array $attributes = [])
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            array_merge(['value' => $value], $attributes)
        );
        
        Cache::forget('settings');
        return $setting;
    }

    // Получить все настройки группы
    public static function getGroup(string $group): array
    {
        return static::where('group', $group)->pluck('value', 'key')->toArray();
    }

    // Кеширование настроек
    public static function getAllCached(): array
    {
        return Cache::remember('settings', 3600, function () {
            return static::all()->pluck('value', 'key')->toArray();
        });
    }
}



// Использование настроек в коде:
// php
// // Получить настройку
// $siteName = Setting::get('site_name');
// $maxPhotos = Setting::get('max_photos_per_user', 5);

// // В Blade
// {{ Setting::get('site_name') }}

// // В контроллере
// use App\Models\Setting;
// $contactEmail = Setting::get('contact_email');