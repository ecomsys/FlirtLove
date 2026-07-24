<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{   
}; ?>

<footer class="w-full border-t border-border bg-background mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 pb-8">

        <!-- Верхняя часть: Лого + Статистика + Поддержка + Языки -->
        <div
            class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 md:gap-6 pb-6 border-b border-border/50">

            <!-- Левая часть: Лого + Статистика -->
            <div class="flex items-center gap-4 flex-1 min-w-0">
                <x-application-logo class="w-10 h-10 fill-current text-primary flex-shrink-0" />
                <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-4">
                    <p class="text-base font-semibold text-foreground whitespace-nowrap">{{ config('app.name', 'App') }}</p>
                    <p class="text-sm text-muted-foreground leading-relaxed">
                        <span class="whitespace-nowrap">© 2026</span>
                        <span class="hidden xs:inline mx-1">·</span>
                        <span class="whitespace-nowrap">{{ __('common.total_users') }}: 53,628,556</span>
                        <span class="hidden sm:inline mx-1">·</span>
                        <span class="whitespace-nowrap">{{ __('common.new') }}: 27,180</span>
                        <span class="hidden sm:inline mx-1">·</span>
                        <span class="whitespace-nowrap">{{ __('common.online') }}: 42,750</span>
                    </p>
                </div>
            </div>

            <!-- Правая часть: Поддержка + Языки -->
            <div class="flex items-center gap-4 md:gap-6 flex-shrink-0 w-full md:w-auto justify-start md:justify-end">
                <a href="#"
                    class="italic text-sm text-muted-foreground hover:text-foreground transition-colors whitespace-nowrap">
                    {{ __('common.support') }}
                </a>
                <div class="w-px h-5 bg-border/70"></div>
                <livewire:language-switcher />
            </div>
        </div>

        <!-- Нижняя часть: Навигация + 18+ -->
        <div class="flex flex-col md:flex-row justify-between items-center gap-4 md:gap-6 pt-6">

            <!-- Навигация -->
            <nav
                class="flex flex-wrap justify-center md:justify-start gap-x-4 gap-y-2 text-xs md:text-sm text-muted-foreground w-full md:w-auto">
                <a href="#"
                    class="hover:text-foreground transition-colors whitespace-nowrap">{{ __('common.documents') }}</a>
                <span class="text-border/50 hidden sm:inline">|</span>
                <a href="#"
                    class="hover:text-foreground transition-colors whitespace-nowrap">{{ __('common.about_it') }}</a>
                <span class="text-border/50 hidden sm:inline">|</span>
                <span class="flex flex-wrap items-center gap-1">
                    <span>{{ __('common.dating_site') }}</span>
                    <a href="https://flirtlove.ru" target="_blank" rel="noopener noreferrer"
                        class="text-primary hover:text-primary/80 transition-colors font-medium">
                        flirtlove.ru
                    </a>
                    <span>{{ __('common.partner') }}</span>
                </span>
                <span class="text-border/50 hidden md:inline">|</span>
                <a href="#"
                    class="hover:text-foreground transition-colors whitespace-nowrap w-full sm:w-auto text-center sm:text-left">
                    {{ __('common.serious') }}
                </a>
            </nav>

            <!-- Иконка 18+ -->
            <div class="flex items-center gap-2 flex-shrink-0">
                <span
                    class="inline-flex items-center justify-center w-10 h-10 border-2 border-muted-foreground/30 rounded-md text-sm font-bold text-muted-foreground hover:border-muted-foreground/60 hover:text-foreground transition-colors">
                    18+
                </span>
            </div>
        </div>
    </div>
</footer>