<?php

namespace App\Http\Middleware;

use App\Models\BlockedIp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckBlockedIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        // 1.  Разрешаем локальные IP (чтобы админ не заблокировал сам себя на локалке)
        if (in_array($ip, ['127.0.0.1', '::1'])) {
            return $next($request);
        }

        // 2.  Разрешаем доступ к странице логина (чтобы разблокированный юзер мог зайти)
        if ($request->is('login') || $request->is('admin/login')) {
            return $next($request);
        }

        // 3. Если пользователь авторизован и он Админ — пропускаем сразу!
        if (auth()->check() && auth()->user()->is_admin) {
            return $next($request);
        }

        // 4. Иначе проверяем IP
        if ($ip && BlockedIp::where('ip_address', $ip)->exists()) {
            auth()->logout();
            abort(403, 'Ваш IP-адрес заблокирован администрацией.');
        }

        return $next($request);
    }
}