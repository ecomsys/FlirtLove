<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
         // Перенаправление после логина в зависимости от роли
        Event::listen(Login::class, function (Login $event) {
            $user = $event->user;

            if (in_array($user->role, ['admin', 'moderator', 'support'])) {
                // Админов кидаем в админку
                Session::put('url.intended', route('admin.dashboard'));
            } else {
                // Обычных юзеров на их дашборд
                Session::put('url.intended', route('dashboard'));
            }
        });
    }
}
