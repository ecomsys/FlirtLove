@props(['breadcrumbs' => []])

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

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Шрифт из твоей дизайн-системы -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Скрипт для мгновенного применения темы -->
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

<body class="font-sans antialiased bg-background text-foreground min-h-screen">
    <div class="min-h-screen flex flex-col">
        <!-- Единая навигация -->
        <livewire:layout.navigation />

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
