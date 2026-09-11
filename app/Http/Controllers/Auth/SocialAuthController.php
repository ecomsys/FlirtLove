<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class SocialAuthController extends Controller
{
    // Редирект на соцсеть
    public function redirect(Request $request, $provider)
    {
        // Сохраняем режим (логин или регистрация) в сессию
        $mode = $request->query('mode', 'login');
        session(['social_auth_mode' => $mode]);

        return Socialite::driver($provider)->redirect();
    }

    // Обработка ответа от соцсети
    public function callback(Request $request, $provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect('/')->with('error', 'Не удалось авторизоваться через ' . ucfirst($provider));
        }

        // Ищем юзера по email или по ID соцсети
        $user = User::where('email', $socialUser->getEmail())->first();

        if ($user) {
            // Логинем существующего
            Auth::login($user, true);
            return redirect()->intended('/');
        }

        // Если юзера нет, сохраняем данные соцсети и кидаем на регистрацию с автозаполнением
        session([
            'social_provider' => $provider,
            'social_id' => $socialUser->getId(),
            'social_name' => $socialUser->getName(),
            'social_email' => $socialUser->getEmail(),
            'social_avatar' => $socialUser->getAvatar(),
        ]);

        return redirect()->route('register')->with('social_data', true);
    }
}