<?php

namespace App\Livewire\Modals;

use App\Services\CaptchaService;
use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

class LoginModal extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $remember = false; 
    
    public string $captchaImage = ''; 
    public string $captchaInput = ''; 

    protected CaptchaService $captchaService;

    public function boot(CaptchaService $captchaService): void
    {
        $this->captchaService = $captchaService;
    }
  

    #[On('open-login-modal')]
    public function openModal(): void
    {
        $this->reset(['email', 'password', 'captchaInput', 'remember']);
        $this->generateCaptcha();
    }

    #[On('open-login-modal-with-creds')]
    public function openModalWithCreds($email = '', $password = ''): void
    {
        $this->email = $email;
        $this->password = $password;
        $this->captchaInput = '';
        $this->generateCaptcha();
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

        if (!Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            // ФИКС: Обязательно обновляем капчу, так как предыдущая уже "сгорела" при валидации
            $this->refreshCaptcha();
            
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

        if (! $user->hasCompletedOnboarding()) {
            $this->redirect(route('onboarding.index', absolute: false), navigate: true);
            return;
        }

        $this->redirectIntended(default: route('home'), navigate: true);
    }

    public function render()
    {
        return view('livewire.modals.login-modal');
    }
}