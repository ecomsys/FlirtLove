<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\Chat;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ManageChatsAction
{
    /**
     * Заблокировать/Разблокировать чат администратором.
     */
    public function toggleLock(Chat $chat, User $admin): bool
    {
        $beforeState = $chat->getOriginal('is_locked');
        $beforeLastMsg = $chat->getOriginal('last_message_at');
        $beforeLastMsgFormatted = $beforeLastMsg ? Carbon::parse($beforeLastMsg)->toDateTimeString() : null;

        DB::transaction(function () use ($chat, $admin, $beforeState, $beforeLastMsgFormatted) {
            // 1. Блокируем/разблокируем
            $chat->update(['is_locked' => !$chat->is_locked]);

            // 2. Пишем системное сообщение
            $systemMsgText = $chat->is_locked 
                ? 'Чат заблокирован администрацией.' 
                : 'Чат разблокирован администрацией.';

            $chat->messages()->create([
                'sender_id' => null,
                'type' => 'system',
                'body' => $systemMsgText,
            ]);
            
            // 3. Обновляем время последнего сообщения
            $chat->update(['last_message_at' => now()]);

            // 4. Логируем
            $participantIds = $chat->participants()->pluck('user_id')->toArray();

            AdminLog::record(
                action: $chat->is_locked ? 'chat.lock' : 'chat.unlock', 
                model: $chat, 
                admin: $admin, 
                before: [
                    'is_locked' => (bool) $beforeState,
                    'last_message_at' => $beforeLastMsgFormatted,
                ], 
                after: [
                    'is_locked' => $chat->is_locked,
                    'last_message_at' => $chat->last_message_at->toDateTimeString(),
                    'system_message' => $systemMsgText,
                    'context' => [
                        'chat_id' => $chat->id,
                        'admin_id' => $admin->id,
                        'participants' => $participantIds,
                    ]
                ],
                participants: $participantIds
            );
        });

        Cache::forget('admin_chat_stats');

        return $chat->is_locked;
    }
}