@php
    $currentTheme = 'light';
    if (auth()->check()) {
        $currentTheme = auth()->user()->preferences?->theme ?? request()->cookie('theme', 'light');
    } else {
        $currentTheme = request()->cookie('theme', 'light');
    }
    if (!in_array($currentTheme, ['light', 'dark'])) {
        $currentTheme = 'light';
    }
@endphp

<div 
    x-data="{
        theme: '{{ $currentTheme }}',
        loading: false,
        isAuth: {{ auth()->check() ? 'true' : 'false' }},
        
        toggle() {
            // 1. Меняем тему в памяти Alpine
            this.theme = this.theme === 'light' ? 'dark' : 'light';
            
            // 2. Вызываем глобальную функцию (меняет класс у <html> и ставит куки)
            if (typeof window.toggleAppTheme === 'function') {
                window.toggleAppTheme();
            }

            // 3. Если юзер авторизован — отправляем AJAX
            if (this.isAuth) {
                this.loading = true;
                fetch('/settings/theme', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ theme: this.theme })
                }).finally(() => {
                    this.loading = false;
                });
            }
        }
    }"
>
    <button 
        @click="toggle()" 
        type="button"
        class="flex items-center gap-1 p-2 rounded-lg text-muted-foreground hover:text-foreground hover:bg-accent transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50"
        aria-label="Toggle theme"
        x-bind:disabled="loading"
    >
        <!-- Иконка Солнца (показывается только в светлой теме) -->
        <svg x-show="theme === 'light'" class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
        </svg>

        <!-- Иконка Луны (показывается только в тёмной теме) -->
        <svg x-show="theme === 'dark'" class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
        </svg>
    </button>
</div>