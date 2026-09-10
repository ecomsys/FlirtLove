<?php

namespace App\Livewire\Web;

use App\Services\CaptchaService;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

class LoginModal extends Component
{
    public bool $showModal = false;
    public string $email = '';
    public string $password = '';
    public bool $remember = false; // НОВОЕ СВОЙСТВО (Чужой компьютер)
    
    public string $captchaImage = ''; 
    public string $captchaInput = ''; 

    protected CaptchaService $captchaService;

    public function boot(CaptchaService $captchaService): void
    {
        $this->captchaService = $captchaService;
    }

    protected $listeners = [
        'open-login-modal' => 'openModal',
        'open-login-modal-with-creds' => 'openModalWithCreds'
    ];

    public function openModal(): void
    {
        $this->captchaInput = '';
        $this->generateCaptcha();
        $this->showModal = true;
    }

    public function openModalWithCreds($email = '', $password = ''): void
    {
        $this->email = $email;
        $this->password = $password;
        $this->captchaInput = '';
        $this->generateCaptcha();
        $this->showModal = true;
    }

    public function generateCaptcha(): void
    {
        $this->captchaImage = $this->captchaService->generate('login_captcha');
    }

    public function refreshCaptcha(): void
    {
        $this->generateCaptcha();
        $this->captchaInput = '';
    }

    public function login(): void
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'captchaInput' => 'required|string',
        ], [
            'captchaInput.required' => 'Введите код с картинки.',
        ]);

        if (!$this->captchaService->validate('login_captcha', $this->captchaInput)) {
            $this->addError('captchaInput', 'Неверный код с картинки. Попробуйте снова.');
            $this->refreshCaptcha();
            return;
        }

        // ФИКС: Передаем $this->remember в Auth::attempt
        // Если "Чужой компьютер" включен, remember будет false, и Laravel не запомнит сессию надолго
        if (!Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            throw ValidationException::withMessages([
                'email' => 'Неверный email или пароль.',
            ]);
        }

        Session::regenerate();

        $user = Auth::user();
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ]);

        if ($user->isStaff()) {
            $this->redirect(route('admin.dashboard'), navigate: true);
            return;
        }

        $this->redirectIntended(default: route('home'), navigate: true);
    }

    public function render()
    {
        return view('livewire.web.login-modal');
    }
}