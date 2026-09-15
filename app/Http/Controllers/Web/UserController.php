<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
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

        return view('home.user', compact('user'));
    }
}