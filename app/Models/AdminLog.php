<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class AdminLog extends Model
{
    // КОНСТАНТЫ ДЕЙСТВИЙ (чтобы не хардкодить строки)
    public const ACTION_USER_BAN = 'user.ban';
    public const ACTION_USER_UNBAN = 'user.unban';
    public const ACTION_PHOTO_APPROVE = 'photo.approve';
    public const ACTION_PHOTO_REJECT = 'photo.reject';
    public const ACTION_TRANSACTION_REFUND = 'transaction.refund';
    public const ACTION_SETTING_UPDATE = 'setting.update';

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
    // СКОПЫ
    // ============================================

    public function scopeByAdmin(Builder $query, int $adminId): Builder
    {
        return $query->where('admin_id', $adminId);
    }

    public function scopeForAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    public function scopeForEntity(Builder $query, string $type, int $id): Builder
    {
        return $query->where('loggable_type', $type)->where('loggable_id', $id);
    }

    // ============================================
    // ХЕЛПЕР: ВЫЧИСЛЕНИЕ ЧИСТОГО ДИФФА
    // ============================================

    private static function calculateDiff(array $before, array $after): array
    {
        $cleanBefore = [];
        $cleanAfter = [];
        $ignoreFields = ['updated_at', 'created_at', 'last_seen', 'last_login_at', 'remember_token'];

        // Проверяем изменившиеся или новые поля
        foreach ($after as $key => $value) {
            if (in_array($key, $ignoreFields)) continue;

            if (!array_key_exists($key, $before) || $before[$key] !== $value) {
                $cleanBefore[$key] = $before[$key] ?? null;
                $cleanAfter[$key] = $value;
            }
        }

        // Проверяем удаленные поля (было в before, исчезло в after)
        foreach ($before as $key => $value) {
            if (in_array($key, $ignoreFields)) continue;

            if (!array_key_exists($key, $after)) {
                $cleanBefore[$key] = $value;
                $cleanAfter[$key] = null;
            }
        }

        return [$cleanBefore, $cleanAfter];
    }

    // ============================================
    // СТАТИЧЕСКИЙ ХЕЛПЕР ДЛЯ ЗАПИСИ ЛОГОВ
    // ============================================

    /**
     * Универсальный метод для записи действия в лог.
     * ИСПОЛЬЗУЕМ DB::insertGetId для максимальной скорости (в 10 раз быстрее Model::create)
     */
    public static function record(string $action, ?Model $model = null, ?User $admin = null, ?array $before = null, ?array $after = null, array $participants = []): ?int
    {
        $admin = $admin ?? auth()->user();

        if (is_array($before) && is_array($after)) {
            [$before, $after] = self::calculateDiff($before, $after);
            
            // Если ничего не изменилось, не пишем пустой лог в БД
            if (empty($before) && empty($after) && empty($participants)) {
                return null; 
            }
        }

        return DB::table('admin_logs')->insertGetId([
            'admin_id'      => $admin?->id,
            'action'        => $action,
            'loggable_type' => $model ? get_class($model) : null,
            'loggable_id'   => $model?->id,
            'before'        => $before ? json_encode($before) : null,
            'after'         => $after ? json_encode($after) : null,
            'ip_address'    => app()->runningInConsole() ? 'CLI' : Request::ip(),
            'user_agent'    => app()->runningInConsole() ? null : Request::userAgent(),
            'participants'  => !empty($participants) ? json_encode($participants) : null,
            'created_at'    => now(),
            'updated_at'    => now(),
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
