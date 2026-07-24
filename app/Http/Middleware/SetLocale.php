<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            App::setLocale(Auth::user()->locale);
        } else {
            App::setLocale(session()->get('locale', config('app.locale')));
        }

        return $next($request);
    }
}