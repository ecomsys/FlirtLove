<?php

namespace App\Http\Controllers\Web\Home;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if ($user && in_array($user->role, ['admin', 'moderator', 'support'])) {
            return Redirect::route('admin.dashboard');
        }

        $autoOpenLogin = $request->query('login') === '1' && !$user;
        $userGender = $user?->profile?->gender;
        $defaultSearchGender = $userGender ? ($userGender === 'male' ? 'female' : 'male') : 'any';

        $advFilters = collect(config('profile_fields.advanced_filters'))->map(fn($label, $value) => [
            'value' => $value, 
            'label' => $label
        ])->values()->toArray();

        // ДОБАВЛЯЕМ ПУСТОЙ ПУНКТ В НАЧАЛО МАССИВА
        array_unshift($advFilters, ['value' => 'none', 'label' => 'Дополнительное поле']);

        return view('pages.home.feed', [
            'autoOpenLogin' => $autoOpenLogin,
            'searchConfig' => config('profile_fields.options'),
            'advFilters' => $advFilters,
            'defaultSearchGender' => $defaultSearchGender
        ]);
    }

    public function searchPage(Request $request)
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if ($user && in_array($user->role, ['admin', 'moderator', 'support'])) {
            return Redirect::route('admin.dashboard');
        }

        $autoOpenLogin = $request->query('login') === '1' && !$user;
        $userGender = $user?->profile?->gender;
        $defaultSearchGender = $userGender ? ($userGender === 'male' ? 'female' : 'male') : 'any';

        $advFilters = collect(config('profile_fields.advanced_filters'))->map(fn($label, $value) => [
            'value' => $value, 
            'label' => $label
        ])->values()->toArray();
        array_unshift($advFilters, ['value' => 'none', 'label' => 'Дополнительное поле']);

        return view('pages.search.index', [
            'autoOpenLogin' => $autoOpenLogin,
            'searchConfig' => config('profile_fields.options'),
            'advFilters' => $advFilters,
            'defaultSearchGender' => $defaultSearchGender
        ]);
    }
}