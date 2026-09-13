<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest')] class extends Component
{
    public string $password = '';

    public function mount(): void
    {
        // Защита: если у юзера вообще нет пароля (регистрировался через соцсеть),
        // ему нечего подтверждать. Выкидываем на главную.
        if (empty(Auth::user()->password)) {
            $this->redirect(route('home'), navigate: true);
        }
    }

    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email'    => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.failed_password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('home', absolute: false), navigate: true);
    }
}; ?>

<div class="w-full max-w-md mx-auto p-4 bg-background text-foreground min-h-[calc(100dvh-4rem)] flex flex-col justify-center">

    <!-- Заголовок -->
    <div class="text-center mb-4">
        <h1 class="text-2xl font-semibold">{{ __('auth.confirm_password') }}</h1>
        <p class="text-sm text-muted-foreground mt-1">{{ __('auth.confirm_password_before_continuing') }}</p>
    </div>

    <form wire:submit="confirmPassword" class="space-y-5">

        <!-- Пароль -->
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

        <!-- Кнопка отправки с состоянием загрузки -->
        <x-ui.button
            type="submit"
            class="w-full py-3 bg-primary text-primary-foreground hover:bg-primary/90 transition-colors"
            wire:loading.attr="disabled"
            wire:target="confirmPassword"
        >
            <span wire:loading.remove wire:target="confirmPassword">{{ __('common.confirm') }}</span>
            <span wire:loading wire:target="confirmPassword" class="flex items-center justify-center gap-2">
                <x-lucide-loader-2 class="w-5 h-5 animate-spin inline"/>
                {{ __('common.processing') }} <!-- Добавь этот ключ в языковые файлы -->
            </span>
        </x-ui.button>
    </form>
</div>