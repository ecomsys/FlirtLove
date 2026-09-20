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

        <!-- 1. ШАПКА  -->
        <x-layout.inapp.navigation />

        <!-- 3. ОСНОВНОЙ КОНТЕНТ  -->
        <main class="flex-1 w-full">
            {{ $slot }}
        </main>

        <!-- 4. ФУТЕР -->
        <x-layout.inapp.footer />

    </div>

    {{-- Модалка - "Купить Кредиты" --}}
    <x-modals.billing-modal />

    {{-- Модалка - "Купить Премиум" --}}
    <x-modals.premium-modal />

    {{-- Модалка - "Купить Vip" --}}
    <x-modals.vip-modal />

    <x-ui.sonner expand="true" />

    {{-- спинер при переходе между страницами триггер-функция window.showPageLoader(); --}}
    <x-web-ui.global-page-loader />
      
    @stack('scripts')
</body>

</html>
