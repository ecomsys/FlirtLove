<?php

namespace App\Livewire\Web\Modals;

use App\Services\CaptchaService;
use Livewire\Component;
use Illuminate\Support\Facades\Password;

class ForgotPasswordModal extends Component
{
    // Убрали $showModal
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
        // Убрали $this->showModal = true;
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
        $this->validate([
            'email' => 'required|email',
            'captchaInput' => 'required|string',
        ], [
            'captchaInput.required' => 'Введите код с картинки.',
        ]);

        if (!$this->captchaService->validate('forgot_password_captcha', $this->captchaInput)) {
            $this->addError('captchaInput', 'Неверный код с картинки. Попробуйте снова.');
            $this->refreshCaptcha(); 
            return;
        }

        $status = Password::sendResetLink(['email' => $this->email]);

        if ($status === Password::RESET_LINK_SENT) {
            $this->emailSent = true;
        } elseif ($status === Password::RESET_THROTTLED) {
            $this->addError('email', 'Слишком много попыток. Попробуйте позже.');
            $this->refreshCaptcha(); 
        } else {
            $this->addError('email', __($status));
            $this->refreshCaptcha(); 
        }
    }

    public function render()
    {
        return view('livewire.web.modals.forgot-password-modal');
    }
}