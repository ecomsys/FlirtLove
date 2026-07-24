<?php

use App\Livewire\Actions\Logout;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component 
{
    // Свойства для формы смены почты
    public bool $showEmailForm = false;
    public string $newEmail = '';

    /**
     * Определяем URL для веб-почты на основе домена юзера
     */
    public function getEmailProviderUrlProperty(): string
    {
        $email = Auth::user()->email;
        $domain = substr(strrchr($email, "@"), 1);

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

    /**
     * Отправить письмо повторно
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
            return;
        }

        Auth::user()->sendEmailVerificationNotification();
        
        $this->dispatch('show-toast', type: 'success', message: __('auth.resend_success'));
    }

    /**
     * Сохранить новый email
     */
    public function changeEmail(): void
    {
         $validated = $this->validate([
            'newEmail' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore(Auth::id())],
        ]);

        $user = Auth::user();
        $user->email = $validated['newEmail'];
        $user->email_verified_at = null;
        $user->save();

        $user->sendEmailVerificationNotification();
        
        $this->showEmailForm = false;
        $this->reset('newEmail');
        
        $this->dispatch('show-toast', type: 'success', message: __('auth.resend_success'));

        $this->dispatch('profile-updated', email: $user->email);
    }

    /**
     * Выход
     */
    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }
}; ?>

<section class="w-full">
    <div class="bg-card text-card-foreground px-4">
        <div class="relative max-w-md mx-auto px-6 py-10 bg-card text-card-foreground">
            <div class="hidden md:flex absolute -left-30 top-10 w-24 h-24 rounded-full bg-primary/10 items-center justify-center">
                <svg class="w-14 h-14 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                </svg>
            </div>
            <h1 class="text-2xl font-semibold mb-6">{{ __('auth.check_email') }}</h1>

            <p>
                <span>{{ __('auth.check_email_desc') }}</span>
                <span class="text-primary font-medium"> {{ Auth::user()->email }}</span>
            </p>
        </div>
    </div>

    <div class="w-full max-w-md mx-auto py-10 px-4 bg-background text-foreground">

        <!-- Кнопки действий -->
        <div class="max-w-[18rem] flex flex-col gap-3 mb-10 mx-auto">
            
            <!-- Динамическая кнопка перехода в почту -->
            <x-ui.button as-child class="w-full">
                <a href="{{ $this->emailProviderUrl }}" target="_blank" class="flex items-center justify-center gap-2">
                    {{ __('auth.go_to_email') }}
                </a>
            </x-ui.button>

            <x-ui.button variant="outline" wire:click="sendVerification" class="w-full">
                {{ __('auth.resend_email') }}
            </x-ui.button>

            @if (session('status') == 'verification-link-sent')
                <div class="p-3 rounded-md bg-primary/10 border border-primary/20 text-primary text-sm text-center">
                    {{ __('auth.resend_success') }}
                </div>
            @endif
        </div>

        <!-- Разделитель -->
        <div class="relative mb-8">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-border"></div>
            </div>
            <div class="relative flex justify-center text-xs uppercase">
                <span class="bg-background px-2 text-muted-foreground">{{ __('auth.what_if_no_email') }}</span>
            </div>
        </div>

        <!-- Блок помощи -->
        <div class="space-y-4 text-sm mb-8">
            <p class="text-muted-foreground">
                {{ __('auth.check_spam') }}
            </p>
        </div>

        <!-- Разделитель -->
        <div class="relative my-8">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-border"></div>
            </div>
            <div class="relative flex justify-center text-xs uppercase">
                <span class="bg-background px-2 text-muted-foreground">{{ __('auth.wrong_email') }}</span>
            </div>
        </div>

        <!-- Блок смены почты -->
        <div class="max-w-[18rem] mx-auto" x-data="{ showForm: @entangle('showEmailForm') }">
            
            @if (!$showEmailForm)
                <x-ui.button variant="outline" wire:click="$set('showEmailForm', true)" class="w-full">
                    {{ __('auth.change_email') }}
                </x-ui.button>
            @else
                <form wire:submit="changeEmail" class="space-y-3">
                    <div>
                        <x-ui.label for="newEmail" class="text-xs text-muted-foreground">{{ __('auth.new_email_address') }}</x-ui.label>
                        <x-ui.input wire:model="newEmail" id="newEmail" type="email" class="mt-1 block w-full" placeholder="new@example.com" />
                        @error('newEmail') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>
                    
                    <div class="flex gap-2">
                        <x-ui.button type="submit" class="flex-1">{{ __('auth.save_and_send') }}</x-ui.button>
                        <x-ui.button type="button" variant="outline" wire:click="$set('showEmailForm', false)" class="flex-1">{{ __('common.cancel') }}</x-ui.button>
                    </div>
                </form>
            @endif

            <button wire:click="logout" class="block w-full text-center mt-6 text-sm text-muted-foreground hover:text-destructive transition-colors">
                {{ __('common.logout') }}
            </button>
        </div>
    </div>
</section>