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

        if (! $user || $user->hasCompletedOnboarding()) {
            return $next($request);
        }

        // Защита от цикла на случай, если мидлварь когда-нибудь
        // повесят глобально или на сам photo-setup
        if ($request->routeIs('onboarding.index')) {
            return $next($request);
        }

        // Запоминаем, куда юзер хотел — после онбординга можно вернуть
        redirect()->setIntendedUrl($request->url());

        return redirect()->route('onboarding.index');
    }
}