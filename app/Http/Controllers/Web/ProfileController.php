<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserFavorite;
use App\Models\UserBlock;
use App\Models\Report;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show($id)
    {
        $user = User::with([
            'profile.city', 
            'photos' => function($query) {
                $query->with('album')->where('status', 'approved')->orderByDesc('is_primary')->orderBy('position');
            },
            'preferences',
            'giftsReceived' => function($query) {
                $query->limit(5)->orderByDesc('created_at');
            }
        ])->withCount(['photos' => function($query) {
            $query->where('status', 'approved');
        }])->findOrFail($id);

        // Вычисляем статусы для кнопок НА СЕРВЕРЕ
        $isFavorited = auth()->check() ? UserFavorite::isFavorite(auth()->id(), $user->id) : false;
        $isBlocked = auth()->check() ? UserBlock::isBlocked(auth()->id(), $user->id) : false;
        $isReported = auth()->check() ? Report::where('reporter_id', auth()->id())
            ->where('reported_id', $user->id)
            ->where('status', Report::STATUS_PENDING)
            ->exists() : false;

            

        return view('home.profile', compact('user', 'isFavorited', 'isBlocked', 'isReported'));
    }
}