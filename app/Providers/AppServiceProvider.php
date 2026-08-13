<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Photo;

use App\Models\UserSubscription;
use App\Observers\UserSubscriptionObserver;
use App\Observers\PhotoObserver;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;

use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\Route;


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
        // наблюдаем за измененимя чтобы сразу обновлять таблицу
        Photo::observe(PhotoObserver::class);

        // Когда мы создаем подписку (например, юзер оплатил VIP), нам нужно обновить поля is_premium и premium_expires_at в таблице users (чтобы middleware работало быстро).   
        UserSubscription::observe(UserSubscriptionObserver::class);    
        
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
