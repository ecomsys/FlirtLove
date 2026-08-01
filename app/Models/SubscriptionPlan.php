<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'price',
        'currency',
        'duration_days',
        'trial_days',
        'features',            // JSON: {"invisible": true, "likes_per_day": 100, "hide_ads": true}
        'apple_product_id',    // Для App Store
        'google_product_id',   // Для Google Play
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration_days' => 'integer',
        'trial_days' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    /**
     * История покупок этого тарифа юзерами.
     */
    public function subscriptions(): HasMany 
    {
        return $this->hasMany(UserSubscription::class);
    }

    // ============================================
    // СКОПЫ
    // ============================================

    /**
     * Выводить только активные тарифы (для страницы покупки VIP).
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Сортировка по умолчанию (самые выгодные тарифы сверху).
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    // ============================================
    // ХЕЛПЕРЫ ДЛЯ ФИЧ (JSON)
    // ============================================

    /**
     * Проверить, есть ли конкретная фича в тарифе.
     * Используем в коде: if ($plan->hasFeature('invisible')) { ... }
     */
    public function hasFeature(string $key): bool
    {
        return isset($this->features[$key]) && $this->features[$key] !== false;
    }

    /**
     * Получить значение фичи (например, лимит лайков).
     * Используем: $limit = $plan->getFeature('likes_per_day', 20);
     */
    public function getFeature(string $key, mixed $default = null): mixed
    {
        return $this->features[$key] ?? $default;
    }

    // ============================================
    // СТАТИЧЕСКИЕ ХЕЛПЕРЫ
    // ============================================

    /**
     * Найти тариф по слагу (удобно для сидинга и привязки вебхуков).
     */
    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }
}



// Модель SubscriptionPlan (Тарифы) — это справочник, который определяет всю монетизацию проекта. 
// Админка будет создавать тут тарифы (VIP на 1 месяц, Premium+ на год), указывать их цены и фичи.

// Самое крутое здесь — это JSON-поле features. Мы напишем удобный хелпер, чтобы в middleware или policies 
// проверять доступ одной строкой: if ($user->plan->hasFeature('invisible')).

// Разбор архитектуры:

// Хелперы hasFeature и getFeature: Это киллер-фича для масштабирования. Тебе не нужно будет лезть в код, 
// если маркетолог решит добавить новую фичу "Скрыть просмотры". Он просто добавит её в JSON в админке, 
// а ты в middleware напишешь if ($user->plan->hasFeature('hide_views')).
// Отсутствие SoftDeletes: Тарифы нельзя удалять. Если тариф "VIP 1 месяц" перестал продаваться, 
// админ ставит is_active = false. Если мы удалим тариф физически, старые записи в user_subscriptions 
// (которые ссылаются на него) потеряют историю (хотя мы и защитили их nullOnDelete, но лучше просто скрывать).
// Apple/Google ID: Поля apple_product_id и google_product_id готовы к интеграции. Когда мобильное приложение 
// пришлет чек об оплате, твой сервер найдет тариф по этому ID и начислит юзеру подписку.