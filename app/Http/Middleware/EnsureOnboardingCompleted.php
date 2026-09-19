<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureOnboardingCompleted
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        // 1. Если гость — просто пропускаем
        if (! $user) {
            return $next($request);
        }

        // 2. Персонал не мучается верификацией на клиенте
        if (in_array($user->role, ['admin', 'moderator', 'support'])) {
            return $next($request);
        }

        // 3. ВАЖНО: Пропускаем AJAX, Livewire и API запросы!
        // Без этого фронтенд ломается (не работает выход, смена темы и т.д.),
        // потому что fetch() получает HTML редирект вместо JSON ответа.
        if ($request->ajax() || $request->is('livewire/*') || $request->is('api/*')) {
            return $next($request);
        }

        // Маршруты, которые доступны ВСЕГДА
        $allowedRoutes = ['verification.*', 'logout', 'onboarding.*'];

        // 4. Если юзер залогинен, но НЕ подтвердил email
        if (! $user->hasVerifiedEmail()) {
            if (! $request->routeIs($allowedRoutes)) {
                return redirect()->route('verification.notice');
            }
            return $next($request);
        }

        // 5. Если email подтвержден, но онбординг не пройден
        if (! $user->hasCompletedOnboarding()) {
            if (! $request->routeIs($allowedRoutes)) {
                return redirect()->route('onboarding.index');
            }
        }

        // 6. Если всё пройдено — пропускаем
        return $next($request);
    }
}