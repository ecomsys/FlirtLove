<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class InvalidateOldSessions
{
    /**
     * Когда юзер логинится, удаляем все его предыдущие сессии из базы.
     * Таким образом, на старом устройстве его "выкинет" из аккаунта.
     */
    public function handle(Login $event): void
    {
        // 1. Получаем ID текущей (новой) сессии, в которую только что вошли
        $currentSessionId = Session::getId();
        
        // 2. Получаем ID юзера, который вошел
        $userId = $event->user->getAuthIdentifier();

        // 3. Удаляем все сессии этого юзера из таблицы sessions, КРОМЕ текущей
        DB::table('sessions')
            ->where('user_id', $userId)
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }
}