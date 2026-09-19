<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CaptchaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator; // <--- Подключили фасад

class LoginController extends Controller
{
    public function getCaptcha(CaptchaService $captchaService)
    {
        $image = $captchaService->generate('login_captcha');
        return response()->json(['image' => $image]);
    }

    public function ajaxLogin(Request $request, CaptchaService $captchaService)
    {
        // 1. Ручная валидация (железобетонная, не выбрасывает исключений)
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            'captchaInput' => 'required|string',
        ], [
            'captchaInput.required' => 'Введите код с картинки.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        // 2. Проверяем капчу
        if (!$captchaService->validate('login_captcha', $request->captchaInput)) {
            return response()->json([
                'errors' => ['captchaInput' => ['Неверный код с картинки. Попробуйте снова.']]
            ], 422);
        }

        // 3. Пытаемся залогинить
        if (!Auth::attempt(['email' => $request->email, 'password' => $request->password], $request->boolean('remember'))) {
            return response()->json([
                'errors' => ['email' => ['Неверный email или пароль.']]
            ], 422);
        }

        $request->session()->regenerate();

        // Обновляем данные юзера
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        // Определяем, куда редиректить
        $redirectUrl = route('home'); // По умолчанию
        
        if ($user->isStaff()) {
            $redirectUrl = route('admin.dashboard');
        } elseif (! $user->hasCompletedOnboarding()) {
            $redirectUrl = route('onboarding.index');
        }

        return response()->json([
            'success' => true,
            'redirect' => $redirectUrl
        ]);
    }
}