<header class="sticky z-50 top-0 w-full border-b border-border/30 bg-background/70 backdrop-blur-md supports-[backdrop-filter]:bg-background/60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            
            <!-- ЛЕВАЯ ЧАСТЬ: Лого + Навигация (Десктоп) -->
            <div class="flex items-center gap-10">
                
                <!-- Лого -->
                <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-2.5 group shrink-0">
                    <x-application-logo class="w-8 h-8 fill-current text-foreground group-hover:text-primary transition-colors" />
                    <span class="font-semibold text-lg text-foreground group-hover:text-primary transition-colors inline">
                        {{ config('app.name', 'App') }}
                    </span>
                </a>

                <!-- НАВИГАЦИЯ (Показываем только на lg экранах и выше) -->
                <nav class="hidden lg:flex items-center gap-2">
                    <a href="#" wire:navigate class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent/50 transition-colors">
                        <x-lucide-heart class="w-5 h-5" />
                        Знакомства
                    </a>
                    <a href="#" wire:navigate class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent/50 transition-colors">
                        <x-lucide-layout-grid class="w-5 h-5" />
                        Доска объявлений
                    </a>
                    <a href="#" wire:navigate class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent/50 transition-colors">
                        <x-lucide-book-open class="w-5 h-5" />
                        Блог
                    </a>
                </nav>

            </div>

            <!-- ПРАВАЯ ЧАСТЬ: Только для гостей -->
            <div class="flex items-center gap-2 sm:gap-4">
                
                <!-- Переключатель темы (всегда виден) -->
                <livewire:theme-switcher />

                <!-- Кнопка Войти (Скрываем на самом маленьком экране, чтобы место под бургер оставить) -->
                <x-ui.button @click="Livewire.dispatch('open-login-modal')" variant="outline" size="sm" type="button" class="hidden sm:inline-flex">
                    {{ __('common.login') }}
                </x-ui.button>
                
                <!-- Кнопка Регистрация (Скрываем на самом маленьком экране) -->
                <x-ui.button variant="default" size="sm" as-child class="hidden sm:inline-flex">
                    <a href="{{ route('register') }}" wire:navigate>{{ __('common.register') }}</a>
                </x-ui.button>

                <!-- БУРГЕР ДЛЯ МОБИЛОК И ПЛАНШЕТОВ (Скрывается на lg и выше) -->
                <div class="lg:hidden">
                    <x-ui.sheet showClose="true">
                        <!-- Иконка бургера (Lucide Menu) -->
                        <x-ui.sheet-trigger as-child>
                            <button class="inline-flex items-center justify-center p-2 rounded-md text-muted-foreground hover:text-foreground hover:bg-accent/50 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                <x-lucide-menu class="w-6 h-6" />
                            </button>
                        </x-ui.sheet-trigger>
                        
                     <!-- Выезжающая панель (Drawer) -->
                        <x-ui.sheet-content side="right" class="w-full sm:max-w-sm">
                            <div class="flex flex-col h-full">
                                
                                <!-- Шапка драйвера -->
                                <x-ui.sheet-header class="flex-shrink-0">
                                    <x-ui.sheet-title class="flex items-center gap-2">
                                        <x-application-logo class="w-6 h-6 fill-current text-foreground" />
                                        {{ config('app.name', 'App') }}
                                    </x-ui.sheet-title>
                                </x-ui.sheet-header>

                                <!-- Ссылки меню -->
                                <div class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
                                    <!-- Знакомства -->
                                    <a href="#" wire:navigate 
                                    @click="window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))" 
                                    class="flex items-center gap-3 px-3 py-3 rounded-md text-base font-medium text-foreground hover:bg-accent/50 transition-colors">
                                        <x-lucide-heart class="w-5 h-5 text-muted-foreground" />
                                        Знакомства
                                    </a>

                                    <!-- Доска объявлений -->
                                    <a href="#" wire:navigate 
                                    @click="window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))" 
                                    class="flex items-center gap-3 px-3 py-3 rounded-md text-base font-medium text-foreground hover:bg-accent/50 transition-colors">
                                        <x-lucide-layout-grid class="w-5 h-5 text-muted-foreground" />
                                        Доска объявлений
                                    </a>

                                    <!-- Блог -->
                                    <a href="#" wire:navigate 
                                    @click="window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))" 
                                    class="flex items-center gap-3 px-3 py-3 rounded-md text-base font-medium text-foreground hover:bg-accent/50 transition-colors">
                                        <x-lucide-book-open class="w-5 h-5 text-muted-foreground" />
                                        Блог
                                    </a>
                                </div>

                                <!-- Низ драйвера: Кнопки авторизации для мобилок -->
                                <x-ui.sheet-footer class="flex-shrink-0 border-t border-border/50 pt-4 px-4 pb-8 space-y-3">
                                    
                                    <!-- Кнопка Войти (Сначала закрываем драйвер, потом открываем модалку) -->
                                    <x-ui.button 
                                        @click="window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true })); Livewire.dispatch('open-login-modal')" 
                                        variant="outline" 
                                        class="w-full">
                                        Войти
                                    </x-ui.button>

                                    <!-- Кнопка Регистрация -->
                                    <x-ui.button variant="default" as-child class="w-full">
                                        <a href="{{ route('register') }}" wire:navigate 
                                        @click="window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))">
                                            Регистрация
                                        </a>
                                    </x-ui.button>
                                </x-ui.sheet-footer>

                            </div>
                        </x-ui.sheet-content>
                    </x-ui.sheet>
                </div>

            </div>
        </div>
    </div>
</header>