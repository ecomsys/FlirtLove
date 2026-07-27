<?php

namespace App\Models;

use Illuminate\Support\Facades\Auth;
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
        return $this->hasMany(Message::class)->latest(); // По умолчанию сортируем от новых к старым
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ChatParticipant::class);
    }

    /**
     * Локальный скоуп: Исключает чаты, где замешаны админы.
     * Используется только для private чатов (знакомства).
     */
    public function scopeExcludeAdmins($query)
    {
        return $query->whereHas('user1', fn($q) => $q->where('is_admin', false))
                     ->whereHas('user2', fn($q) => $q->where('is_admin', false));
    }

    /**
     * Получить собеседника (того, кто не я)
     */
    public function getOtherUserAttribute()
    {
        return Auth::id() == $this->user1_id ? $this->user2 : $this->user1;
    }
    
    /**
     * Создать или получить чат между двумя юзерами
     * ВАЖНО: user1_id всегда меньше user2_id, чтобы не было дублей
     */
    public static function getOrCreateBetween(User $userA, User $userB): self
    {
        $user1Id = min($userA->id, $userB->id);
        $user2Id = max($userA->id, $userB->id);

        return self::firstOrCreate(
            ['user1_id' => $user1Id, 'user2_id' => $user2Id],
            ['last_message_at' => now()]
        );
    }
  
        /**
     * Связь с матчем (если чат создан на основе взаимных лайков)
     */
    public function match()
    {
        return $this->hasOne(UserMatch::class, 'user1_id', 'user1_id')
                    ->where('user2_id', $this->user2_id);
    }

        /**
     * Создать или получить чат поддержки между Админом и Юзером
     */
    public static function getOrCreateSupportChat(User $admin, User $user): self
    {
        $chat = self::firstOrCreate(
            [
                'user1_id' => $admin->id, 
                'user2_id' => $user->id,
                'type' => 'support'
            ],
            ['last_message_at' => now()]
        );

        //  Гарантируем, что участники чата существуют в БД (чтобы работали счетчики)
        \App\Models\ChatParticipant::firstOrCreate(
            ['chat_id' => $chat->id, 'user_id' => $admin->id],
            ['unread_count' => 0]
        );
        \App\Models\ChatParticipant::firstOrCreate(
            ['chat_id' => $chat->id, 'user_id' => $user->id],
            ['unread_count' => 0]
        );

        return $chat;
    }
}