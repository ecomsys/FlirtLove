<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Глобальные контроллеры
use App\Http\Controllers\GiftController;
use App\Http\Controllers\SwipeController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SubscriptionController;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\SocialAuthController;

// Контроллеры страниц
use App\Http\Controllers\Web\Subscriptions\VipController;
use App\Http\Controllers\Web\Subscriptions\PremiumController;
use App\Http\Controllers\Web\Blog\BlogController;
use App\Http\Controllers\Web\Home\HomeController;
use App\Http\Controllers\Web\Feed\FeedController;
use App\Http\Controllers\Web\Onboarding\OnboardingController;

use App\Http\Controllers\Web\User\UserController;
use App\Http\Controllers\Web\User\UserActionController;

// === ГЛАВНЫЕ СТРАНИЦЫ (Доступны всем) ===
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/user/{user}', [UserController::class, 'show'])->name('user.show');
Route::get('/search', [HomeController::class, 'searchPage'])->name('search.page');

// API для ленты
Route::get('/api/users/search', [FeedController::class, 'search'])->name('api.users.search');

// === AJAX РОУТЫ ДЛЯ МОДАЛОК (Доступны всем) ===
Route::get('/ajax/captcha/login', [LoginController::class, 'getCaptcha'])->name('ajax.captcha.login');
Route::post('/ajax/login', [LoginController::class, 'ajaxLogin'])->name('ajax.login');

Route::get('/ajax/captcha/forgot', [ForgotPasswordController::class, 'getCaptcha'])->name('ajax.captcha.forgot');
Route::post('/ajax/forgot-password', [ForgotPasswordController::class, 'sendResetLink'])->name('ajax.forgot.password');

// === БЛОГ ===
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{post:slug}', [BlogController::class, 'show'])->name('blog.show');

// === АВТОРИЗАЦИЯ ЧЕРЕЗ СОЦСЕТИ ===
Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->where('provider', 'vkontakte|odnoklassniki|mailru|yandex|google')
    ->name('social.redirect');

Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->where('provider', 'vkontakte|odnoklassniki|mailru|yandex|google')
    ->name('social.callback');

// === АВТОРИЗОВАННЫЕ ОБЫЧНЫЕ РОУТЫ (Фронтенд) ===
Route::middleware(['auth'])->group(function () {

    // Биллинг (Покупка кредитов)
    Route::post('/billing/pay', [BillingController::class, 'pay'])->name('billing.pay');
    Route::get('/billing/success', [BillingController::class, 'success'])->name('billing.success');
    Route::get('/api/billing/status/{transaction}', [BillingController::class, 'status'])->name('billing.status'); 
    
    // Премиум и VIP (Покупка подписки)
    Route::get('/premium', [PremiumController::class, 'index'])->name('premium.index');
    Route::post('/premium/pay', [SubscriptionController::class, 'pay'])->name('premium.pay');
    Route::get('/vip', [VipController::class, 'index'])->name('vip.index');
    Route::post('/vip/pay', [SubscriptionController::class, 'pay'])->name('vip.pay');
    Route::get('/subscription/success', [SubscriptionController::class, 'success'])->name('subscription.success');  
    Route::get('/api/subscription/status/{transaction}', [SubscriptionController::class, 'status'])->name('subscription.status');    

    // Настройки (тема)
    Route::post('/settings/theme', [SettingsController::class, 'updateTheme'])->name('settings.theme.update');

    // Онбординг
    Route::get('/onboarding', [OnboardingController::class, 'index'])->name('onboarding.index');
    Route::post('/onboarding/save', [OnboardingController::class, 'save'])->name('onboarding.save');
    Route::post('/onboarding/skip', [OnboardingController::class, 'skip'])->name('onboarding.skip');
 

    // Подарки и Свайпы
    Route::post('/user/{user}/swipe', [SwipeController::class, 'store'])->name('user.swipe');
    Route::post('/user/{user}/gift', [GiftController::class, 'store'])->name('user.gift');    

    // Взаимодействие с юзером (Чат, Избранное, Блок, Жалоба)
    Route::post('/user/{user}/chat', [UserActionController::class, 'chat'])->name('user.chat');
    Route::post('/user/{user}/favorite', [UserActionController::class, 'toggleFavorite'])->name('user.favorite');
    Route::post('/user/{user}/block', [UserActionController::class, 'toggleBlock'])->name('user.block');
    Route::post('/user/{user}/report', [UserActionController::class, 'report'])->name('user.report');
});

