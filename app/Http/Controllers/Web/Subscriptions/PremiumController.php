<?php

namespace App\Http\Controllers\Web\Subscriptions;

use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PremiumController extends Controller
{
    public function index(Request $request, User $user)
    {

        /** @var \App\Models\User $authUser */
        $user = $request->user();

        // ЗАЩИТА: Если у юзера уже есть Премиум, пускаем его на главную
        if ($user->has_active_premium) {
            return redirect()->route('home')->with('error', 'У вас уже активен Премиум-доступ!');
        }

        // В будущем тут будем доставать реальное количество лайков и аватарки из БД
        $likesCount = 14;
        $avatars = [
            'https://i.pravatar.cc/100?img=1',
            'https://i.pravatar.cc/100?img=5',
            'https://i.pravatar.cc/100?img=9',
            'https://i.pravatar.cc/100?img=20',
            'https://i.pravatar.cc/100?img=33'
        ];

        $benefits = [
            ['icon' => 'message', 'text' => 'Свободно пиши всем девушкам, которые понравились'],
            ['icon' => 'heart', 'text' => 'Посмотри, кто поставил тебе лайк и не против встретиться'],
            ['icon' => 'users', 'text' => 'Увеличь шансы в 10 раз — напиши сразу нескольким девушкам'],
            ['icon' => 'star', 'text' => 'Получи преимущество в общении с новыми и популярными девушками'],
            ['icon' => 'eye', 'text' => 'Просматривай анкеты в режиме "Невидимки"'],
            ['icon' => 'ban', 'text' => 'Отключи показ рекламы'],
        ];

        return view('pages.subscriptions.premium', compact('likesCount', 'avatars', 'benefits'));
    }
}