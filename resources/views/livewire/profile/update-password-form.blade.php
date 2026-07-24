<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');
            throw $e;
        }

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->dispatch('password-updated');
    }
}; ?>

<section class="max-w-xl">
    
    <!-- Заголовок -->
    <header class="mb-8">
        <h2 class="text-2xl font-semibold text-foreground">{{ __('common.update_password') }}</h2>
        <p class="mt-1 text-sm text-muted-foreground">{{ __('common.update_password_desc') }}</p>
    </header>

    <form wire:submit="updatePassword" class="space-y-6">

        <!-- Current Password -->
        <div class="space-y-2">
            <x-ui.label for="update_password_current_password" class="text-sm font-medium text-muted-foreground">
                {{ __('common.current_password') }}
            </x-ui.label>
            <x-ui.input 
                wire:model="current_password" 
                id="update_password_current_password" 
                name="current_password" 
                type="password" 
                autocomplete="current-password"
                class="w-full bg-input border-border focus-visible:ring-ring"
                placeholder="{{ __('common.enter_current_password') }}"
            />
            @error('current_password')
                <p class="text-sm text-destructive">{{ $message }}</p>
            @enderror
        </div>

        <!-- New Password -->
        <div class="space-y-2">
            <x-ui.label for="update_password_password" class="text-sm font-medium text-muted-foreground">
                {{ __('common.new_password') ?? __('auth.new_password') }}
            </x-ui.label>
            <x-ui.input 
                wire:model="password" 
                id="update_password_password" 
                name="password" 
                type="password" 
                autocomplete="new-password"
                class="w-full bg-input border-border focus-visible:ring-ring"
                placeholder="{{ __('common.enter_new_password') }}"
            />
            @error('password')
                <p class="text-sm text-destructive">{{ $message }}</p>
            @enderror
        </div>

        <!-- Confirm Password -->
        <div class="space-y-2">
            <x-ui.label for="update_password_password_confirmation" class="text-sm font-medium text-muted-foreground">
                {{ __('auth.confirm_password') }}
            </x-ui.label>
            <x-ui.input 
                wire:model="password_confirmation" 
                id="update_password_password_confirmation" 
                name="password_confirmation" 
                type="password" 
                autocomplete="new-password"
                class="w-full bg-input border-border focus-visible:ring-ring"
                placeholder="{{ __('common.confirm_new_password') }}"
            />
            @error('password_confirmation')
                <p class="text-sm text-destructive">{{ $message }}</p>
            @enderror
        </div>

        <!-- Кнопки -->
        <div class="flex items-center gap-4 pt-2">
            <x-ui.button type="submit" class="bg-primary text-primary-foreground hover:bg-primary/90 transition-colors">
                {{ __('common.save') }}
            </x-ui.button>

            @if (session()->has('password-updated'))
                <p class="text-sm text-green-600 dark:text-green-400">
                    {{ __('common.saved') }}
                </p>
            @endif
        </div>

    </form>
</section>