<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component {
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }
}; ?>

<header
    class="sticky z-50 top-0 w-full border-b border-border/30 bg-background/70 backdrop-blur-md supports-[backdrop-filter]:bg-background/60">
    <div class="px-4 sm:px-6 ">
        <div class="flex items-center justify-between h-16">

            <!-- Левая часть: Лого + Навигация -->
            <div class="flex items-center gap-6">
                <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-2.5 group shrink-0">
                    <x-application-logo
                        class="w-8 h-8 fill-current text-foreground group-hover:text-primary transition-colors" />
                    <span
                        class="font-semibold text-lg text-foreground group-hover:text-primary transition-colors inline">
                        {{ __('common.admin') }}
                    </span>
                </a>
            </div>

            <!-- Правая часть -->
            <div class="flex items-center gap-2 sm:gap-4">
                <livewire:theme-switcher />

                @auth
                    <!-- Кнопка выхода через Livewire -->
                    <x-ui.button 
                        variant="outline" 
                        size="sm" 
                        wire:click="logout"
                        class="text-muted-foreground hover:text-foreground hover:bg-accent/50"
                    >
                        <svg class="w-4 h-4 mr-1.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                        </svg>
                        {{ __('common.logout') }}
                    </x-ui.button>
                @else
                    <x-ui.button variant="default" size="sm" as-child>
                        <a href="{{ route('login') }}" wire:navigate>{{ __('common.login') }}</a>
                    </x-ui.button>
                @endauth
            </div>
        </div>
    </div>
</header>