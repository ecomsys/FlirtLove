@php
    $isAuthPage = request()->routeIs('register', 'login', 'password.request', 'password.reset', 'verification.notice', 'verification.verify', 'password.confirm');
@endphp

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

<body class="font-sans antialiased bg-background text-foreground min-h-screen">
    <div class="min-h-screen flex flex-col">
        
        @if ($isAuthPage)
            <livewire:layout.guest.auth-navigation />
        @elseif ($isAuth)
            <livewire:layout.inapp.navigation />
        @else
            <livewire:layout.guest.home-navigation />
        @endif

        <main class="flex-1">
            {{ $slot }}
        </main>

        <livewire:layout.footer />
    </div>

    <!-- Глобальный спиннер для wire:navigate -->
    <x-navigate-loader />

    <livewire:modals.login-modal />
    <livewire:modals.forgot-password-modal />
    <x-ui.sonner expand="true" />
    
    @stack('scripts')
</body>
</html>