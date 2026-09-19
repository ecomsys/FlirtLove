<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CaptchaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;

class ForgotPasswordController extends Controller
{
    public function getCaptcha(CaptchaService $captchaService)
    {
        $image = $captchaService->generate('forgot_password_captcha');
        return response()->json(['image' => $image]);
    }

    public function sendResetLink(Request $request, CaptchaService $captchaService)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'captchaInput' => 'required|string',
        ], [
            'captchaInput.required' => 'Введите код с картинки.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!$captchaService->validate('forgot_password_captcha', $request->captchaInput)) {
            return response()->json([
                'errors' => ['captchaInput' => ['Неверный код с картинки. Попробуйте снова.']]
            ], 422);
        }

        $status = Password::sendResetLink(['email' => $request->email]);

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json(['success' => true]);
        } elseif ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'errors' => ['email' => ['Слишком много попыток. Попробуйте позже.']]
            ], 422);
        } else {
            return response()->json([
                'errors' => ['email' => [__($status)]]
            ], 422);
        }
    }
}