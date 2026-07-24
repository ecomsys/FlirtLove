<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }   
}; ?>

<header class="sticky z-50 top-0 w-full border-b border-border/30 bg-background/70 backdrop-blur-md supports-[backdrop-filter]:bg-background/60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            
            <!-- Левая часть: Лого + Навигация -->
            <div class="flex items-center gap-6">
                <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-2.5 group shrink-0">
                    <x-application-logo class="w-8 h-8 fill-current text-foreground group-hover:text-primary transition-colors" />
                    <span class="font-semibold text-lg text-foreground group-hover:text-primary transition-colors inline">
                        {{ config('app.name', 'App') }}
                    </span>
                </a>

                @auth
                    <nav class="hidden md:flex items-center gap-1">
                        <a href="{{ route('dashboard') }}" wire:navigate 
                           class="px-3 py-2 rounded-md text-sm font-medium transition-colors {{ request()->routeIs('dashboard') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:text-foreground hover:bg-accent/50' }}">
                            {{ __('common.dashboard') }}
                        </a>
                        <a href="{{ route('profile') }}" wire:navigate 
                           class="px-3 py-2 rounded-md text-sm font-medium transition-colors {{ request()->routeIs('profile') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:text-foreground hover:bg-accent/50' }}">
                            {{ __('common.profile') }}
                        </a>
                    </nav>
                @endauth
            </div>

            <!-- Правая часть -->
            <div class="flex items-center gap-2 sm:gap-4">
                <livewire:theme-switcher />              

                @auth
                    <!-- Обертка с Alpine.js состоянием. Слушаем одно событие profile-updated -->
                    <div x-data="{ name: '{{ Auth::user()->name }}', email: '{{ Auth::user()->email }}' }" 
                        x-on:profile-updated.window="
                            if ($event.detail.name !== undefined) { name = $event.detail.name; }
                            if ($event.detail.email !== undefined) { email = $event.detail.email; }
                        " 
                        class="flex items-center gap-2 sm:gap-4">
                        
                        <!-- Аватар + Dropdown для авторизованных (Десктоп) -->
                        <x-ui.dropdown-menu class="hidden md:block">
                            <x-ui.dropdown-menu-trigger as-child>
                                <button class="border border-border flex items-center gap-2 rounded-full hover:bg-accent/50 transition-colors p-1 pl-2 pr-3">
                                    <x-avatar name="{{ Auth::user()->name }}" size="sm" />
                                    <span class="text-sm font-medium hidden sm:inline text-foreground" x-text="name">
                                        {{ Auth::user()->name ?? 'User' }}
                                    </span>
                                    <svg class="h-4 w-4 text-muted-foreground transition-transform duration-200 ease-out" 
                                         xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                         x-data="{ open: false }" x-on:click="open = !open" x-on:click.away="open = false" x-bind:class="{ 'rotate-180': open }">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </button>
                            </x-ui.dropdown-menu-trigger>
                            <x-ui.dropdown-menu-content align="end" class="w-56 p-3">
                                <x-ui.dropdown-menu-label class="font-normal">
                                    <div class="flex flex-col space-y-1">
                                        <p class="text-sm font-medium text-foreground" x-text="name">{{ Auth::user()->name }}</p>
                                        <p class="text-xs text-muted-foreground" x-text="email">{{ Auth::user()->email }}</p>
                                    </div>
                                </x-ui.dropdown-menu-label>
                                <x-ui.dropdown-menu-separator />
                                <x-ui.dropdown-menu-item as-child>
                                    <a href="{{ route('profile') }}" wire:navigate class="flex items-center gap-2">
                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                        </svg>
                                        {{ __('common.profile') }}
                                    </a>
                                </x-ui.dropdown-menu-item>
                                <x-ui.dropdown-menu-separator />
                                <x-ui.dropdown-menu-item wire:click="logout" as-child class="text-destructive focus:text-destructive">
                                    <div class="flex items-center gap-2 w-full text-left">
                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" />
                                        </svg>
                                        {{ __('common.logout') }}
                                    </div>
                                </x-ui.dropdown-menu-item>
                            </x-ui.dropdown-menu-content>
                        </x-ui.dropdown-menu>

                        <!-- Мобильный sheet (только для авторизованных) -->
                        <x-ui.sheet showClose="true" class="block md:hidden">
                            <x-ui.sheet-trigger as-child>
                                <button class="md:hidden inline-flex items-center justify-center p-2 rounded-md text-muted-foreground hover:text-foreground hover:bg-accent/50 transition-colors">
                                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                    </svg>
                                </button>
                            </x-ui.sheet-trigger>
                            <x-ui.sheet-content side="right" class="w-full sm:max-w-sm">
                                <div class="flex flex-col h-full">
                                    <x-ui.sheet-header class="flex-shrink-0">
                                        <div class="flex items-center justify-between">
                                            <x-ui.sheet-title class="flex items-center gap-2">
                                                <x-application-logo class="w-6 h-6 fill-current text-foreground" />
                                                {{ config('app.name', 'App') }}
                                            </x-ui.sheet-title>                                    
                                        </div>
                                        <x-ui.sheet-description>
                                            <div class="flex items-center gap-3 mt-3 p-3 rounded-lg bg-accent/30">
                                                <x-avatar name="{{ Auth::user()->name }}" size="sm" />
                                                <div>
                                                    <p class="text-sm font-medium text-foreground" x-text="name">{{ Auth::user()->name }}</p>
                                                    <p class="text-xs text-muted-foreground" x-text="email">{{ Auth::user()->email }}</p>
                                                </div>
                                            </div>
                                        </x-ui.sheet-description>
                                    </x-ui.sheet-header>

                                    <div class="flex-1 px-4 py-4 space-y-1 overflow-y-auto">
                                        <a href="{{ route('dashboard') }}" wire:navigate 
                                           class="flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium transition-colors {{ request()->routeIs('dashboard') ? 'bg-primary/10 text-primary' : 'text-foreground hover:bg-accent/50' }}">
                                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                                            </svg>
                                            {{ __('common.dashboard') }}
                                        </a>
                                        <a href="{{ route('profile') }}" wire:navigate 
                                           class="flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium transition-colors {{ request()->routeIs('profile') ? 'bg-primary/10 text-primary' : 'text-foreground hover:bg-accent/50' }}">
                                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                            </svg>
                                            {{ __('common.profile') }}
                                        </a>
                                        <div class="border-t border-border/50 my-3"></div>
                                        <button wire:click="logout" class="flex items-center gap-3 w-full text-left px-3 py-2.5 rounded-md text-sm font-medium text-destructive hover:bg-destructive/10 transition-colors">
                                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" />
                                            </svg>
                                            {{ __('common.logout') }}
                                        </button>
                                    </div>

                                    <x-ui.sheet-footer class="flex-shrink-0 border-t border-border/50 pt-4 px-4 pb-4">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-2">
                                                <livewire:theme-switcher />
                                                <livewire:language-switcher />
                                            </div>
                                            <p class="text-xs text-muted-foreground">v{{ config('app.version', '1.0') }}</p>
                                        </div>
                                    </x-ui.sheet-footer>
                                </div>
                            </x-ui.sheet-content>
                        </x-ui.sheet>
                    </div>
                @else
                    <!-- Кнопки для гостей (без бургера) -->
                    @if (request()->routeIs('register'))
                        <x-ui.button variant="default" size="sm" as-child>
                            <a href="{{ route('login') }}" wire:navigate>{{ __('common.login') }}</a>
                        </x-ui.button>
                    @elseif (request()->routeIs('login'))
                        <x-ui.button variant="default" size="sm" as-child>
                            <a href="{{ route('register') }}" wire:navigate>{{ __('common.register') }}</a>
                        </x-ui.button>
                    @else
                        <x-ui.button variant="ghost" size="sm" as-child class="hidden sm:inline-flex">
                            <a href="{{ route('login') }}" wire:navigate>{{ __('common.login') }}</a>
                        </x-ui.button>
                        <x-ui.button variant="default" size="sm" as-child>
                            <a href="{{ route('register') }}" wire:navigate>{{ __('common.register') }}</a>
                        </x-ui.button>
                    @endif
                @endauth
            </div>
        </div>
    </div>
</header>