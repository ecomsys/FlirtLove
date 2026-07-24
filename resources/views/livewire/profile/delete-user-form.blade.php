<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section class="max-w-3xl">
    <header class="mb-8">        
        <!-- Заголовок -->    
        <h2 class="text-2xl font-semibold text-foreground">{{ __('common.delete_account') }}</h2>            
        <p class="mt-1 text-sm text-muted-foreground">{{ __('common.delete_account_warning') }}</p>        
    </header>

    <x-ui.button
        variant="destructive"
        x-data
        x-on:click.prevent="$dispatch('open-dialog-confirm-user-deletion')"
    >
        {{ __('common.delete_account') }}
    </x-ui.button>

    <x-ui.dialog id="confirm-user-deletion">
        <x-ui.dialog-content>
            <x-ui.dialog-header>
                <x-ui.dialog-title>
                    {{ __('common.confirm_delete_account') }}
                </x-ui.dialog-title>
                <x-ui.dialog-description>
                    {{ __('common.confirm_delete_account_desc') }}
                </x-ui.dialog-description>
            </x-ui.dialog-header>

            <form wire:submit="deleteUser" class="space-y-4">
                <div class="mt-4">
                    <x-ui.label for="password" class="sr-only">
                        {{ __('auth.password') }}
                    </x-ui.label>

                    <x-ui.input
                        wire:model="password"
                        id="password"
                        name="password"
                        type="password"
                        class="mt-1 block bg-input border-border focus-visible:ring-ring"
                        placeholder="{{ __('auth.password') }}"
                    />

                    @error('password')
                        <p class="mt-2 text-sm text-destructive">{{ $message }}</p>
                    @enderror
                </div>

                <x-ui.dialog-footer class="gap-2">
                    <x-ui.button 
                        variant="outline" 
                        x-on:click="$dispatch('close-dialog-confirm-user-deletion')"
                    >
                        {{ __('common.cancel') }}
                    </x-ui.button>

                    <x-ui.button 
                        type="submit" 
                        variant="destructive"
                        x-on:click="$dispatch('close-dialog-confirm-user-deletion')"
                    >
                        {{ __('common.delete_account') }}
                    </x-ui.button>
                </x-ui.dialog-footer>
            </form>
        </x-ui.dialog-content>
    </x-ui.dialog>
</section>