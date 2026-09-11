@php
    $isAuth = auth()->check();
    $dbTheme = $isAuth ? (auth()->user()->preferences?->theme ?? 'light') : null;
    
    // Все роуты, где нужна простая шапка auth-navigation (независимо от статуса авторизации)
    $isAuthPage = request()->routeIs('register', 'login', 'password.request', 'password.reset', 'verification.notice', 'verification.verify', 'password.confirm');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" 
      class="scroll-smooth {{ $dbTheme === 'dark' ? 'dark' : '' }}"
      data-auth="{{ $isAuth ? '1' : '0' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'FlirtLove') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />

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

<body class="font-sans antialiased bg-background text-foreground min-h-screen">
    <div class="min-h-screen flex flex-col">
        
        <!-- УМНАЯ НАВИГАЦИЯ -->
        @if ($isAuthPage)
            <!-- 1. Страницы авторизации (вход, регистрация, пароль, подтверждение email) -->
            <livewire:layout.guest.auth-navigation />
        @elseif ($isAuth)
            <!-- 2. Авторизованный юзер на основном сайте (шапка с кошельком, VIP, профилем) -->
            <livewire:layout.inapp.navigation />
        @else
            <!-- 3. Гость на основном сайте (шапка с бургер-меню) -->
            <livewire:layout.guest.home-navigation />
        @endif

        <!-- Page Content -->
        <main class="flex-1">
            {{ $slot }}
        </main>

        <!-- Подключаем наш футер -->
        <livewire:layout.footer />
    </div>

    
     <!-- Наша модалка логина -->
    <livewire:web.modals.login-modal />

    <!-- ФИКС: Модалка восстановления пароля -->
    <livewire:web.modals.forgot-password-modal />

    <x-ui.sonner expand="true" />
</body>
</html>
