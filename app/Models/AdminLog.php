<?php 

// Модель AdminLog (Журнал аудита) — это система видеонаблюдения твоего проекта. Если кто-то из модераторов решит по дружбе 
// раздать VIP-статусы, поменять цены тарифов или тихо удалить жалобу — всё это навсегда останется в этой таблице.

// В нашей архитектуре эта модель полиморфна (может логировать действия с любыми сущностями: юзерами, фото, транзакциями) и 
// хранит диффы (состояние before и after).

// Чтобы не писать простыни кода в каждом контроллере, я добавил крутой статический хелпер AdminLog::record(...), 
// который будет автоматически собирать IP, User-Agent и сохранять изменения.

// Разбор архитектуры (Big Brother is watching):

// Полиморфность loggable(): Журнал может ссылаться на любую таблицу. loggable_type = 'App\Models\Photo', loggable_id = 105. 
// В админке при просмотре профиля юзера ты сможешь вывести: "История действий с этим юзером" — 
// AdminLog::where('loggable_type', User::class)->where('loggable_id', $user->id)->get().
// Хелпер record(): Это спасет тонны времени. Вместо того, чтобы в каждом Livewire-компоненте писать 
// AdminLog::create([...]) с получением IP и User-Agent, ты напишешь одну строку:

// AdminLog::record('photo.approve', $photo, auth()->user());

// before и after: Если админ меняет цену тарифа с 500 на 5 рублей, ты всегда можешь подсунуть в метод 
// массивы ['price' => 500] и ['price' => 5]. При расследовании ошибки ты сразу увидишь дифф и сможешь откатить значение. 
// В идеале этот хелпер будет вызываться в Observer-классах Laravel автоматически, но для старта ручной вызов тоже отлично работает.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Request;

class AdminLog extends Model
{
    // ВАЖНО: Никаких SoftDeletes! Логи нельзя удалять или скрывать.
    // В идеале, доступ к удалению из этой таблицы должен быть только у DevOps через raw SQL.

    protected $fillable = [
        'admin_id',
        'action',          // user.ban, photo.approve, transaction.refund
        'loggable_type',   // Класс сущности (App\Models\User)
        'loggable_id',     // ID сущности
        'before',          // JSON: Состояние до изменения
        'after',           // JSON: Состояние после изменения
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    // Кто совершил действие (админ/модератор). nullable, т.к. действие может совершить система (воркер).
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    // Полиморфная связь: над кем или над чем совершили действие
    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }

    // ============================================
    // СКОПЫ (Для фильтрации в админке)
    // ============================================

    public function scopeByAdmin($query, int $adminId)
    {
        return $query->where('admin_id', $adminId);
    }

    public function scopeForAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeForEntity($query, string $type, int $id)
    {
        return $query->where('loggable_type', $type)->where('loggable_id', $id);
    }

    // ============================================
    // СТАТИЧЕСКИЙ ХЕЛПЕР ДЛЯ ЗАПИСИ ЛОГОВ
    // ============================================

    /**
     * Универсальный метод для записи действия в лог.
     * Использование в коде:
     * AdminLog::record('user.ban', $user, auth()->user(), ['status' => 'active'], ['status' => 'banned']);
     *
     * @param string $action - Что сделали (slug)
     * @param Model $model - Над какой моделью издевались
     * @param User|null $admin - Кто издевался (если null — система)
     * @param array|null $before - Данные ДО
     * @param array|null $after - Данные ПОСЛЕ
     * @return self
     */
    public static function record(string $action, Model $model, ?User $admin = null, ?array $before = null, ?array $after = null): self
    {
        return self::create([
            'admin_id'      => $admin?->id,
            'action'        => $action,
            'loggable_type' => get_class($model),
            'loggable_id'   => $model->id,
            'before'        => $before,
            'after'         => $after,
            'ip_address'    => Request::ip(),
            'user_agent'    => Request::userAgent(),
        ]);
    }
}