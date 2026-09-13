@props(['class' => ''])

<div x-data="{ loading: false }"
     x-on:livewire:navigate.window="loading = true"
     x-on:livewire:navigated.window="loading = false"
     x-show="loading"
     x-cloak
     class="fixed inset-0 z-[9999] bg-black/60 backdrop-blur-sm flex items-center justify-center transition-opacity duration-300 {{ $class }}">
    <div class="flex flex-col items-center gap-4">
        <svg class="animate-spin h-12 w-12 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <p class="text-primary text-sm font-medium">Загрузка...</p>
    </div>
</div>