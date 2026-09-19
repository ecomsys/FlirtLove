<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function updateTheme(Request $request)
    {
        $validated = $request->validate([
            'theme' => 'required|in:light,dark'
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($user) {
            UserPreference::updateOrCreate(
                ['user_id' => $user->id],
                ['theme' => $validated['theme']]
            );
        }

        return response()->json(['success' => true]);
    }
}