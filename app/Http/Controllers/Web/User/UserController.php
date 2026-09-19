<?php

namespace App\Http\Controllers\Web\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserFavorite;
use App\Models\UserBlock;
use App\Models\Report;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function show(Request $request, User $user)
    {
        // Жадно загружаем связи
        $user->load([
            'profile.city', 
            'photos' => function($query) {
                $query->with('album')->where('status', 'approved')->orderByDesc('is_primary')->orderBy('position');
            },
            'preferences',
            'giftsReceived' => function($query) {
                $query->limit(6)->orderByDesc('created_at');
            }
        ]);

        $user->loadCount(['photos' => function($query) {
            $query->where('status', 'approved');
        }]);

        /** @var \App\Models\User|null $authUser */
        $authUser = $request->user();

        // Статусы для кнопок
        $isFavorited = $authUser ? UserFavorite::isFavorite($authUser->id, $user->id) : false;
        $isBlocked = $authUser ? UserBlock::isBlocked($authUser->id, $user->id) : false;
        $isReported = $authUser ? Report::where('reporter_id', $authUser->id)
            ->where('reported_id', $user->id)
            ->where('status', Report::STATUS_PENDING)
            ->exists() : false;

        // Если у тебя файл называется user.blade.php (в папке home), то пиши так:
        return view('pages.home.user', compact('user', 'isFavorited', 'isBlocked', 'isReported'));
    }
}