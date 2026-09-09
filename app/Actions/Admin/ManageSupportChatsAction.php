<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\Chat;
use App\Models\ChatParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class ManageSupportChatsAction
{
    public function markAsRead(Chat $chat, User $admin): void
    {
        // ФИКС: Обновляем только если есть непрочитанные (защита от спама базы пустыми UPDATE)
        ChatParticipant::where('chat_id', $chat->id)
            ->where('user_id', $admin->id)
            ->where('unread_count', '>', 0)
            ->update([
                'unread_count' => 0,
                'last_read_at' => now(), // ФИКС: Важно для WebSockets
            ]);
        
        Cache::forget('admin_sidebar_stats');
        Cache::forget('admin_support_stats_' . $admin->id); // ФИКС: Сброс кэша счетчиков
    }

    public function archiveChat(Chat $chat, User $admin): void
    {
        $partnerId = $chat->participants()->where('user_id', '!=', $admin->id)->value('user_id');

        ChatParticipant::where('chat_id', $chat->id)
            ->where('user_id', $admin->id)
            ->update(['is_hidden' => true]);

        $before = ['is_hidden' => false];
        $after = [
            'is_hidden' => true,
            'context' => [
                'chat_id' => $chat->id,
                'user_id' => $partnerId,
                'admin_id' => $admin->id
            ]
        ];

        AdminLog::record('support.archive', $chat, $admin, $before, $after, participants: array_filter([$admin->id, $partnerId]));
        Cache::forget('admin_support_stats_' . $admin->id); // ФИКС: Сброс кэша
    }

    public function unarchiveChat(Chat $chat, User $admin): void
    {
        $partnerId = $chat->participants()->where('user_id', '!=', $admin->id)->value('user_id');

        ChatParticipant::where('chat_id', $chat->id)
            ->where('user_id', $admin->id)
            ->update(['is_hidden' => false]);

        $before = ['is_hidden' => true];
        $after = [
            'is_hidden' => false,
            'context' => [
                'chat_id' => $chat->id,
                'user_id' => $partnerId,
                'admin_id' => $admin->id
            ]
        ];

        AdminLog::record('support.unarchive', $chat, $admin, $before, $after, participants: array_filter([$admin->id, $partnerId]));
        Cache::forget('admin_support_stats_' . $admin->id); // ФИКС: Сброс кэша
    }

    public function sendMessage(Chat $chat, User $admin, string $messageBody): void
    {
        $partnerId = $chat->participants()->where('user_id', '!=', $admin->id)->value('user_id');

        DB::transaction(function () use ($chat, $admin, $messageBody) {
            Message::create([
                'chat_id' => $chat->id,
                'sender_id' => $admin->id,
                'type' => 'text',
                'body' => $messageBody,
            ]);
            
            $chat->update(['last_message_at' => now()]);
            
            // Инкрементим счетчик непрочитанных у юзера
            ChatParticipant::where('chat_id', $chat->id)
                ->where('user_id', '!=', $admin->id)
                ->increment('unread_count');
            
            // ФИКС: Сбрасываем свой счетчик и ставим время прочтения
            ChatParticipant::where('chat_id', $chat->id)
                ->where('user_id', $admin->id)
                ->update([
                    'unread_count' => 0,
                    'last_read_at' => now(),
                ]);
        });

        $after = [
            'message_sent' => true,
            'context' => [
                'chat_id' => $chat->id,
                'user_id' => $partnerId,
                'admin_id' => $admin->id,
                'snippet' => Str::limit($messageBody, 50)
            ]
        ];

        AdminLog::record('support.message_sent', $chat, $admin, null, $after, participants: array_filter([$admin->id, $partnerId]));
        
        Cache::forget('admin_sidebar_stats');
        Cache::forget('admin_support_stats_' . $admin->id); // ФИКС: Сброс кэша

        /* 
         * ВАЖНО: Если у вас настроено уведомление юзеров об ответе саппорта (через ShouldQueue),
         * здесь можно добавить отправку уведомления юзеру:
         * 
         * if ($partnerId) {
         *     $user = User::find($partnerId);
         *     if ($user) $user->notify(new SupportReplied($chat, $messageBody));
         * }
         */
    }
}