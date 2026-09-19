<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class VerificationController extends Controller
{
    /**
     * Показываем страницу "Проверьте почту"
     */
    public function notice(Request $request)
    {
        return view('pages.auth.verify-email');
    }

    /**
     * Обработка клика по ссылке из письма (Подтверждение email)
     */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended($this->getRedirectUrl($user).'?verified=1');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->intended($this->getRedirectUrl($user).'?verified=1');
    }

    /**
     * Определяем, куда кидать юзера после верификации
     */
    private function getRedirectUrl($user): string
    {
        if (in_array($user->role, ['admin', 'moderator', 'support'])) {
            return route('admin.dashboard');
        }

        return route('home');
    }

    /**
     * AJAX: Повторная отправка письма подтверждения
     */
    public function send(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true, 
                'redirect' => route('home')
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['success' => true]);
    }

    /**
     * AJAX: Смена email-адреса и отправка нового письма
     */
    public function changeEmail(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($user->id)],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->email = $request->email;
        $user->email_verified_at = null;
        $user->save();

        $user->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'email' => $user->email
        ]);
    }
}