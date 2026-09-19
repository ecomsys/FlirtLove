<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ConfirmPasswordController extends Controller
{
    /**
     * Показываем форму подтверждения пароля
     */
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // Защита: если у юзера вообще нет пароля (регистрировался через соцсеть),
        // ему нечего подтверждать. Выкидываем на главную.
        if (empty($user->password)) {
            return redirect()->route('home');
        }

        return view('pages.auth.confirm-password');
    }

    /**
     * Обработка AJAX-запроса подтверждения пароля
     */
    public function confirm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        // Проверяем пароль
        if (! Hash::check($request->password, $user->password)) {
            return response()->json([
                'errors' => ['password' => [__('auth.failed_password')]]
            ], 422);
        }

        // Записываем в сессию время подтверждения
        $request->session()->put('auth.password_confirmed_at', time());

        // Определяем, куда редиректить
        $redirectUrl = redirect()->intended(route('home'))->getTargetUrl();

        return response()->json([
            'success' => true,
            'redirect' => $redirectUrl
        ]);
    }
}