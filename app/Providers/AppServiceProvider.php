<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Photo;

use Illuminate\Support\Facades\Event;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\VKontakte\VKontakteExtendSocialite;
use SocialiteProviders\Odnoklassniki\OdnoklassnikiExtendSocialite;
use SocialiteProviders\MailRu\MailRuExtendSocialite;
use SocialiteProviders\Yandex\YandexExtendSocialite;

use App\Services\GeoIPBlockService;
use App\Services\StopWordsFilterService;

use App\Listeners\InvalidateOldSessions;

use App\Models\UserSubscription;
use App\Observers\UserSubscriptionObserver;
use App\Observers\PhotoObserver;


use Illuminate\Auth\Events\Login;
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
        $this->app->useLangPath(base_path('lang'));

        // Регистрируем Гео-сервис как Singleton (один экземпляр на весь жизненный цикл запроса)
        $this->app->singleton(GeoIPBlockService::class, function ($app) {
            return new GeoIPBlockService();
        });


        $this->app->singleton(StopWordsFilterService::class, function ($app) {
            return new StopWordsFilterService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Правильная регистрация SocialiteProviders
        Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('vkontakte', \SocialiteProviders\VKontakte\Provider::class);
            $event->extendSocialite('odnoklassniki', \SocialiteProviders\Odnoklassniki\Provider::class);
            $event->extendSocialite('yandex', \SocialiteProviders\Yandex\Provider::class);
            $event->extendSocialite('mailru', \SocialiteProviders\Mailru\Provider::class);
        });

        // выкидуем из старых сессий при новом входе а аккаунт
        Event::listen(Login::class, InvalidateOldSessions::class);

        // счетчик непрочитанных сообшений
        \App\Models\Message::observe(\App\Observers\MessageObserver::class);


        // наблюдаем за измененимя чтобы сразу обновлять таблицу
        Photo::observe(PhotoObserver::class);

        // Когда мы создаем подписку (например, юзер оплатил VIP), нам нужно обновить поля is_premium и premium_expires_at в таблице users (чтобы middleware работало быстро).   
        UserSubscription::observe(UserSubscriptionObserver::class);


        // Перенаправление после логина в зависимости от роли
        Event::listen(Login::class, function (Login $event) {
            $user = $event->user;

            // Очищаем старые намерения, чтобы не кидало на прошлые URL
            Session::forget('url.intended');

            if (in_array($user->role, ['admin', 'moderator', 'support'])) {
                // Персонал кидаем в админку
                Session::put('url.intended', route('admin.dashboard'));
            } else {
                // Обычных юзеров кидаем на главную (ленту анкет)
                Session::put('url.intended', route('home'));
            }
        });
    }
}