// === АДМИНКА (LIVEWIRE/VOLT) ===
Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // ЗОНА 1: Админ, Модератор, Саппорт
        Route::middleware('role:admin,moderator,support')->group(function () {
            Volt::route('/', 'admin.dashboard.index')->name('dashboard');
            Volt::route('/users', 'admin.users.index')->name('users.index');
            Volt::route('/users/{user}', 'admin.users.show')->name('users.show');
            Volt::route('/communication/support', 'admin.communication.support')->name('communication.support');
            Volt::route('/communication/templates', 'admin.communication.templates')->name('communication.templates');
        });

        // ЗОНА 2: Админ, Модератор
        Route::middleware('role:admin,moderator')->group(function () {      
            Volt::route('/moderation/dating', 'admin.moderation.dating')->name('moderation.dating');
            Volt::route('/moderation/reports', 'admin.moderation.reports')->name('moderation.reports');
            Volt::route('/moderation/photos', 'admin.moderation.photos')->name('moderation.photos');        
            Volt::route('/moderation/photo-comments', 'admin.moderation.photo-comments')->name('moderation.photo-comments');

            Volt::route('/moderation/diaries', 'admin.moderation.diary.index')->name('moderation.diary.index');
            Volt::route('/moderation/diaries/{diary}/moderate', 'admin.moderation.diary.moderate')->name('moderation.diary.moderate');
            Volt::route('/moderation/diaries/comments', 'admin.moderation.diary.comments')->name('moderation.diary.comments');

            Volt::route('/communication/chats', 'admin.communication.chats')->name('communication.chats');        
            Volt::route('/communication/stop-words', 'admin.communication.stop-words.index')->name('communication.stop-words.index');     

            Volt::route('/security/block-signals', 'admin.security.block-signals.index')->name('security.block-signals.index');
            Volt::route('/security/fraud-alerts', 'admin.security.fraud-alerts.index')->name('security.fraud-alerts.index');
        });

        // ЗОНА 3: Только Админ
        Route::middleware('role:admin')->group(function () {
            Volt::route('/media', 'admin.media.index')->name('media.index');

            Volt::route('/finances/transactions', 'admin.finances.transactions')->name('finances.transactions');
            Volt::route('/finances/subscriptions', 'admin.finances.subscriptions')->name('finances.subscriptions');
            Volt::route('/finances/gifts', 'admin.finances.gifts')->name('finances.gifts');

            Volt::route('/system/settings', 'admin.system.settings')->name('system.settings');

            Volt::route('/system/pages', 'admin.system.pages.index')->name('system.pages.index');
            Volt::route('/system/pages/create', 'admin.system.pages.form')->name('system.pages.create');
            Volt::route('/system/pages/{page}/edit', 'admin.system.pages.form')->name('system.pages.edit');
            
            Volt::route('/system/broadcasts', 'admin.system.broadcasts.index')->name('system.broadcasts.index');
            Volt::route('/system/broadcasts/create', 'admin.system.broadcasts.form')->name('system.broadcasts.create');
            Volt::route('/system/broadcasts/{broadcast}/edit', 'admin.system.broadcasts.form')->name('system.broadcasts.edit');
                        
            Volt::route('/system/admin-logs', 'admin.system.admin-logs')->name('system.admin-logs');
            Volt::route('/system/laravel-logs', 'admin.system.laravel-logs')->name('system.laravel-logs');
            Volt::route('/system/geo-ip-locations', 'admin.system.geo-ip-locations.index')->name('system.geo-ip-locations.index');
            Volt::route('/system/roles', 'admin.system.roles')->name('system.roles');

            Volt::route('/system/blog', 'admin.system.blog.index')->name('system.blog.index');
            Volt::route('/system/blog/create', 'admin.system.blog.form')->name('system.blog.create');
            Volt::route('/system/blog/{post}/edit', 'admin.system.blog.form')->name('system.blog.edit');
        });
    });

// Подключаем файл с роутами авторизации
require __DIR__ . '/auth.php';