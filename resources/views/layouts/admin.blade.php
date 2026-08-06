<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Panel - {{ config('app.name', 'App') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    @stack('styles')
    <script>
        (function() {
            const theme = localStorage.getItem('theme') || 'light';
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased bg-background text-foreground min-h-screen">
    <div class="flex flex-col min-h-screen">

        <!-- Шапка -->
        <livewire:layout.admin-navigation />

        <div class="flex flex-1 relative">
            <!-- Сайдбар -->
            <aside class="fixed little-scroll top-[4rem] left-0 z-40 w-64 h-[calc(100vh-4rem)] bg-card border-r border-border flex flex-col px-4 pt-4 pb-10 overflow-y-auto">
                <livewire:layout.admin-sidebar />
            </aside>

            <!-- Основной контент -->
            <div class="flex-1 flex flex-col ml-64">
                <main class="flex-1 p-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </div>

    <x-ui.sonner expand="true" />
    <x-ui.confirm-modal />

    <!-- Подключаем скрипт Trix -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>   
   
    <script>       
        Fancybox.bind('[data-fancybox]', {
            // Настройки
        });       
    </script>

    @stack('scripts')  
</body>

</html>