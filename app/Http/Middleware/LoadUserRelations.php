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
     * Жадно грузим настройки, профиль и фото для авторизованного юзера,
     * чтобы не было N+1 в шапке сайта (тема, локаль, аватарка).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            // Грузим только если еще не загружены!
            Auth::user()->loadMissing([
                'preferences', 
                'profile',
                // Жадно грузим только одобренные фото, ставим is_primary на первое место
                'photos' => function ($query) {
                    $query->where('status', 'approved')->orderByDesc('is_primary');
                }
            ]);
        }

        return $next($request);
    }
}