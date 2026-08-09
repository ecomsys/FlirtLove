<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public string $theme;

    public function mount(): void
    {
        if (Auth::check()) {
            // Для авторизованных БЕЗУСЛОВНО верим БД
            $this->theme = Auth::user()->preferences?->theme ?? 'light';
        } else {
            // Для гостей верим localStorage (который синкался в <head>)
            // В PHP мы не можем читать LS напрямую, поэтому берем дефолт, 
            // а реальным переключением для гостей пусть занимается только JS
            $this->theme = request()->cookie('theme', 'light'); 
        }
    }

    public function toggleTheme(): void
    {
        $this->theme = $this->theme === 'light' ? 'dark' : 'light';

        if (Auth::check()) {
            $user = Auth::user();
            // Сохраняем в БД
            if ($user->preferences) {
                $user->preferences->update(['theme' => $this->theme]);
            } else {
                $user->preferences()->create(['theme' => $this->theme]);
            }
        }
        
        // Кидаем событие в браузер, чтобы Alpine.update класс на <html> И обновил localStorage!
        $this->dispatch('theme-toggled', theme: $this->theme);
    }
}; ?>


<button wire:click="toggleTheme" type="button"
    class="flex items-center gap-1 p-2 rounded-lg text-muted-foreground hover:text-foreground hover:bg-accent transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
    aria-label="Toggle theme">
    
    <!-- Иконка Солнца (показывается в светлой теме) -->
    <svg class="w-5 h-5 block dark:hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
        stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round"
            d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
    </svg>

    <!-- Иконка Луны (показывается в темной теме) -->
    <svg class="w-5 h-5 hidden dark:block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
        stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round"
            d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
    </svg>
</button>
