<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Request;

class AdminLog extends Model
{
    protected $fillable = [
        'admin_id',
        'action',          
        'loggable_type',   
        'loggable_id',     
        'before',          
        'after',           
        'ip_address',
        'user_agent',
        'participants'
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'participants' => 'array',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

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
    // ХЕЛПЕР: ВЫЧИСЛЕНИЕ ЧИСТОГО ДИФФА
    // ============================================
    
    /**
     * Сравнивает два массива и возвращает только изменившиеся поля.
     * Игнорирует технические поля (updated_at, last_seen и т.д.)
     */
    private static function calculateDiff(array $before, array $after): array
    {
        $cleanBefore = [];
        $cleanAfter = [];
        
        $ignoreFields = ['updated_at', 'last_seen', 'last_login_at', 'remember_token'];

        foreach ($after as $key => $value) {
            if (in_array($key, $ignoreFields)) continue;

            if (!array_key_exists($key, $before) || $before[$key] !== $value) {
                if ($key === 'body' && ($before[$key] ?? null) === $value) {
                    continue;
                }
                
                $cleanBefore[$key] = $before[$key] ?? null;
                $cleanAfter[$key] = $value;
            }
        }

        return [$cleanBefore, $cleanAfter];
    }

    // ============================================
    // СТАТИЧЕСКИЙ ХЕЛПЕР ДЛЯ ЗАПИСИ ЛОГОВ
    // ============================================

    /**
     * Универсальный метод для записи действия в лог.
     * Объединяет диффы, IP, User-Agent и поддержку участников (для чатов).
     */
    public static function record(string $action, ?Model $model = null, ?User $admin = null, ?array $before = null, ?array $after = null, array $participants = []): ?self
    {
        // Если админ не передан явно, берем текущего авторизованного (если есть)
        $admin = $admin ?? auth()->user();

        // УМНАЯ ОБРАБОТКА: Если переданы оба состояния, чистим их
        if (is_array($before) && is_array($after)) {
            [$before, $after] = self::calculateDiff($before, $after);
            
            // Если ничего не изменилось (кроме updated_at), не пишем пустой лог в БД
            if (empty($before) && empty($after) && empty($participants)) {
                return null; 
            }
        }

        return self::create([
            'admin_id'      => $admin?->id,
            'action'        => $action,
            'loggable_type' => $model ? get_class($model) : null,
            'loggable_id'   => $model?->id,
            'before'        => $before,
            'after'         => $after,
            'ip_address'    => app()->runningInConsole() ? 'CLI' : Request::ip(),
            'user_agent'    => app()->runningInConsole() ? null : Request::userAgent(),
            'participants'  => !empty($participants) ? $participants : null,
        ]);
    }
}

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
