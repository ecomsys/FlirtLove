@php
    // Load the user and their balance to avoid N+1
    $user = auth()->user();
    if ($user) {
        $user->loadMissing('balance');
    }
@endphp

<header class="sticky z-50 top-0 w-full border-b border-border/30 bg-background/70 backdrop-blur-md supports-[backdrop-filter]:bg-background/60">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">

            <!-- ЛЕВАЯ ЧАСТЬ: Лого + Навигация -->
            <div class="flex items-center gap-10">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 group shrink-0">
                    <x-svg.application-logo class="w-8 h-8 fill-current text-foreground group-hover:text-primary transition-colors" />
                    <span class="font-semibold text-lg text-foreground group-hover:text-primary transition-colors inline">
                        {{ config('app.name', 'App') }}
                    </span>
                </a>

                <nav class="hidden lg:flex items-center gap-2">
                    <a href="#" class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent/50 transition-colors">
                        <x-lucide-heart class="w-5 h-5" /> Знакомства
                    </a>
                    <a href="/search" class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent/50 transition-colors">
                        <x-lucide-search class="w-5 h-5" /> Поиск
                    </a>
                    <a href="#" class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent/50 transition-colors">
                        <x-lucide-message-circle class="w-5 h-5" /> Сообщения
                    </a>
                    <a href="#" class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-accent/50 transition-colors">
                        <x-lucide-layers class="w-5 h-5" /> Подписки
                    </a>
                </nav>
            </div>

            <!-- ПРАВАЯ ЧАСТЬ -->
            <div class="flex items-center gap-2 sm:gap-3">
                
                <!-- Переключатель темы (всегда виден) -->
                <x-theme-switcher />

                <!-- ДЕСКТОПНЫЕ HOVER-КАРТОЧКИ (скрыты на мобилках) -->
                <div class="hidden lg:flex items-center gap-2 sm:gap-3" x-data="{ activeMenu: null, timer: null }">
                    
                    <!-- 1. КОШЕЛЕК -->
                    <div class="relative" 
                         @mouseenter="clearTimeout(timer); activeMenu = 'wallet'" 
                         @mouseleave="timer = setTimeout(() => activeMenu = null, 200)">
                        
                        <button class="w-9 h-9 rounded-full bg-muted/50 text-foreground hover:bg-muted flex items-center justify-center transition-colors border border-border/50">
                            <x-lucide-wallet class="w-5 h-5" />
                        </button>

                        <div x-show="activeMenu === 'wallet'" x-cloak x-transition.opacity.duration.200ms
                             @mouseenter="clearTimeout(timer)"
                             class="absolute left-1/2 -translate-x-1/2 top-full mt-3 w-72 z-50">
                            
                            <div class="relative bg-card border border-border rounded-lg shadow-xl">
                                <div class="absolute left-1/2 -translate-x-1/2 -top-2 w-4 h-4 rotate-45 bg-card border-l border-t border-border"></div>

                                <div class="relative p-4 flex items-start gap-3">
                                    <div class="mt-1 w-10 h-10 rounded-full bg-muted flex items-center justify-center shrink-0">
                                        <x-lucide-wallet class="w-5 h-5 text-foreground" />
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-medium text-foreground">В вашем кошельке:</h4>
                                        <p class="text-xl font-bold text-foreground mt-1">{{ $user?->balance?->credits ?? 0 }} ед.</p>
                                    </div>
                                </div>
                                <div class="relative p-4 pt-0">
                                    <button @click="window.dispatchEvent(new CustomEvent('open-billing-modal'))" class="w-full py-2.5 rounded-md bg-green-600 hover:bg-green-700 text-white text-sm font-medium transition-colors flex items-center justify-center gap-2">
                                        <x-lucide-plus-circle class="w-4 h-4" /> Пополнить кошелек
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. VIP СТАТУС -->
                    <div class="relative" 
                         @mouseenter="clearTimeout(timer); activeMenu = 'vip'" 
                         @mouseleave="timer = setTimeout(() => activeMenu = null, 200)">
                        
                        <button class="w-9 h-9 rounded-full bg-blue-500/10 text-blue-500 hover:bg-blue-500/20 flex items-center justify-center transition-colors border border-blue-500/20">
                            <x-lucide-gem class="w-5 h-5" />
                        </button>

                        <div x-show="activeMenu === 'vip'" x-cloak x-transition.opacity.duration.200ms
                             @mouseenter="clearTimeout(timer)"
                             class="absolute left-1/2 -translate-x-1/2 top-full mt-3 w-72 z-50">
                            
                            <div class="relative bg-card border border-border rounded-lg shadow-xl">
                                <div class="absolute left-1/2 -translate-x-1/2 -top-2 w-4 h-4 rotate-45 bg-card border-l border-t border-border"></div>

                                <div class="relative p-4 flex items-start gap-3">
                                    <div class="mt-1 w-10 h-10 rounded-full bg-blue-500/10 flex items-center justify-center shrink-0">
                                        <x-lucide-gem class="w-5 h-5 text-blue-500" />
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-foreground text-sm">Включить VIP статус</h4>
                                        <p class="text-xs text-muted-foreground mt-1">Встань первым</p>
                                    </div>
                                </div>
                                <div class="relative p-4 pt-0">
                                    <button class="w-full py-2.5 rounded-md bg-green-600 hover:bg-green-700 text-white text-sm font-medium transition-colors flex items-center justify-center gap-2">
                                        <x-lucide-gem class="w-4 h-4" /> Включить VIP статус
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. ПРЕМИУМ ДОСТУП -->
                    <div class="relative" 
                         @mouseenter="clearTimeout(timer); activeMenu = 'premium'" 
                         @mouseleave="timer = setTimeout(() => activeMenu = null, 200)">
                        
                        <button class="w-9 h-9 rounded-full bg-orange-500/10 text-orange-500 hover:bg-orange-500/20 flex items-center justify-center transition-colors border border-orange-500/20">
                            <x-lucide-star class="w-5 h-5" />
                        </button>

                        <div x-show="activeMenu === 'premium'" x-cloak x-transition.opacity.duration.200ms
                             @mouseenter="clearTimeout(timer)"
                             class="absolute left-1/2 -translate-x-1/2 top-full mt-3 w-72 z-50">
                            
                            <div class="relative bg-card border border-border rounded-lg shadow-xl">
                                <div class="absolute left-1/2 -translate-x-1/2 -top-2 w-4 h-4 rotate-45 bg-card border-l border-t border-border"></div>

                                <div class="relative p-4 flex items-start gap-3">
                                    <div class="mt-1 w-10 h-10 rounded-full bg-orange-500/10 flex items-center justify-center shrink-0">
                                        <x-lucide-star class="w-5 h-5 text-orange-500" />
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-foreground text-sm">Включить Премиум доступ</h4>
                                        <p class="text-xs text-muted-foreground mt-1">Знакомься без ограничений</p>
                                    </div>
                                </div>
                                <div class="relative p-4 pt-0">
                                    <button class="w-full py-2.5 rounded-md bg-green-600 hover:bg-green-700 text-white text-sm font-medium transition-colors flex items-center justify-center gap-2">
                                        <x-lucide-star class="w-4 h-4" /> Включить Премиум
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 4. ПРОФИЛЬ (Десктоп) -->
                    <div class="relative" 
                         @mouseenter="clearTimeout(timer); activeMenu = 'profile'" 
                         @mouseleave="timer = setTimeout(() => activeMenu = null, 200)">
                        
                        <button class="flex items-center gap-2 border border-border rounded-full py-1 pl-1 pr-3 hover:bg-accent transition-colors">
                            
                            <x-avatar 
                                src="{{ $user->avatar_url }}" 
                                name="{{ $user->name }}" 
                                size="sm" 
                                user-id="{{ $user->id }}"
                            />                        
                            
                            <span class="hidden sm:inline text-sm font-medium text-foreground">Профиль</span>
                            <x-lucide-chevron-down class="w-4 h-4 text-muted-foreground" />
                        </button>

                        <div x-show="activeMenu === 'profile'" x-cloak x-transition.opacity.duration.200ms
                             @mouseenter="clearTimeout(timer)"
                             class="absolute left-1/2 -translate-x-1/2 top-full mt-3 w-60 z-50">
                            
                            <div class="relative bg-card border border-border rounded-lg shadow-xl">
                                <div class="absolute left-1/2 -translate-x-1/2 -top-2 w-4 h-4 rotate-45 bg-card border-l border-t border-border"></div>
                                
                                <div class="relative p-1.5">
                                    <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-accent text-sm text-foreground transition-colors">
                                        <x-lucide-user class="w-4 h-4 text-muted-foreground" /> Мой профиль
                                    </a>
                                    <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-accent text-sm text-foreground transition-colors">
                                        <x-lucide-image class="w-4 h-4 text-muted-foreground" /> Загрузить фото
                                    </a>
                                    <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-accent text-sm text-foreground transition-colors">
                                        <x-lucide-settings class="w-4 h-4 text-muted-foreground" /> Настройки сайта
                                    </a>
                                    <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-accent text-sm text-foreground transition-colors">
                                        <x-lucide-credit-card class="w-4 h-4 text-muted-foreground" /> Подписки
                                    </a>
                                    <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-accent text-sm text-foreground transition-colors">
                                        <x-lucide-book class="w-4 h-4 text-muted-foreground" /> Дневник
                                    </a>
                                </div>

                                <div class="relative border-t border-border/50 my-1"></div>

                                <div class="relative p-1.5">
                                    <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-accent text-sm text-foreground transition-colors">
                                        <x-lucide-life-buoy class="w-4 h-4 text-muted-foreground" /> Помощь
                                    </a>
                                </div>

                                <div class="relative border-t border-border/50 my-1"></div>

                                <div class="relative p-1.5">
                                    <!-- ФОРМА ВЫХОДА ДЛЯ ДЕСКТОПА -->
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-destructive/10 text-sm text-destructive transition-colors w-full">
                                            <x-lucide-log-out class="w-4 h-4" /> Выход
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- БУРГЕР ДЛЯ МОБИЛОК И ПЛАНШЕТОВ (Скрывается на lg и выше) -->
                <div class="lg:hidden">
                    <x-ui.sheet showClose="true">
                        <x-ui.sheet-trigger as-child>
                            <!-- ИСПОЛЬЗУЕМ АВАТАРКУ ВМЕСТО БУРГЕРА -->
                            <button class="rounded-full border border-border transition-transform hover:scale-105 ">
                                <x-avatar 
                                    src="{{ $user->avatar_url }}" 
                                    name="{{ $user->name }}" 
                                    size="sm" 
                                    user-id="{{ $user->id }}"
                                />
                            </button>
                        </x-ui.sheet-trigger>
                        
                        <x-ui.sheet-content side="right" class="w-full sm:max-w-sm">
                            <div class="flex flex-col h-full">
                                
                                <!-- Шапка драйвера: Инфо о юзере -->
                                <x-ui.sheet-header class="flex-shrink-0 border-b border-border pb-4">
                                    <div class="flex items-center gap-3">
                                        <x-avatar 
                                            src="{{ $user->avatar_url }}" 
                                            name="{{ $user->name }}" 
                                            size="md" 
                                            user-id="{{ $user->id }}"
                                        />
                                        <div class="flex-1 min-w-0">
                                            <p class="font-semibold text-foreground truncate">{{ $user->name }}</p>
                                            <p class="text-xs text-muted-foreground truncate">{{ $user->email }}</p>
                                        </div>
                                    </div>
                                </x-ui.sheet-header>

                                <!-- Ссылки меню -->
                                <div class="flex-1 px-4 py-6 space-y-6 overflow-y-auto scrollbar-hidden">
                                    
                                    <!-- Основное меню -->
                                    <div class="space-y-1">
                                        <a href="#" @click="window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))" class="flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium text-foreground hover:bg-accent/50 transition-colors">
                                            <x-lucide-heart class="w-5 h-5 text-muted-foreground" /> Знакомства
                                        </a>
                                        <a href="#" @click="window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))" class="flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium text-foreground hover:bg-accent/50 transition-colors">
                                            <x-lucide-search class="w-5 h-5 text-muted-foreground" /> Поиск
                                        </a>
                                        <a href="#" @click="window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))" class="flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium text-foreground hover:bg-accent/50 transition-colors">
                                            <x-lucide-message-circle class="w-5 h-5 text-muted-foreground" /> Сообщения
                                        </a>
                                        <a href="#" @click="window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))" class="flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium text-foreground hover:bg-accent/50 transition-colors">
                                            <x-lucide-layers class="w-5 h-5 text-muted-foreground" /> Подписки
                                        </a>
                                    </div>

                                    <div class="border-t border-border"></div>

                                    <!-- Финансы и подписки -->
                                    <div class="space-y-1">
                                        <a href="#" @click="window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))" class="flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium text-foreground hover:bg-accent/50 transition-colors">
                                            <x-lucide-wallet class="w-5 h-5 text-muted-foreground" /> Кошелек (0.00 &#8381;)
                                        </a>
                                        <a href="#" @click="window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))" class="flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium text-blue-500 hover:bg-blue-500/10 transition-colors">
                                            <x-lucide-gem class="w-5 h-5" /> Включить VIP статус
                                        </a>
                                        <a href="#" @click="window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))" class="flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium text-orange-500 hover:bg-orange-500/10 transition-colors">
                                            <x-lucide-star class="w-5 h-5" /> Включить Премиум
                                        </a>
                                    </div>

                                    <div class="border-t border-border"></div>

                                    <!-- Личное -->
                                    <div class="space-y-1">
                                        <a href="#" @click="window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))" class="flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium text-foreground hover:bg-accent/50 transition-colors">
                                            <x-lucide-user class="w-5 h-5 text-muted-foreground" /> Мой профиль
                                        </a>
                                        <a href="#" @click="window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))" class="flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium text-foreground hover:bg-accent/50 transition-colors">
                                            <x-lucide-image class="w-5 h-5 text-muted-foreground" /> Загрузить фото
                                        </a>
                                        <a href="#" @click="window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))" class="flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium text-foreground hover:bg-accent/50 transition-colors">
                                            <x-lucide-settings class="w-5 h-5 text-muted-foreground" /> Настройки сайта
                                        </a>
                                        <a href="#" @click="window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))" class="flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium text-foreground hover:bg-accent/50 transition-colors">
                                            <x-lucide-book class="w-5 h-5 text-muted-foreground" /> Дневник
                                        </a>
                                        <a href="#" @click="window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))" class="flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium text-foreground hover:bg-accent/50 transition-colors">
                                            <x-lucide-life-buoy class="w-5 h-5 text-muted-foreground" /> Помощь
                                        </a>
                                    </div>
                                </div>

                                <!-- Низ драйвера: Выход -->
                                <x-ui.sheet-footer class="flex-shrink-0 border-t border-border pt-4 px-4 pb-8">
                                    <!-- ФОРМА ВЫХОДА ДЛЯ МОБИЛОК -->
                                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                                        @csrf
                                        <button type="submit" @click="window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))" class="w-full flex items-center justify-center gap-2 py-2.5 rounded-md text-sm font-medium text-destructive hover:bg-destructive/10 transition-colors">
                                            <x-lucide-log-out class="w-5 h-5" /> Выход
                                        </button>
                                    </form>
                                </x-ui.sheet-footer>

                            </div>
                        </x-ui.sheet-content>
                    </x-ui.sheet>
                </div>

            </div>
        </div>
    </div>
</header>