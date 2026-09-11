@php
    $isAuth = auth()->check();
    $dbTheme = $isAuth ? (auth()->user()->preferences?->theme ?? 'light') : null;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" 
      class="scroll-smooth {{ $dbTheme === 'dark' ? 'dark' : '' }}"
      data-auth="{{ $isAuth ? '1' : '0' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Panel - {{ config('app.name', 'App') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />
      
    @stack('styles')      
    <style>[x-cloak] { display: none !important; }</style>    
    
    <script>
        (function() {
            const dbTheme = '{{ $dbTheme }}';
            if (dbTheme) {
                // Синхронизируем БД с ключом 'theme:mode', чтобы blatui-core.js не сбрасывал тему при загрузке
                localStorage.setItem('theme:mode', dbTheme);
            } else {
                // Для гостей
                const localTheme = localStorage.getItem('theme:mode') || 'light';
                if (localTheme === 'dark') {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            }
        })();
    </script>
    
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
                    <aside class="w-full md:w-64 lg:w-72 shrink-0">
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

    <!-- Наша модалка логина -->
    <livewire:web.modals.login-modal />

    <!-- ФИКС: Модалка восстановления пароля -->
    <livewire:web.modals.forgot-password-modal />

    <x-ui.sonner expand="true" />
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css" />
</body>
</html>