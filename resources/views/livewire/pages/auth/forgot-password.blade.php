<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));
            return;
        }

        $this->reset('email');
        session()->flash('status', __($status));
    }
}; ?>

<div class="w-full max-w-md mx-auto py-10 px-4 bg-background text-foreground">

    <!-- Заголовок -->
    <div class="text-center mb-4">
        <h1 class="text-2xl font-semibold">{{ __('auth.reset_password') }}</h1>
        <p class="text-sm text-muted-foreground mt-1">{{ __('auth.enter_email_reset_link') }}</p>
    </div>

    <!-- Session Status -->
    @if (session('status'))
        <div class="mb-4 p-3 rounded-md bg-green-50 dark:bg-green-950/20 border border-green-200 dark:border-green-800/30 text-green-700 dark:text-green-400 text-sm">
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit="sendPasswordResetLink" class="space-y-5">

        <!-- Email Address -->
        <div class="space-y-2">
            <x-ui.label for="email" class="text-sm font-medium text-muted-foreground">
                {{ __('auth.email') }}
            </x-ui.label>
            <x-ui.input 
                wire:model="email" 
                id="email" 
                name="email" 
                type="email" 
                required 
                autofocus
                class="w-full bg-input border-border focus-visible:ring-ring autofill:bg-input autofill:text-foreground autofill:shadow-none"
                placeholder="{{ __('auth.ph_email') }}"
            />
            @error('email')
                <p class="text-sm text-destructive">{{ $message }}</p>
            @enderror
        </div>

        <!-- Submit Button -->
        <x-ui.button 
            type="submit" 
            class="w-full py-3 bg-primary text-primary-foreground hover:bg-primary/90 transition-colors"
        >
            {{ __('auth.send_reset_link') }}
        </x-ui.button>

        <!-- Back to Login Link -->
        <p class="text-center text-sm text-muted-foreground mt-4">
            <a href="{{ route('login') }}" wire:navigate class="text-primary hover:text-primary/80 transition-colors font-medium">
                {{ __('auth.back_to_login') }}
            </a>
        </p>
    </form>
</div>