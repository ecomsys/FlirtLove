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

    <script>window.LIVEWIRE_ENABLED = true;</script>

    {{-- хелпер для определения темы --}}
    @include('partials.theme-bootstrap')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>


<body class="font-sans antialiased bg-background text-foreground">
    
    <div class="flex flex-col h-screen overflow-hidden">
        <livewire:layout.admin.navigation />

        <div class="flex flex-row flex-1 overflow-hidden max-h-[calc(100dvh-4rem)]">
            <aside class="w-64 h-full overflow-y-auto little-scroll bg-card border-r border-border flex flex-col px-4 pt-4 pb-10 shrink-0">
                <livewire:layout.admin.sidebar />
            </aside>

            <main class="flex-1 h-full overflow-y-auto overflow-x-hidden little-scroll p-4 md:p-8 min-w-0">                
                {{ $slot }}                
            </main>
        </div>
    </div>

    <x-ui.sonner expand="true" />
    <x-ui.confirm-modal />
    <livewire:admin.ban-user-modal />
    <livewire:admin.delete-user-modal />
 
    <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>   
    <script>       
        Fancybox.bind('[data-fancybox]', {});
    </script>

    @stack('scripts')  
</body>
</html>