<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMatch extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_UNMATCHED = 'unmatched';

    protected $table = 'user_matches'; 

    protected $fillable = [
        'user1_id',
        'user2_id',
        'status',
        'unmatched_by',
        'unmatched_at',
    ];

    protected $casts = [
        'unmatched_at' => 'datetime',
    ];

    // ============================================
    // СТАТИЧЕСКИЕ ХЕЛПЕРЫ (Бизнес-логика)
    // ============================================

    public static function createMatch(int $userA, int $userB): self
    {
        $user1Id = min($userA, $userB);
        $user2Id = max($userA, $userB);

        try {
            return self::create([
                'user1_id' => $user1Id,
                'user2_id' => $user2Id,
                'status' => self::STATUS_ACTIVE
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] === 23505) { 
                // ФИКС: Если мэтч уже есть (например, они разорвали его и снова полайкали)
                // Мы НЕ просто возвращаем старый unmatched мэтч, а РЕАКТИВИРУЕМ его!
                $match = self::where('user1_id', $user1Id)
                    ->where('user2_id', $user2Id)
                    ->firstOrFail();
                    
                if ($match->status === self::STATUS_UNMATCHED) {
                    $match->update([
                        'status' => self::STATUS_ACTIVE,
                        'unmatched_by' => null,
                        'unmatched_at' => null,
                    ]);
                }
                
                return $match;
            }
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
        return $query->where('status', self::STATUS_ACTIVE);
    }

    // ============================================
    // ХЕЛПЕРЫ
    // ============================================

    /**
     * Получить второго участника матча.
     * getRelationValue само проверит eager loading и сделает запрос, если нужно.
     */
    public function getPartner(int $userId): ?User
    {
        $relationName = $this->user1_id === $userId ? 'user2' : 'user1';
        return $this->getRelationValue($relationName);
    }

    /**
     * Разорвать мэтч (Unmatch).
     */
    public function unmatch(int $userId): bool
    {
        if ($this->status === self::STATUS_UNMATCHED) {
            return true;
        }

        return $this->update([
            'status' => self::STATUS_UNMATCHED,
            'unmatched_by' => $userId,
            'unmatched_at' => now(),
        ]);
    }
}