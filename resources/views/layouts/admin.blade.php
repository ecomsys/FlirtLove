<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth"  x-data
      @theme-toggled.window="
          const newTheme = $event.detail.theme;
          if (newTheme === 'dark') {
              document.documentElement.classList.add('dark');
          } else {
              document.documentElement.classList.remove('dark');
          }
          localStorage.setItem('theme', newTheme);
      ">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Panel - {{ config('app.name', 'App') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <style>[x-cloak] { display: none !important; }</style>
    
    @stack('styles')
   <script>
        (function() {
            // 1. Определяем, что говорит БД (через PHP). Если гость - dbTheme пустой.
            const dbTheme = '{{ Auth::check() ? (Auth::user()->preferences?->theme ?? "light") : "" }}';
            
            // 2. Определяем, что говорит localStorage
            const localTheme = localStorage.getItem('theme') || 'light';
            
            // 3. Выбираем источник истины: БД приоритетнее для авторизованных!
            const theme = dbTheme || localTheme;

            // 4. Применяем класс
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
            
            // 5. Синхроним localStorage с выбранным состоянием (для будущих перезагрузок)
            localStorage.setItem('theme', theme);
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-background text-foreground">
    
    {{-- 1. Главный контейнер: занимает ровно 100% высоты экрана, скрывает внешний скролл --}}
    <div class="flex flex-col h-screen overflow-hidden">

        <!-- Шапка (убедись, что в самом компоненте навигации задана высота, например h-16) -->
        <livewire:layout.admin-navigation />

        {{-- 2. Контейнер для сайдбара и контента. flex-1 заставляет его занять всё оставшееся место --}}
        <div class="flex flex-row flex-1 overflow-hidden max-h-[calc(100dvh-4rem)]">
            
            <!-- Сайдбар -->
            <aside class="w-64 h-full overflow-y-auto little-scroll bg-card border-r border-border flex flex-col px-4 pt-4 pb-10 shrink-0">
                <livewire:layout.admin-sidebar />
            </aside>

            {{-- 3. Основной контент. 
                 min-w-0 — КРИТИЧЕСКИ ВАЖНО для flex, чтобы таблицы не рвали верстку. 
                 overflow-y-auto — скролл контента внутри. --}}
            <main class="flex-1 h-full overflow-y-auto overflow-x-hidden little-scroll p-4 md:p-8 min-w-0">                
                {{ $slot }}                
            </main>
        </div>
    </div>

    <x-ui.sonner expand="true" />
    <x-ui.confirm-modal />
    <livewire:admin.ban-user-modal />
    <livewire:admin.delete-user-modal />

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

