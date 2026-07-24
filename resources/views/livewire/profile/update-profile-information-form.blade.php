<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));
            return;
        }

        $user->sendEmailVerificationNotification();
        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section class="max-w-xl">
    
    <!-- Заголовок -->
    <header class="mb-8">
        <h2 class="text-2xl font-semibold text-foreground">{{ __('common.profile_information') }}</h2>
        <p class="mt-1 text-sm text-muted-foreground">{{ __('common.profile_information_desc') }}</p>
    </header>

    <form wire:submit="updateProfileInformation" class="space-y-6">

        <!-- Name -->
        <div class="space-y-2">
            <x-ui.label for="name" class="text-sm font-medium text-muted-foreground">
                {{ __('auth.name') }}
            </x-ui.label>
            <x-ui.input 
                wire:model="name" 
                id="name" 
                name="name" 
                type="text" 
                required 
                autofocus 
                autocomplete="name"
                class="w-full bg-input border-border focus-visible:ring-ring autofill:bg-input autofill:text-foreground autofill:shadow-none"
                placeholder="{{ __('auth.ph_name') }}"
            />
            @error('name')
                <p class="text-sm text-destructive">{{ $message }}</p>
            @enderror
        </div>

        <!-- Email -->
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
                autocomplete="username"
                class="w-full bg-input border-border focus-visible:ring-ring autofill:bg-input autofill:text-foreground autofill:shadow-none"
                placeholder="{{ __('auth.ph_email') }}"
            />
            @error('email')
                <p class="text-sm text-destructive">{{ $message }}</p>
            @enderror

            <!-- Подтверждение email -->
            @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                <div class="mt-3 p-4 rounded-lg bg-accent/20 border border-border/50">
                    <p class="text-sm text-foreground">
                        {{ __('common.email_unverified') }}
                        <button 
                            wire:click.prevent="sendVerification" 
                            class="text-primary hover:text-primary/80 font-medium transition-colors focus:outline-none focus:underline"
                        >
                            {{ __('common.resend_verification') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 text-sm font-medium text-green-600 dark:text-green-400">
                            {{ __('common.verification_link_sent') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <!-- Кнопки -->
        <div class="flex items-center gap-4 pt-2">
            <x-ui.button type="submit" class="bg-primary text-primary-foreground hover:bg-primary/90 transition-colors">
                {{ __('common.save') }}
            </x-ui.button>

            @if (session()->has('profile-updated'))
                <p class="text-sm text-green-600 dark:text-green-400">
                    {{ __('common.saved') }}
                </p>
            @endif
        </div>

    </form>
</section>