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
Route::middleware(['auth', 'verified', 'redirect.admin', 'onboarding'])->group(function () {
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
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {

    // Дашборд
    Volt::route('/', 'admin.dashboard')->name('dashboard');

    // Пользователи
    Volt::route('/users', 'admin.users-index')->name('users.index');
    
    Volt::route('/users/{user}', 'admin.users-show')->name('users.show');

    // Чаты
    Volt::route('/chats', 'admin.chats-index')->name('chats.index');

    // Чаты поддержки
    Volt::route('/support', 'admin.chats-support')->name('support.index');
    Volt::route('/support/{user_id}', 'admin.chats-support')->name('support.show');

    // Жалобы
    Volt::route('/reports', 'admin.reports')->name('reports');

    // Финансы
    Volt::route('/finances', 'admin.finances')->name('finances');

    // Оповещения пользователей
    Volt::route('/broadcasts', 'admin.broadcasts')->name('broadcasts');

    // Системные логи
    Volt::route('/logs', 'admin.logs')->name('logs');

    // Модерация фото 
    Volt::route('/photos', 'admin.moderate-photos')->name('moderate-photos.index');

    // Модерация комментарии к фото
    Volt::route('/photo-comments', 'admin.moderate-photo-comments')->name('moderate-photo-comments');
    
    // Модерация знакомств
    Volt::route('/admin/moderate-dating', 'admin.moderate-dating')->name('moderate-dating');

    // Настройки
    Volt::route('/settings', 'admin.settings')->name('settings');    
  
});

require __DIR__ . '/auth.php';
