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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css" />

    @stack('styles')
    <style>[x-cloak] { display: none !important; }</style>

    <script>window.LIVEWIRE_ENABLED = false;</script>

    {{-- хелпер для определения темы --}}
     @include('partials.theme-bootstrap')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>


<body class="font-sans antialiased bg-background text-foreground">
    <div class="min-h-screen flex flex-col">
        <!-- Простая шапка -->
        <x-layout.onboarding.navigation />

        <main class="flex-1">
            {{ $slot }}
        </main>
       
    </div>

    <!-- Глобальный спиннер для wire:navigate -->
    <x-navigate-loader />

    <x-ui.sonner expand="true" />
    @stack('scripts')
</body>

</html>
