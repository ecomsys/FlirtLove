<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class RedirectIfAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && Auth::user()->is_admin) {
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}

// Скрываем Админа из поиска и ленты (Заранее)
// Так как админ есть в таблице users, нам нужно убедиться, что когда мы начнем делать ленту анкет (свайпы), мы никогда не достаем админов из базы.

// Для этого в модели User (на будущее) мы всегда будем делать запрос так:

// php

// User::where('is_admin', false)->...
// А чтобы в будущем юзеры вообще не могли найти админа по имени или ID, можно добавить в App\Models\User метод, который мы будем использовать в поиске:

// php

// // В app/Models/User.php
// public function scopePublicUsers($query)
// {
//     return $query->where('is_admin', false);
// }
// (Позже, когда будем делать ленту, мы напишем: User::publicUsers()->where(...)->get()).