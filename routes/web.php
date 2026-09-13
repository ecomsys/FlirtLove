<?php
use App\Http\Controllers\Web\OnboardingController;

use App\Livewire\Web\Home;
use App\Livewire\Web\Blog\BlogIndex;
use App\Livewire\Web\Blog\BlogShow;
use App\Http\Controllers\Auth\SocialAuthController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// === ГЛАВНАЯ СТРАНИЦА ===
Route::get('/', Home::class)
    ->middleware('onboarding') // Теперь безопасно для гостей после фикса middleware
    ->name('home');

// === АВТОРИЗАЦИЯ ЧЕРЕЗ СОЦСЕТИ ===
Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->where('provider', 'vkontakte|odnoklassniki|mailru|yandex|google')
    ->name('social.redirect');
    
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->where('provider', 'vkontakte|odnoklassniki|mailru|yandex|google')
    ->name('social.callback');

// === БЛОГ ===
Route::get('/blog', BlogIndex::class)->name('blog.index');
Route::get('/blog/{post:slug}', BlogShow::class)->name('blog.show');

// === ОНБОРДИНГ ===
Route::get('/onboarding', [OnboardingController::class, 'index'])
    ->middleware(['auth'])
    ->name('onboarding.index');

Route::post('/onboarding/save', [OnboardingController::class, 'save'])
    ->middleware(['auth'])
    ->name('onboarding.save');

Route::post('/onboarding/skip', [OnboardingController::class, 'skip'])
    ->middleware(['auth'])
    ->name('onboarding.skip');

// === АВТОРИЗОВАННЫЕ МАРШРУТЫ ===
Route::middleware(['auth', 'verified', 'onboarding'])->group(function () {
    Route::view('profile', 'profile')->name('profile');
});

// === АДМИНКА ===
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

require __DIR__ . '/auth.php';