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

<header class="sticky z-50 top-0 w-full border-b border-border/30 bg-background/70 backdrop-blur-md supports-[backdrop-filter]:bg-background/60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            
            <!-- ЛЕВАЯ ЧАСТЬ: Только Лого -->
            <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-2.5 group shrink-0">
                <x-application-logo class="w-8 h-8 fill-current text-foreground group-hover:text-primary transition-colors" />
                <span class="font-semibold text-lg text-foreground group-hover:text-primary transition-colors inline">
                    {{ config('app.name', 'App') }}
                </span>
            </a>

            <!-- ПРАВАЯ ЧАСТЬ: Переключатель темы + Умная кнопка -->
            <div class="flex items-center gap-2 sm:gap-4">
                
                <!-- Переключатель темы -->
                <livewire:theme-switcher />

                @php
                    $routeName = request()->route()?->getName();
                    $isAuth = auth()->check();
                    
                    // Точные списки роутов из твоего файла
                    $authRoutes = ['verification.notice', 'verification.verify', 'password.confirm'];
                    $guestAuthRoutes = ['login', 'password.request', 'password.reset'];
                    $registerRoute = ['register'];
                @endphp

                <!-- СИТУАЦИЯ 1: Юзер авторизован (verify-email или confirm-password) -> Показываем "Выйти" -->
                @if ($isAuth && in_array($routeName, $authRoutes))
                <x-ui.button variant="outline" size="sm" wire:click="logout"
                    class="text-muted-foreground hover:text-foreground hover:bg-accent/50">
                    <svg class="w-4 h-4 mr-1.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                    </svg>
                    Выйти
                </x-ui.button>

                <!-- СИТУАЦИЯ 2: Гость на странице Регистрации -> Показываем "Войти" (Открывает модалку) -->
                @elseif (!$isAuth && in_array($routeName, $registerRoute))
                    <x-ui.button @click="Livewire.dispatch('open-login-modal')" variant="outline" size="sm" type="button">
                        Войти
                    </x-ui.button>

                <!-- СИТУАЦИЯ 3: Гость на страницах Логина, Забыли пароль, Сброс пароля -> Показываем "Регистрация" -->
                @elseif (!$isAuth && in_array($routeName, $guestAuthRoutes))
                    <x-ui.button variant="default" size="sm" as-child>
                        <a href="{{ route('register') }}" wire:navigate>
                            Регистрация
                        </a>
                    </x-ui.button>
                @endif

            </div>
        </div>
    </div>
</header>