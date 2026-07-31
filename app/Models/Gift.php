<?php 

// Каталог подарков (Gift) — это справочник. Админка будет создавать новые подарки, менять им цены и картинки. 
// Чтобы старые отправленные подарки (в таблице user_gifts) не ломались при изменении каталога, 
// мы отключили каскадное удаление и используем is_active, чтобы просто скрывать подарки из продажи.

// Также я добавил аксессор getImageUrlAttribute, используя твой гениальный паттерн с filter_var из модели Photo, 
// чтобы мы могли хранить картинки подарков как локально, так и на CDN.

// Разбор архитектуры:

// Отсутствие SoftDeletes: Мы обсуждали это при проектировании БД. 
// Если админ удаляет подарок из каталога (например, новогодний подарок в феврале), 
// мы делаем is_active = false. Жестко удалять (DELETE) нельзя, иначе связь в user_gifts 
// потеряется (хотя мы и защитили её через nullOnDelete, но лучше просто скрывать). 
// Если нужно совсем удалить — удаляем без сомнений, старые логи выживут благодаря снапшоту.
// scopeActive и scopeOfCategory: Позволят в Livewire-компоненте магазина писать: 
// Gift::active()->ofCategory('romantic')->get(). Это супер-читаемо и летает благодаря индексам в БД.
// getImageUrlAttribute: Унификация. Все картинки в проекте (фото юзеров, подарки) теперь работают 
// по одному принципу. Если завтра ты переедешь на Amazon S3, тебе не придется переписывать модели, 
// достаточно будет поменять конфиг filesystems.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Gift extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'image_url',
        'price',         // Цена во внутренней валюте (кредитах)
        'category',      // Категория (romantic, cars, male, female, 18+)
        'is_active',     // Доступен ли для покупки в каталоге
    ];

    protected $casts = [
        'price' => 'integer',
        'is_active' => 'boolean',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    /**
     * История отправок этого подарка.
     * Связь с таблицей user_gifts.
     */
    public function userGifts(): HasMany
    { 
        return $this->hasMany(UserGift::class);
    }

    // ============================================
    // СКОПЫ
    // ============================================

    /**
     * Выводить только активные подарки (для каталога в магазине).
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Фильтр по категории (для группировки в магазине).
     */
    public function scopeOfCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    // ============================================
    // АКСЕССОРЫ
    // ============================================

    /**
     * Получить URL картинки подарка.
     * Используем тот же паттерн, что и в Photo: 
     * если ссылка полная (CDN) — отдаем как есть, если путь — генерируем через Storage.
     */
    public function getImageUrlAttribute(): string
    {
        if (empty($this->image_url)) {
            return ''; // Заглушка, если админ не загрузил картинку
        }

        if (filter_var($this->image_url, FILTER_VALIDATE_URL)) {
            return $this->image_url;
        }

        return Storage::url($this->image_url);
    }

    // ============================================
    // ХЕЛПЕРЫ
    // ============================================

    /**
     * Доступен ли подарок для покупки.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }
}
