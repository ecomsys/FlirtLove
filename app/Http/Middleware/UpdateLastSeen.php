<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        // Берем юзера напрямую из Request (это избавляет от красных подчеркиваний)
        $user = $request->user();

        if ($user) {
            // Обновляем не чаще чем раз в 2 минуты, чтобы не нагружать БД на каждый клик
            if (is_null($user->last_seen) || $user->last_seen->lt(now()->subMinutes(2))) {
                $user->update(['last_seen' => now()]);
            }
        }

        return $next($request);
    }
}