@props(['breadcrumbs' => []])

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

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Шрифт из твоей дизайн-системы -->
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
        <!-- Единая навигация -->
        <livewire:layout.inapp.navigation />

        <!-- Page Heading -->
        @if (isset($header))
            <header class="bg-card border-b border-border">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endif

        <!-- Page Content -->
        <main class="flex-1">
             @if (!empty($breadcrumbs))
                <x-breadcrumbs :breadcrumbs="$breadcrumbs" />
            @endif           

            {{ $slot }}
        </main>

        <!-- Подключаем наш футер -->
        <livewire:layout.footer />
    </div>

    <x-ui.sonner expand="true" />
</body>

</html>
