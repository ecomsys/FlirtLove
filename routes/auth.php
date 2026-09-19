<?php

use App\Http\Controllers\Auth\ConfirmPasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\VerificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// === ГОСТЕВЫЕ РОУТЫ (Регистрация, Логин, Сброс пароля) ===
Route::middleware('guest')->group(function () {
    // Регистрация
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register/step1', [RegisterController::class, 'validateStep1'])->name('register.step1');
    Route::post('/register/step2', [RegisterController::class, 'validateStep2'])->name('register.step2');
    Route::post('/register/captcha', [RegisterController::class, 'refreshCaptcha'])->name('register.captcha');
    Route::post('/register', [RegisterController::class, 'register'])->name('register.store');
    
    // Логин (редирект на главную с флагом для открытия модалки)
    Route::redirect('login', '/?login=1')->name('login');

    // Сброс пароля (Страница ввода нового пароля из письма)
    Route::get('reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');
});

// === АВТОРИЗОВАННЫЕ РОУТЫ (Верификация, Выход, Подтверждение пароля) ===
Route::middleware(['auth'])->group(function () {
    // Выход
    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    })->name('logout');

    // Верификация Email
    Route::get('verify-email', [VerificationController::class, 'notice'])->name('verification.notice');
    Route::post('verify-email/send', [VerificationController::class, 'send'])->name('verification.send');
    Route::post('verify-email/change', [VerificationController::class, 'changeEmail'])->name('verification.change');
    
    Route::get('verify-email/{id}/{hash}', [VerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // Подтверждение пароля (перед опасными действиями)
    Route::get('confirm-password', [ConfirmPasswordController::class, 'index'])->name('password.confirm');
    Route::post('confirm-password', [ConfirmPasswordController::class, 'confirm'])->name('password.confirm.post');
});