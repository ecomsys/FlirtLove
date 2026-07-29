<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chat extends Model
{
    protected $fillable = [
        'user1_id',
        'user2_id',
        'type',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

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

    public function messages(): HasMany
    {
        // Убрал ->latest() из связи! 
        // Это правило: связи не должны иметь дефолтной сортировки, 
        // иначе ->create() на этой связи может работать криво.
        // Сортировку будем делать при вызове: $chat->messages()->latest()->get()
        return $this->hasMany(Message::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ChatParticipant::class);
    }

        public function match()
    {
        // Возвращаем запрос, который ищет совпадение в любом порядке (кто кого лайкнул)
        return UserMatch::where(function ($q) {
            $q->where('user1_id', $this->user1_id)
              ->where('user2_id', $this->user2_id);
        })->orWhere(function ($q) {
            $q->where('user1_id', $this->user2_id)
              ->where('user2_id', $this->user1_id);
        });
    }

    // ============================================
    // ХЕЛПЕРЫ
    // ============================================

    /**
     * Получить собеседника.
     * ВАЖНО: Мы убрали Auth::id() из модели! Модель не должна знать о текущем запросе.
     * Теперь мы передаем ID юзера явно: $chat->getPartner(auth()->id())
     * Это позволяет использовать метод в очередях (queues) и тестах.
     */
    public function getPartner(int $userId): ?User
    {
        if ($this->user1_id === $userId) {
            return $this->relationLoaded('user2') ? $this->user2 : $this->user2()->first();
        }
        
        return $this->relationLoaded('user1') ? $this->user1 : $this->user1()->first();
    }

    /**
     * Создать или получить приватный чат между двумя юзерами
     */
    public static function getOrCreateBetween(User $userA, User $userB): self
    {
        $user1Id = min($userA->id, $userB->id);
        $user2Id = max($userA->id, $userB->id);

        $chat = self::firstOrCreate(
            ['user1_id' => $user1Id, 'user2_id' => $user2Id, 'type' => 'private'],
            ['last_message_at' => now()]
        );

        // Гарантируем, что участники чата существуют в БД (чтобы работали счетчики)
        // Это важный момент, которого не хватало в старой версии для приватных чатов!
        self::ensureParticipantsExist($chat, $user1Id, $user2Id);

        return $chat;
    }

    /**
     * Создать или получить чат поддержки
     */
    public static function getOrCreateSupportChat(User $admin, User $user): self
    {
        // В саппорте админ всегда user1 (для порядка)
        $chat = self::firstOrCreate(
            [
                'user1_id' => $admin->id, 
                'user2_id' => $user->id,
                'type' => 'support'
            ],
            ['last_message_at' => now()]
        );

        self::ensureParticipantsExist($chat, $admin->id, $user->id);

        return $chat;
    }

    /**
     * Внутренний метод для создания участников чата (чтобы не дублировать код)
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