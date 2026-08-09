<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="w-full max-w-md mx-auto p-4 bg-background text-foreground h-[calc(100vh-4rem)] flex flex-col justify-center">

    <!-- Заголовок -->
    <div class="text-center mb-4">
        <h1 class="text-2xl font-semibold">{{ __('auth.confirm_password') }}</h1>
        <p class="text-sm text-muted-foreground mt-1">{{ __('auth.confirm_password_before_continuing') }}</p>
    </div>

    <form wire:submit="confirmPassword" class="space-y-5">

        <!-- Password -->
        <div class="space-y-2">
            <x-ui.label for="password" class="text-sm font-medium text-muted-foreground">
                {{ __('auth.password') }}
            </x-ui.label>
            <x-ui.input 
                wire:model="password" 
                id="password" 
                name="password" 
                type="password" 
                required 
                autocomplete="current-password"
                class="w-full bg-input border-border focus-visible:ring-ring autofill:bg-input autofill:text-foreground autofill:shadow-none"
                placeholder="{{ __('auth.ph_password') }}"
            />
            @error('password')
                <p class="text-sm text-destructive">{{ $message }}</p>
            @enderror
        </div>

        <!-- Submit Button -->
        <x-ui.button 
            type="submit" 
            class="w-full py-3 bg-primary text-primary-foreground hover:bg-primary/90 transition-colors"
        >
            {{ __('common.confirm') }}
        </x-ui.button>
    </form>
</div>