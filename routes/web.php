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

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {

    // ============================================
    // ЗОНА 1: Доступно ВСЕМ сотрудникам (Admin, Moderator, Support)
    // ============================================
    Route::middleware('role:admin,moderator,support')->group(function () {
        Volt::route('/', 'admin.dashboard.index')->name('dashboard');
        
        // Базовый просмотр юзеров (саппорт должен видеть профиль, чтобы помочь)
        Volt::route('/users', 'admin.users.index')->name('users.index');
        Volt::route('/users/{user}', 'admin.users.show')->name('users.show');

        // Саппорт-чат
        Volt::route('/communication/support', 'admin.communication.support')->name('communication.support');
    });

    // ============================================
    // ЗОНА 2: Доступно Модераторам и Админам (Управление контентом и безопасностью)
    // ============================================
    Route::middleware('role:admin,moderator')->group(function () {
        // Модерация
        Volt::route('/media', 'admin.media.index')->name('media.index');

        Volt::route('/moderation/photos', 'admin.moderation.photos')->name('moderation.photos');
        Volt::route('/moderation/comments', 'admin.moderation.comments')->name('moderation.comments');
        Volt::route('/moderation/dating', 'admin.moderation.dating')->name('moderation.dating');
        Volt::route('/moderation/reports', 'admin.moderation.reports')->name('moderation.reports');

        // Коммуникация (Модеры проверяют дневники и чаты на спам)
        Volt::route('/communication/chats', 'admin.communication.chats')->name('communication.chats');
        Volt::route('/communication/diaries', 'admin.communication.diaries')->name('communication.diaries');
        Volt::route('/communication/stop-words', 'admin.communication.stop-words')->name('communication.stop-words');
        Volt::route('/communication//templates', 'admin.communication.templates')->name('communication.templates');

        // Безопасность
        Volt::route('/security/blocks', 'admin.security.blocks')->name('security.blocks');
        Volt::route('/security/fraud-alerts', 'admin.security.fraud-alerts')->name('security.fraud-alerts');
    });

    // ============================================
    // ЗОНА 3: Доступно ТОЛЬКО Админам (Бог-режим)
    // ============================================
    Route::middleware('role:admin')->group(function () {
        // Финансы
        Volt::route('/finances/transactions', 'admin.finances.transactions')->name('finances.transactions');
        Volt::route('/finances/plans', 'admin.finances.plans')->name('finances.plans');
        Volt::route('/finances/gifts', 'admin.finances.gifts')->name('finances.gifts');

        // Система
        Volt::route('/system/settings', 'admin.system.settings')->name('system.settings');

        // Страницы
        // Volt::route('/system/pages', 'admin.system.pages')->name('system.pages');
        Volt::route('/system/pages', 'admin.system.pages.index')->name('system.pages.index');
        Volt::route('/system/pages/create', 'admin.system.pages.form')->name('system.pages.create');
        Volt::route('/system/pages/{page}/edit', 'admin.system.pages.form')->name('system.pages.edit');
        
        Volt::route('/system/broadcasts', 'admin.system.broadcasts.index')->name('system.broadcasts.index');
        Volt::route('/system/broadcasts/create', 'admin.system.broadcasts.form')->name('system.broadcasts.create');
        Volt::route('/system/broadcasts/{broadcast}/edit', 'admin.system.broadcasts.form')->name('system.broadcasts.edit');
       
        Volt::route('/system/journal-logs', 'admin.system.journal-logs')->name('system.journal-logs');
        Volt::route('/system/laravel-logs', 'admin.system.laravel-logs')->name('system.laravel-logs');
        
        // НОВАЯ СТРАНИЦА: Управление персоналом
        Volt::route('/system/roles', 'admin.system.roles')->name('system.roles');
    });
});

require __DIR__ . '/auth.php';
