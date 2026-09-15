<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class HomeController extends Controller
{
    public function index()
    {
        if (Auth::check() && in_array(Auth::user()->role, ['admin', 'moderator', 'support'])) {
            return Redirect::route('admin.dashboard');
        }

        $autoOpenLogin = request()->query('login') === '1' && !auth()->check();

        $userGender = auth()->user()?->profile?->gender;
        $defaultSearchGender = $userGender ? ($userGender === 'male' ? 'female' : 'male') : 'any';

        return view('home.index', [
            'autoOpenLogin' => $autoOpenLogin,
            'searchConfig' => config('profile_fields'),
            'defaultSearchGender' => $defaultSearchGender
        ]);
    }
}