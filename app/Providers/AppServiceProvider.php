<?php

namespace App\Providers;

use App\Models\Photo;
use App\Models\UserSubscription;

use App\Services\GeoIPBlockService;
use App\Services\StopWordsFilterService;

use App\Observers\UserSubscriptionObserver;
use App\Observers\PhotoObserver;

use App\Listeners\InvalidateOldSessions;
use App\Listeners\MigrateGuestTheme;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;

use App\Models\SubscriptionPlan;

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
        // Передаем активные тарифы Премиума и VIP во все вьюхи
        View::share('premiumPlans', SubscriptionPlan::where('tier', 'premium')->active()->ordered()->get());
        View::share('vipPlans', SubscriptionPlan::where('tier', 'vip')->active()->ordered()->get());


        // делаем доступными во всех лейаутах переменные
        View::composer('*', function ($view) {
            // Теперь isAuth проверяется прямо перед рендером страницы!
            $isAuth = Auth::check();

            $dbTheme = $isAuth ? Auth::user()?->preferences?->theme : null;
            if (!in_array($dbTheme, ['light', 'dark'], true)) {
                $dbTheme = null;
            }

            $cookieTheme = request()->cookie('theme');
            if (!in_array($cookieTheme, ['light', 'dark'], true)) {
                $cookieTheme = null;
            }

            // Отдаем обе переменные в шаблон
            $view->with([
                'isAuth' => $isAuth,
                'theme'  => $dbTheme ?? $cookieTheme ?? 'light',
            ]);
        });

        // Перенос темы из браузера в БД при регистрации
        Event::listen(Login::class, MigrateGuestTheme::class);

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
