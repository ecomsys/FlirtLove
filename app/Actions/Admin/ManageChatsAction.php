<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\Chat;
use App\Models\User;
use Illuminate\Support\Carbon;

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

        $chat->update(['is_locked' => !$chat->is_locked]);

        $systemMsgText = $chat->is_locked 
            ? 'Чат заблокирован администрацией.' 
            : 'Чат разблокирован администрацией.';

        $chat->messages()->create([
            'sender_id' => null,
            'type' => 'system',
            'body' => $systemMsgText,
        ]);
        
        $chat->update(['last_message_at' => now()]);

        // Получаем ID участников напрямую запросом, чтобы не грузить модели
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

        return $chat->is_locked;
    }
}