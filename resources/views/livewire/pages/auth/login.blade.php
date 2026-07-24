<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component 
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $user = Auth::user();
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ]);

         // Если вошел админ — кидаем его сразу в админку
        if (Auth::user()->is_admin) {
            $this->redirect(route('admin.dashboard'), navigate: true);
            return;
        }

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="w-full max-w-md mx-auto py-10 px-4 bg-background text-foreground">

    <!-- Заголовок -->
    <div class="text-center mb-4">
        <h1 class="text-2xl font-semibold">{{ __('auth.welcome_back') }}</h1>
        <p class="text-sm text-muted-foreground mt-1">{{ __('auth.sign_in_account') }}</p>
    </div>

    <!-- Session Status -->
    @if (session('status'))
        <div
            class="mb-4 p-3 rounded-md bg-green-50 dark:bg-green-950/20 border border-green-200 dark:border-green-800/30 text-green-700 dark:text-green-400 text-sm">
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit="login" class="space-y-5">

        <!-- Email Address -->
        <div class="space-y-2">
            <x-ui.label for="email" class="text-sm font-medium text-muted-foreground">
                {{ __('auth.email') }}
            </x-ui.label>
            <x-ui.input wire:model="form.email" id="email" name="email" type="email" required autofocus
                autocomplete="username"
                class="w-full bg-input border-border focus-visible:ring-ring autofill:bg-input autofill:text-foreground autofill:shadow-none"
                placeholder="{{ __('auth.ph_email') }}" />
            @error('form.email')
                <p class="text-sm text-destructive">{{ $message }}</p>
            @enderror
        </div>

        <!-- Password -->
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <x-ui.label for="password" class="text-sm font-medium text-muted-foreground">
                    {{ __('auth.password') }}
                </x-ui.label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" wire:navigate
                        class="text-xs text-muted-foreground hover:text-foreground transition-colors">
                        {{ __('auth.forgot_password') }}
                    </a>
                @endif
            </div>
            <x-ui.input wire:model="form.password" id="password" name="password" type="password" required
                autocomplete="current-password"
                class="w-full bg-input border-border focus-visible:ring-ring autofill:bg-input autofill:text-foreground autofill:shadow-none"
                placeholder="{{ __('auth.ph_password') }}" />
            @error('form.password')
                <p class="text-sm text-destructive">{{ $message }}</p>
            @enderror
        </div>

        <!-- Remember Me -->
        <div class="flex items-center gap-2">
            <x-ui.checkbox wire:model="form.remember" id="remember" />
            <x-ui.label for="remember" class="text-sm text-muted-foreground cursor-pointer">
                {{ __('auth.remember_me') }}
            </x-ui.label>
        </div>

        <!-- Submit Button -->
        <x-ui.button type="submit"
            class="w-full py-3 bg-primary text-primary-foreground hover:bg-primary/90 transition-colors">
            {{ __('auth.sign_in') }}
        </x-ui.button>

        <!-- Register Link -->
        <p class="text-center text-sm text-muted-foreground mt-4">
            {{ __('auth.have_account') }}
            <a href="{{ route('register') }}" wire:navigate
                class="text-primary hover:text-primary/80 transition-colors font-medium">
                {{ __('common.register') }}
            </a>
        </p>
    </form>
</div>