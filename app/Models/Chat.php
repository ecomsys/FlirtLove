<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chat extends Model
{
    protected $fillable = [
        'type',             // private, support
        'last_message_at',  // Кэш времени последнего сообщения для сортировки
         'is_locked',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'is_locked' => 'boolean',
    ];

    // ============================================
    // СВЯЗИ
    // ============================================

    /**
     * Участники чата (прямая связь со сводной таблицей).
     * Нужна для управления настройками чата (мьюты, баны, счетчики).
     */
    public function participants(): HasMany
    {
        return $this->hasMany(ChatParticipant::class);
    }

    /**
     * Юзеры в чате (Многие ко многим через chat_participants).
     * С пивотом для удобного доступа к настройкам.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_participants')
            ->withPivot(['unread_count', 'last_read_at', 'is_hidden', 'is_muted', 'is_blocked'])
            ->withTimestamps();
    }

    /**
     * Сообщения в чате.
     * Твой правило: связи не должны иметь дефолтной сортировки!
     * Сортировку делаем при вызове: $chat->messages()->latest()->get()
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    // ============================================
    // СКОПЫ
    // ============================================

    public function scopePrivate($query)
    {
        return $query->where('type', 'private');
    }

    public function scopeSupport($query)
    {
        return $query->where('type', 'support');
    }

    // ============================================
    // ХЕЛПЕРЫ
    // ============================================

    /**
     * Получить собеседника (Адаптировано под новую БД).
     * Твой принцип: Модель не должна знать о текущем запросе, передаем ID явно.
     */
    public function getPartner(int $userId): ?User
    {
        // Если юзеры загружены через eager loading, берем из коллекции
        if ($this->relationLoaded('users')) {
            return $this->users->firstWhere('id', '!=', $userId);
        }
        
        // Иначе делаем легкий запрос через сводную таблицу
        return $this->users()->where('user_id', '!=', $userId)->first();
    }

    // ============================================
    // БИЗНЕС-ЛОГИКА (СОЗДАНИЕ ЧАТОВ)
    // ============================================

    /**
     * Создать или получить приватный чат между двумя юзерами.
     */
    public static function getOrCreateBetween(int $userAId, int $userBId): self
    {
        $hash = md5(min($userAId, $userBId) . '-' . max($userAId, $userBId));

        try {
            $chat = self::create([
                'participants_hash' => $hash,
                'type' => 'private',
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
    /**
     * Создать или получить чат поддержки.
     */
    public static function getOrCreateSupportChat(int $adminId, int $userId): self
    {
        $chat = self::where('type', 'support')
            ->whereHas('participants', fn($q) => $q->where('user_id', $userId))
            ->first();

        if (!$chat) {
            $chat = self::create(['type' => 'support', 'last_message_at' => now()]);
            self::ensureParticipantsExist($chat, $adminId, $userId);
        }

        return $chat;
    }

    /**
     * Внутренний метод для создания участников чата (Твоя идея).
     * Гарантирует, что записи в pivot таблице существуют.
     */
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