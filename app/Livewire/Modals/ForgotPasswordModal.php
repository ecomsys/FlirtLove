<?php

namespace App\Livewire\Modals;

use App\Services\CaptchaService;
use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Password;

class ForgotPasswordModal extends Component
{
    public string $email = '';
    public string $captchaImage = ''; 
    public string $captchaInput = ''; 
    public bool $emailSent = false;

    protected CaptchaService $captchaService;

    public function boot(CaptchaService $captchaService): void
    {
        $this->captchaService = $captchaService;
    }

    // Современный синтаксис Livewire 3
    #[On('open-forgot-password-modal')]
    public function openModal($email = ''): void
    {
        $this->email = $email;
        $this->captchaInput = '';
        $this->emailSent = false;
        $this->generateCaptcha(); 
    }

    /**
     * Киллер-фича: определяем URL для веб-почты на основе введенного домена
     */
    public function getEmailProviderUrlProperty(): string
    {
        if (empty($this->email)) return '#';
        
        $domain = substr(strrchr($this->email, "@"), 1);
        if (!$domain) return '#';

        $providers = [
            'gmail.com' => 'https://mail.google.com',
            'googlemail.com' => 'https://mail.google.com',
            'mail.ru' => 'https://e.mail.ru/inbox',
            'inbox.ru' => 'https://e.mail.ru/inbox',
            'list.ru' => 'https://e.mail.ru/inbox',
            'bk.ru' => 'https://e.mail.ru/inbox',
            'yandex.ru' => 'https://mail.yandex.ru',
            'yandex.by' => 'https://mail.yandex.ru',
            'ya.ru' => 'https://mail.yandex.ru',
            'outlook.com' => 'https://outlook.live.com/mail/0/inbox',
            'hotmail.com' => 'https://outlook.live.com/mail/0/inbox',
            'live.com' => 'https://outlook.live.com/mail/0/inbox',
            'icloud.com' => 'https://www.icloud.com/mail',
            'rambler.ru' => 'https://mail.rambler.ru/',
        ];

        return $providers[$domain] ?? 'https://' . $domain;
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
        return view('livewire.modals.forgot-password-modal');
    }
}