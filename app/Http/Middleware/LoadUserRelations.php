<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class LoadUserRelations
{
    /**
     * Handle an incoming request.
     * Жадно грузим настройки и профиль для авторизованного юзера,
     * чтобы не было N+1 в шапке сайта (тема, локаль, онлайн-статус).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            // Грузим только если еще не загружены!
            Auth::user()->loadMissing('preferences', 'profile');
        }

        return $next($request);
    }
}