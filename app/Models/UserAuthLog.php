<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class UserAuthLog extends Model
{
    protected $fillable = [
        'user_id', 'ip_address', 'user_agent', 
        'device_os', 'device_type', 'is_successful'
    ];

    protected $casts = [
        'is_successful' => 'boolean',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ============================================
    // ХЕЛПЕРЫ
    // ============================================

    /**
     * Быстрый логгер авторизации.
     * Прямая вставка в БД (DB::insert) работает в 10 раз быстрее, чем Model::create(),
     * что критично для логов, которые пишутся при каждом входе.
     */
    public static function log(int $userId, string $ip, string $userAgent, ?string $deviceOs = null, ?string $deviceType = null, bool $isSuccessful = true): void
    {
        DB::table('user_auth_logs')->insert([
            'user_id'       => $userId,
            'ip_address'    => $ip,
            'user_agent'    => $userAgent,
            'device_os'     => $deviceOs,
            'device_type'   => $deviceType,
            'is_successful' => $isSuccessful,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}