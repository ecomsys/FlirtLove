<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMatch extends Model
{
    protected $table = 'user_matches'; // Указываем явно, так как "Match" может конфликтовать в Laravel

    protected $fillable = [
        'user1_id',
        'user2_id',
        'status',         // active, unmatched
        'unmatched_by',   // ID юзера, который разорвал мэтч
        'unmatched_at',   // Время разрыва
    ];

    protected $casts = [
        'unmatched_at' => 'datetime',
    ];

    // ============================================
    // СТАТИЧЕСКИЕ ХЕЛПЕРЫ (Бизнес-логика)
    // ============================================

    /**
     * Создать мэтч с гарантией отсутствия дубликатов.
     * Меньший ID всегда идет в user1_id, больший в user2_id.
     * Это защищает от багов, когда Вася лайкает Машу (10-5), а Маша Васю (5-10).
     */
   public static function createMatch(int $userA, int $userB): self
    {
        $user1Id = min($userA, $userB);
        $user2Id = max($userA, $userB);

        try {
            // Пытаемся создать. Если параллельный процесс уже создал - БД выдаст ошибку дубля.
            return self::create([
                'user1_id' => $user1Id,
                'user2_id' => $user2Id,
                'status' => 'active'
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Код 23505 - ошибка уникального индекса в PostgreSQL
            // Если упали именно из-за дубля (состояние гонки) - просто забираем существующий мэтч
            if ($e->errorInfo[1] === 23505) { 
                return self::where('user1_id', $user1Id)
                    ->where('user2_id', $user2Id)
                    ->firstOrFail();
            }
            // Если ошибка другая - пробрасываем дальше
            throw $e;
        }
    }

    // ============================================
    // СВЯЗИ
    // ============================================

    public function user1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user1_id');
    }

    public function user2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user2_id');
    }

    // ============================================
    // СКОПЫ
    // ============================================

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // ============================================
    // ХЕЛПЕРЫ
    // ============================================

    /**
     * Получить второго участника матча (Твой код из старой модели).
     * Оптимизировано под eager loading.
     */
    public function getPartner(int $userId): ?User
    {
        if ($this->user1_id === $userId) {
            return $this->relationLoaded('user2') ? $this->user2 : $this->user2()->first();
        }
        
        return $this->relationLoaded('user1') ? $this->user1 : $this->user1()->first();
    }

    /**
     * Разорвать мэтч (Unmatch).
     * @param int $userId - Кто инициировал разрыв
     */
    public function unmatch(int $userId): bool
    {
        if ($this->status === 'unmatched') {
            return true; // Уже разорван
        }

        return $this->update([
            'status' => 'unmatched',
            'unmatched_by' => $userId,
            'unmatched_at' => now(),
        ]);
    }
}