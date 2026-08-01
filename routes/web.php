<?php

use App\Models\User;
use App\Models\PhotoComment;
use App\Notifications\CommentModerated;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Главная страница (Лендинг)
Route::get('/', function () {
    return view('landing');
})->name('home');

// Маршруты для авторизованных юзеров
Route::middleware(['auth', 'verified', 'role:user', 'onboarding'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::view('profile', 'profile')->name('profile');
});

// Онбординг (загрузка фото)
Volt::route('/photo-setup', 'register-photo-setup')
    ->middleware(['auth'])
    ->name('photo.setup');

// ============================================
// АДМИНКА (ВСЁ НА VOLT)
// ============================================
Route::middleware(['auth', 'verified', 'role:admin,moderator,support'])->prefix('admin')->name('admin.')->group(function () {

    // Дашборд
    Volt::route('/', 'admin.dashboard')->name('dashboard');

    // Пользователи
    Volt::route('/users', 'admin.users.index')->name('users.index');
    
    Volt::route('/users/{user}', 'admin.users.show')->name('users.show');

    // Чаты
    Volt::route('/communication/chats', 'admin.communication.chats')->name('communication.chats');
    Volt::route('/communication/support', 'admin.communication.support')->name('communication.support');
    Volt::route('/communication/diaries', 'admin.communication.diaries')->name('communication.diaries');
    Volt::route('/communication/stop-words', 'admin.communication.stop-words')->name('communication.stop-words');
    // Volt::route('/chats/support/{user_id}', 'admin.chats.support')->name('support.show');

    // черныек списки юзеров (блокировки)
    Volt::route('/security/blocks', 'admin.security.blocks')->name('security.blocks');
    Volt::route('/security/fraud-alerts', 'admin.security.fraud-alerts')->name('security.fraud-alerts');

    // Транзакции
    Volt::route('/finances/transactions', 'admin.finances.transactions')->name('finances.transactions');

    // Подписки на тарифы
    Volt::route('/finances/plans', 'admin.finances.plans')->name('finances.plans');
    Volt::route('/finances/gifts', 'admin.finances.gifts')->name('finances.gifts');


    // Настройки
    Volt::route('/system/settings', 'admin.system.settings')->name('system.settings');    
    // Системные логи
    Volt::route('/system/laravel-logs', 'admin.system.laravel-logs')->name('system.laravel-logs');
    Volt::route('/system/admin-logs', 'admin.system.admin-logs')->name('system.admin-logs');    
    // Оповещения пользователей
    Volt::route('/system/broadcasts', 'admin.system.broadcasts')->name('system.broadcasts');  
    Volt::route('/system/pages', 'admin.system.pages')->name('system.pages');    
    
   


    // Модерация фото 
    Volt::route('/moderation/photos', 'admin.moderation.photos')->name('moderation.photos');

    // Верификации юзеров
    Volt::route('/moderation/verifications', 'admin.moderation.verifications')->name('moderation.verifications');

    // Модерация комментарии к фото
    Volt::route('/moderation/comments', 'admin.moderation.comments')->name('moderation.comments');

    // Жалобы
    Volt::route('/moderation/reports', 'admin.moderation.reports')->name('moderation.reports');
    
    // Модерация знакомств
    Volt::route('/dating', 'admin.dating')->name('dating');

  
});

require __DIR__ . '/auth.php';
