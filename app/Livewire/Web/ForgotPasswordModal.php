<?php

namespace App\Livewire\Web;

use App\Services\CaptchaService;
use Livewire\Component;
use Illuminate\Support\Facades\Password;

class ForgotPasswordModal extends Component
{
    public bool $showModal = false;
    public string $email = '';
    public string $captchaImage = ''; 
    public string $captchaInput = ''; 
    public bool $emailSent = false;

    protected CaptchaService $captchaService;

    public function boot(CaptchaService $captchaService): void
    {
        $this->captchaService = $captchaService;
    }

    protected $listeners = ['open-forgot-password-modal' => 'openModal'];

    public function openModal($email = ''): void
    {
        $this->email = $email;
        $this->captchaInput = '';
        $this->emailSent = false;
        $this->generateCaptcha(); 
        $this->showModal = true;
    }

    public function generateCaptcha(): void
    {
        $this->captchaImage = $this->captchaService->generate('forgot_password_captcha');
    }

    public function refreshCaptcha(): void
    {
        $this->generateCaptcha();
        $this->captchaInput = ''; 
    }

    public function sendResetLink(): void
    {
        // ФИКС 1: Убрали 'exists:users,email'. Теперь хакер не сможет узнать, есть ли email в базе!
        $this->validate([
            'email' => 'required|email',
            'captchaInput' => 'required|string',
        ], [
            'captchaInput.required' => 'Введите код с картинки.',
        ]);

        // Проверяем капчу (сервис сам удалит её из сессии)
        if (!$this->captchaService->validate('forgot_password_captcha', $this->captchaInput)) {
            $this->addError('captchaInput', 'Неверный код с картинки. Попробуйте снова.');
            $this->refreshCaptcha(); 
            return;
        }

        // ФИКС 2: Laravel сам обработает несуществующий email и вернет RESET_LINK_SENT для безопасности.
        $status = Password::sendResetLink(['email' => $this->email]);

        if ($status === Password::RESET_LINK_SENT) {
            $this->emailSent = true;
        } elseif ($status === Password::RESET_THROTTLED) {
            // ФИКС 3: Защита от спама. Если юзер долбит кнопку, Laravel скажет "подождите".
            $this->addError('email', 'Слишком много попыток. Попробуйте позже.');
            $this->refreshCaptcha(); 
        } else {
            $this->addError('email', __($status));
            $this->refreshCaptcha(); 
        }
    }

    public function render()
    {
        return view('livewire.web.forgot-password-modal');
    }
}