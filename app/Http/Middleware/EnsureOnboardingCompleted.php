<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Photo;

class EnsureOnboardingCompleted
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        
        if (!$user) {
            return $next($request);
        }
        
        // Проверяем наличие фото
        $hasPhotos = Photo::where('user_id', $user->id)->exists();
        
        // Если нет фото и флаг онбординга не установлен
        if (!$hasPhotos && !$user->has_completed_onboarding) {
            return redirect()->route('photo.setup');
        }
        
        return $next($request);
    }
}