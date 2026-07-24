<x-guest-layout>
    <!-- HERO СЕКЦИЯ -->
    <section class="relative overflow-hidden">
        <!-- Фоновые свечения (декор) -->
        <div
            class="absolute top-0 left-1/2 -translate-x-1/2 w-[600px] h-[600px] bg-primary/20 rounded-full blur-[120px] -z-10 pointer-events-none">
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 md:py-32 text-center">

            <!-- Бейдж -->
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-border bg-background/50 backdrop-blur-sm mb-6"
                x-data="{ show: false }" x-init="setTimeout(() => show = true, 100)"
                :class="show ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'"
                style="transition: all 0.5s ease;">
                <span class="flex h-2 w-2 rounded-full bg-primary animate-pulse"></span>
                <span class="text-sm text-muted-foreground">{{ __('landing.badge') }}</span>
            </div>

            <!-- Заголовок -->
            <h1 class="text-4xl md:text-6xl font-bold tracking-tight text-foreground mb-6" x-data="{ show: false }"
                x-init="setTimeout(() => show = true, 300)" :class="show ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-8'"
                style="transition: all 0.7s ease;">
                {{ __('landing.hero_title') }}<br class="hidden md:block">
                <span class="bg-gradient-to-r from-primary to-purple-600 bg-clip-text text-transparent">
                    {{ __('landing.hero_highlight') }}
                </span>
            </h1>

            <!-- Подзаголовок -->
            <p class="max-w-2xl mx-auto text-lg text-muted-foreground mb-10" x-data="{ show: false }"
                x-init="setTimeout(() => show = true, 500)" :class="show ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-8'"
                style="transition: all 0.7s ease;">
                {{ __('landing.hero_subtitle') }}
            </p>

             <!-- Кнопки CTA -->
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4" x-data="{ show: false }"
                x-init="setTimeout(() => show = true, 700)" :class="show ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-8'"
                style="transition: all 0.7s ease;">
                
                @guest
                    <!-- Показываем гостям: Регистрация и Вход -->
                    <x-ui.button variant="default" size="lg" class="w-full sm:w-auto shadow-lg shadow-primary/20" as-child>
                        <a href="{{ route('register') }}" wire:navigate>
                            {{ __('landing.cta_register') }}
                        </a>
                    </x-ui.button>
                    <x-ui.button variant="outline" size="lg" class="w-full sm:w-auto" as-child>
                        <a href="{{ route('login') }}" wire:navigate>
                            {{ __('landing.cta_login') }}
                        </a>
                    </x-ui.button>
                @endguest

                @auth
                    <!-- Показываем авторизованным: Поиск (Скоро сделаем роут feed) -->
                    <x-ui.button variant="default" size="lg" class="w-full sm:w-auto shadow-lg shadow-primary/20" as-child>
                        <a href="{{ route('dashboard') }}" wire:navigate>
                             {{ __('landing.cta_find_pair') }}
                        </a>
                    </x-ui.button>
                @endauth
            </div>
        </div>
    </section>

    <!-- СЕКЦИЯ ПРЕИМУЩЕСТВ -->
    <section class="border-t border-border bg-card/30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <div class="grid md:grid-cols-3 gap-8">

                <!-- Карточка 1: Умный поиск -->
                <div class="p-8 rounded-xl border border-border bg-background hover:shadow-md transition-shadow">
                    <div class="w-12 h-12 rounded-lg bg-primary/10 flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-foreground mb-2">{{ __('landing.feature_1_title') }}</h3>
                    <p class="text-muted-foreground">{{ __('landing.feature_1_text') }}</p>
                </div>

                <!-- Карточка 2: Безопасность -->
                <div class="p-8 rounded-xl border border-border bg-background hover:shadow-md transition-shadow">
                    <div class="w-12 h-12 rounded-lg bg-primary/10 flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-foreground mb-2">{{ __('landing.feature_2_title') }}</h3>
                    <p class="text-muted-foreground">{{ __('landing.feature_2_text') }}</p>
                </div>

                <!-- Карточка 3: Общение -->
                <div class="p-8 rounded-xl border border-border bg-background hover:shadow-md transition-shadow">
                    <div class="w-12 h-12 rounded-lg bg-primary/10 flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-foreground mb-2">{{ __('landing.feature_3_title') }}</h3>
                    <p class="text-muted-foreground">{{ __('landing.feature_3_text') }}</p>
                </div>

            </div>
        </div>
    </section>
</x-guest-layout>
