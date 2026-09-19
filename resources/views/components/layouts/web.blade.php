<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth {{ $theme === 'dark' ? 'dark' : '' }}"
    data-auth="{{ $isAuth ? '1' : '0' }}" data-app-theme="{{ $theme }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'App') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />

    @stack('styles')
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    <script>
        window.LIVEWIRE_ENABLED = false;
    </script>

    {{-- хелпер для определения темы --}}
    @include('partials.theme-bootstrap')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>


<body class="font-sans antialiased bg-background text-foreground min-h-screen flex flex-col">

    <div class="min-h-screen flex flex-col w-full">

        <!-- 1. ШАПКА (Динамическая) -->
        @guest
            <x-layout.guest.home-navigation />
        @else
            <x-layout.inapp.navigation />
        @endguest

        <!-- 2. ЛЕНТА БУСТОВ (Горизонтальная плашка под шапкой) -->
        <div class="w-full bg-card/50 border-b border-border backdrop-blur-sm">
            <div class="max-w-6xl mx-auto px-4 py-2 flex items-center gap-4 overflow-x-auto little-scroll">
                <span class="text-xs text-muted-foreground font-medium shrink-0">Рекомендуем:</span>
                <!-- Здесь пока заглушка, позже сделаем Livewire компонент для бустов -->
                <div class="flex gap-3 animate-pulse">
                    <div class="w-10 h-10 rounded-full bg-muted"></div>
                    <div class="w-10 h-10 rounded-full bg-muted"></div>
                    <div class="w-10 h-10 rounded-full bg-muted"></div>
                </div>
            </div>
        </div>

        <!-- 3. ОСНОВНОЙ КОНТЕНТ (Две колонки) -->
        <main class="flex-1 w-full">
            <div class="max-w-6xl mx-auto px-4 py-6 flex flex-col md:flex-row gap-6">

                <!-- ЛЕВАЯ КОЛОНКА (Сайдбар) -->
                @isset($sidebar)
                    <aside class="w-full md:w-64 shrink-0">
                        <!-- Сайдбар липкий, чтобы он ехал вместе со скроллом ленты -->
                        <div class="space-y-4">
                            {{ $sidebar }}
                        </div>
                    </aside>
                @endisset

                <!-- ПРАВАЯ КОЛОНКА (Динамический контент: Лента, Профиль, Дневники) -->
                <div class="flex-1 min-w-0">
                    {{ $slot }}
                </div>

            </div>
        </main>

        <!-- 4. ФУТЕР -->
        @guest
            <x-layout.guest.footer />
        @else
            <x-layout.inapp.footer />
        @endguest
    </div>

    <!-- Глобальный спиннер для wire:navigate -->
    <x-navigate-loader />

    <!-- Наша модалка логина -->
    <x-modals.auth.login-modal />

    <!-- ФИКС: Модалка восстановления пароля -->
    <x-modals.auth.forgot-password-modal />

    <x-ui.sonner expand="true" />
    

    @stack('scripts')

        @if(request()->has('payment_success'))
    <script>
        console.log('1. Скрипт оплаты загрузился');
        
        document.addEventListener('DOMContentLoaded', () => {
            console.log('2. DOM полностью загружен');
            
            let params = new URLSearchParams(window.location.search);
            if (params.has('payment_success')) {
                let credits = params.get('credits_added');
                let msg = 'Платеж успешно завершен! Вам начислено ' + credits + ' ед.';
                console.log('3. Отправляем событие show-toast с текстом:', msg);
                
                // Отправляем событие
                window.dispatchEvent(new CustomEvent('show-toast', { 
                    detail: { type: 'success', message: msg } 
                }));
                
                // Очищаем URL
                window.history.replaceState({}, document.title, window.location.pathname);
                console.log('4. URL очищен');
            }
        });
    </script>
    @endif
</body>

</html>
