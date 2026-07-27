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
        return self::firstOrCreate(
            [
                'user1_id' => $admin->id, 
                'user2_id' => $user->id,
                'type' => 'support'
            ],
            ['last_message_at' => now()]
        );
    }
}