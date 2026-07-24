<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    #[Locked]
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Mount the component.
     */
    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = request()->input('email', '');
    }

    /**
     * Reset the password for the given user.
     */
    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status != Password::PASSWORD_RESET) {
            $this->addError('email', __($status));
            return;
        }

        Session::flash('status', __($status));
        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div class="w-full max-w-md mx-auto py-10 px-4 bg-background text-foreground">

    <!-- Заголовок -->
    <div class="text-center mb-4">
        <h1 class="text-2xl font-semibold">{{ __('auth.create_new_password') }}</h1>
        <p class="text-sm text-muted-foreground mt-1">{{ __('auth.set_new_password') }}</p>
    </div>

    <form wire:submit="resetPassword" class="space-y-5">

        <!-- Email Address (скрытый или readonly) -->
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
                readonly
                autofocus
                autocomplete="username"
                class="w-full bg-muted/50 border-border cursor-not-allowed opacity-75 focus-visible:ring-ring"
            />
            @error('email')
                <p class="text-sm text-destructive">{{ $message }}</p>
            @enderror
        </div>

        <!-- Password -->
        <div class="space-y-2">
            <x-ui.label for="password" class="text-sm font-medium text-muted-foreground">
                {{ __('auth.new_password') }}
            </x-ui.label>
            <x-ui.input 
                wire:model="password" 
                id="password" 
                name="password" 
                type="password" 
                required 
                autocomplete="new-password"
                class="w-full bg-input border-border focus-visible:ring-ring autofill:bg-input autofill:text-foreground autofill:shadow-none"
                placeholder="{{ __('auth.enter_new_password') }}"
            />
            @error('password')
                <p class="text-sm text-destructive">{{ $message }}</p>
            @enderror
        </div>

        <!-- Confirm Password -->
        <div class="space-y-2">
            <x-ui.label for="password_confirmation" class="text-sm font-medium text-muted-foreground">
                {{ __('auth.confirm_password') }}
            </x-ui.label>
            <x-ui.input 
                wire:model="password_confirmation" 
                id="password_confirmation" 
                name="password_confirmation" 
                type="password" 
                required 
                autocomplete="new-password"
                class="w-full bg-input border-border focus-visible:ring-ring autofill:bg-input autofill:text-foreground autofill:shadow-none"
                placeholder="{{ __('auth.confirm_your_password') }}"
            />
            @error('password_confirmation')
                <p class="text-sm text-destructive">{{ $message }}</p>
            @enderror
        </div>

        <!-- Submit Button -->
        <x-ui.button 
            type="submit" 
            class="w-full py-3 bg-primary text-primary-foreground hover:bg-primary/90 transition-colors"
        >
            {{ __('auth.reset_password') }}
        </x-ui.button>
    </form>
</div>