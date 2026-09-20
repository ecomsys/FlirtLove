<?php

namespace App\Http\Controllers\Web\Subscriptions;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class VipController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // ЗАЩИТА: Если у юзера уже есть VIP, пускаем его на главную
        if ($user->has_active_vip) {
            return redirect()->route('home')->with('error', 'У вас уже активен VIP-статус!');
        }

        $likesCount = 14;
        $avatars = [
            'https://i.pravatar.cc/100?img=1',
            'https://i.pravatar.cc/100?img=5',
            'https://i.pravatar.cc/100?img=9',
            'https://i.pravatar.cc/100?img=20',
            'https://i.pravatar.cc/100?img=33'
        ];

        $benefits = [
            ['icon' => 'top', 'text' => 'Показ в Топе раз в три дня'],
            ['icon' => 'photo', 'text' => 'Фото показываются первыми в знакомствах'],
            ['icon' => 'message', 'text' => 'Сообщения показываются выше других'],
        ];

        return view('pages.subscriptions.vip', compact('likesCount', 'avatars', 'benefits'));
    }
}