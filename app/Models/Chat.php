<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chat extends Model
{
    // КОНСТАНТЫ ТИПОВ ЧАТА
    public const TYPE_PRIVATE = 'private';
    public const TYPE_SUPPORT = 'support';

    protected $fillable = [
        'type',             
        'last_message_at',  
        'is_locked',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'is_locked' => 'boolean',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    public function participants(): HasMany
    {
        return $this->hasMany(ChatParticipant::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_participants')
            ->withPivot(['unread_count', 'last_read_at', 'is_hidden', 'is_muted', 'is_blocked'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        // Оставляем без дефолтной сортировки! Сортировку делаем при вызове.
        return $this->hasMany(Message::class);
    }

    // ============================================
    // СКОПЫ
    // ============================================

    public function scopePrivate(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_PRIVATE);
    }

    public function scopeSupport(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_SUPPORT);
    }

    // ============================================
    // ХЕЛПЕРЫ
    // ============================================

    public function getPartner(int $userId): ?User
    {
        if ($this->relationLoaded('users')) {
            return $this->users->firstWhere('id', '!=', $userId);
        }
        
        return $this->users()->where('user_id', '!=', $userId)->first();
    }

    // ============================================
    // БИЗНЕС-ЛОГИКА (СОЗДАНИЕ ЧАТОВ)
    // ============================================

    public static function getOrCreateBetween(int $userAId, int $userBId): self
    {
        $hash = md5(min($userAId, $userBId) . '-' . max($userAId, $userBId));

        try {
            $chat = self::create([
                'participants_hash' => $hash,
                'type' => self::TYPE_PRIVATE,
                'last_message_at' => now()
            ]);
            self::ensureParticipantsExist($chat, $userAId, $userBId);
            return $chat;
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] === 23505) { // Дубль! Чат уже создан параллельно
                return self::where('participants_hash', $hash)->firstOrFail();
            }
            throw $e;
        }
    }
    
    public static function getOrCreateSupportChat(int $adminId, int $userId): self
    {
        // ФИКС: Используем уникальный хэш для саппорт-чата, чтобы избежать race condition
        // Формат хэша: md5('support-' . $userId)
        $hash = md5('support-' . $userId);

        try {
            $chat = self::create([
                'type' => self::TYPE_SUPPORT,
                'participants_hash' => $hash, // Обязательно прописываем хэш!
                'last_message_at' => now()
            ]);
            self::ensureParticipantsExist($chat, $adminId, $userId);
            return $chat;
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] === 23505) { // Дубль! Саппорт-чат уже создан параллельно
                return self::where('participants_hash', $hash)->firstOrFail();
            }
            throw $e;
        }
    }

    private static function ensureParticipantsExist(self $chat, int $user1Id, int $user2Id): void
    {
        ChatParticipant::firstOrCreate(
            ['chat_id' => $chat->id, 'user_id' => $user1Id],
            ['unread_count' => 0]
        );
        ChatParticipant::firstOrCreate(
            ['chat_id' => $chat->id, 'user_id' => $user2Id],
            ['unread_count' => 0]
        );
    }
}