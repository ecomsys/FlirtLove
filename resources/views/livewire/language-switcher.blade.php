<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public string $currentLocale;

    public function mount(): void
    {
        if (Auth::check()) {
            $this->currentLocale = Auth::user()->locale ?? config('app.locale');
        } else {
            $this->currentLocale = session()->get('locale', config('app.locale'));
        }
    }

    public function switchLanguage(string $locale): void
    {
        if (!in_array($locale, ['en', 'ru'])) {
            return;
        }

        $this->currentLocale = $locale;
        session()->put('locale', $locale);
        App::setLocale($locale);

        if (Auth::check()) {
            Auth::user()->update(['locale' => $locale]);
        }

        $this->redirect(request()->headers->get('Referer'), navigate: false);
    }
}; ?>


<x-ui.dropdown-menu>
    <x-ui.dropdown-menu-trigger as-child>
        <x-ui.button variant="ghost" size="sm" class="flex items-center gap-2 px-3 h-9">
            <!-- Текущий флаг -->
            @if ($currentLocale === 'en')
                <x-flags.en />
            @else
                <x-flags.ru />
            @endif

            <!-- Название языка -->
            <span class="text-sm font-medium">
                {{ $currentLocale === 'en' ? 'English' : 'Русский' }}
            </span>

            <!-- Стрелка вниз -->
            <svg class="h-3 w-3 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </x-ui.button>
    </x-ui.dropdown-menu-trigger>

    <x-ui.dropdown-menu-content align="end" class="w-40">
        <x-ui.dropdown-menu-item wire:click="switchLanguage('en')"
            class="flex items-center gap-2 cursor-pointer {{ $currentLocale == 'en' ? 'bg-accent/50 font-semibold' : '' }}">
            <x-flags.en />
            English
            @if ($currentLocale == 'en')
                <span class="ml-auto">✓</span>
            @endif
        </x-ui.dropdown-menu-item>

        <x-ui.dropdown-menu-item wire:click="switchLanguage('ru')"
            class="flex items-center gap-2 cursor-pointer {{ $currentLocale == 'ru' ? 'bg-accent/50 font-semibold' : '' }}">
            <x-flags.ru />
            Русский
            @if ($currentLocale == 'ru')
                <span class="ml-auto">✓</span>
            @endif
        </x-ui.dropdown-menu-item>
    </x-ui.dropdown-menu-content>
</x-ui.dropdown-menu>
