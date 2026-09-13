<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CaptchaService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules;
use Carbon\Carbon;

class RegisterController extends Controller
{
    protected CaptchaService $captchaService;

    public function __construct(CaptchaService $captchaService)
    {
        $this->captchaService = $captchaService;
    }

    public function showRegistrationForm()
    {
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[$m] = Carbon::create()->month($m)->translatedFormat('F');
        }

        $days = range(1, 31);
        $years = range(2010, 1950);

        $captchaImage = $this->captchaService->generate('register_captcha');

        return view('auth.register', compact('months', 'days', 'years', 'captchaImage'));
    }

    // Общий метод для жёсткого возврата JSON ошибок
    protected function validateJson(Request $request, array $rules)
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors()
            ], 422);
        }

        return null;
    }

    public function validateStep1(Request $request)
    {
        $rules = [
            'birth_day' => ['required', 'integer', 'between:1,31'],
            'birth_month' => ['required', 'integer', 'between:1,12'],
            'birth_year' => ['required', 'integer', 'between:1950,2010'],
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female'],
        ];

        if ($errorResponse = $this->validateJson($request, $rules)) {
            return $errorResponse;
        }

        $validated = $request->only(array_keys($rules));

        if (!checkdate((int)$validated['birth_month'], (int)$validated['birth_day'], (int)$validated['birth_year'])) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => ['birth_day' => ['Указана несуществующая дата рождения.']]
            ], 422);
        }

        return response()->json(['success' => true]);
    }

    public function validateStep2(Request $request)
    {
        $rules = [
            'dating_goal' => ['required', 'in:friends,romantic,family,casual,travel'],
        ];

        if ($errorResponse = $this->validateJson($request, $rules)) {
            return $errorResponse;
        }

        $captchaImage = $this->captchaService->generate('register_captcha');

        return response()->json(['success' => true, 'captchaImage' => $captchaImage]);
    }

    public function refreshCaptcha()
    {
        $captchaImage = $this->captchaService->generate('register_captcha');
        return response()->json(['captchaImage' => $captchaImage]);
    }

    public function register(Request $request)
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female'],
            'birth_day' => ['required', 'integer', 'between:1,31'],
            'birth_month' => ['required', 'integer', 'between:1,12'],
            'birth_year' => ['required', 'integer', 'between:1950,2010'],
            'dating_goal' => ['required', 'in:friends,romantic,family,casual,travel'],
            'city' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'string', Rules\Password::defaults()],
            'captchaInput' => ['required', 'string'],
        ];

        if ($errorResponse = $this->validateJson($request, $rules)) {
            return $errorResponse;
        }

        $validated = $request->only(array_keys($rules));

        if (!checkdate((int)$validated['birth_month'], (int)$validated['birth_day'], (int)$validated['birth_year'])) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => ['birth_day' => ['Указана несуществующая дата рождения.']]
            ], 422);
        }

        if (!$this->captchaService->validate('register_captcha', $validated['captchaInput'])) {
            // Если капча не прошла — генерируем новую!
            $newCaptcha = $this->captchaService->generate('register_captcha');
            
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => ['captchaInput' => ['Неверный код с картинки. Попробуйте снова.']],
                'captchaImage' => $newCaptcha
            ], 422);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $birthDate = sprintf('%04d-%02d-%02d', $validated['birth_year'], $validated['birth_month'], $validated['birth_day']);

        $user->profile->update([
            'gender' => $validated['gender'],
            'birth_date' => $birthDate,
            'dating_goal' => $validated['dating_goal'],
            'city' => $validated['city'],
        ]);

        event(new Registered($user));
        Auth::login($user);

        return response()->json(['redirect' => route('onboarding.index')]);
    }
}