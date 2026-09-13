<?php

use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('guest')->group(function () {

    // === РЕГИСТРАЦИЯ ===


Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('/register/step1', [RegisterController::class, 'validateStep1'])->name('register.step1');
Route::post('/register/step2', [RegisterController::class, 'validateStep2'])->name('register.step2');
Route::post('/register/captcha', [RegisterController::class, 'refreshCaptcha'])->name('register.captcha');
Route::post('/register', [RegisterController::class, 'register'])->name('register.store');

    // Volt::route('login', 'pages.auth.login')
    //     ->name('login');
    
    Route::redirect('login', '/')->name('login');

    Volt::route('forgot-password', 'pages.auth.forgot-password')
        ->name('password.request');

    Volt::route('reset-password/{token}', 'pages.auth.reset-password')
        ->name('password.reset');
});

Route::middleware(['auth', 'onboarding'])->group(function () {
    Volt::route('verify-email', 'pages.auth.verify-email')
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Volt::route('confirm-password', 'pages.auth.confirm-password')
        ->name('password.confirm');
});
