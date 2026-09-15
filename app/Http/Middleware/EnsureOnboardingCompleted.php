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

        // 1. Если гость — просто пропускаем. Главные страницы работают для всех!
        if (! $user) {
            return $next($request);
        }

        // 2. Персонал (админ/модератор) не мучается верификацией на клиенте
        if (in_array($user->role, ['admin', 'moderator', 'support'])) {
            return $next($request);
        }

        // 3. Если юзер залогинен, но НЕ подтвердил email
        // Пускаем его ТОЛЬКО на страницы верификации, выхода и онбординга.
        // Любая другая ссылка (даже на главную) кидает его обратно на verify-email
        if (! $user->hasVerifiedEmail()) {
            if (! $request->routeIs('verification.*') && ! $request->routeIs('logout') && ! $request->routeIs('onboarding.*')) {
                return redirect()->route('verification.notice');
            }
            return $next($request);
        }

        // 4. Если email подтвержден, но онбординг не пройден
        // Пускаем только на онбординг, выход и верификацию
        if (! $user->hasCompletedOnboarding()) {
            if (! $request->routeIs('onboarding.*') && ! $request->routeIs('logout') && ! $request->routeIs('verification.*')) {
                return redirect()->route('onboarding.index');
            }
        }

        // 5. Если всё пройдено — пропускаем
        return $next($request);
    }
}