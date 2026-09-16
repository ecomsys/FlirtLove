<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      class="scroll-smooth {{ $theme === 'dark' ? 'dark' : '' }}"
      data-auth="{{ $isAuth ? '1' : '0' }}"
    data-app-theme="{{ $theme }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'App') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />
    
    @stack('styles')
    <style>[x-cloak] { display: none !important; }</style>

    {{-- хелпер для определения темы --}}
    @include('partials.theme-bootstrap')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>


<body class="font-sans antialiased bg-background text-foreground min-h-screen flex flex-col">
    <div class="min-h-screen flex flex-col w-full">
        
        <!-- 1. ШАПКА (Динамическая) -->        
        @guest
            <livewire:layout.guest.home-navigation />
        @else
            <livewire:layout.inapp.navigation />
        @endguest

        <!-- 2. ЛЕНТА БУСТОВ (Горизонтальная плашка под шапкой) -->
        <div class="w-full bg-card/50 border-b border-border backdrop-blur-sm">
            <div class="max-w-7xl mx-auto px-4 py-2 flex items-center gap-4 overflow-x-auto little-scroll">
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
            <div class="max-w-7xl mx-auto px-4 py-6 flex flex-col md:flex-row gap-6">
                
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
        <livewire:layout.footer />
    </div>

    <!-- Глобальный спиннер для wire:navigate -->
    <x-navigate-loader />

    <!-- Наша модалка логина -->
    <livewire:modals.login-modal />

    <!-- ФИКС: Модалка восстановления пароля -->
    <livewire:modals.forgot-password-modal />    

    <x-ui.sonner expand="true" />            

  
</body>
</html>