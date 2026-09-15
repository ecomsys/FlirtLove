@props(['user'])

<div class="space-y-6">  

    <!-- Карточка профиля -->
    <div class="bg-card border border-border rounded-xl shadow-sm overflow-hidden flex flex-col md:flex-row gap-8 p-6">
        
        <!-- Левая часть: Фото -->        
        <div class="w-full md:w-[33%] shrink-0">
            <x-ui-2.gallery-2 
                :photos="$user->photos" 
                :photos-count="$user->photos_count" 
                :is-premium="$user->has_active_premium" 
                :name="$user->name" 
            />
        </div>

        <!-- Правая часть: Информация -->
        <div class="flex-1 py-6 flex flex-col">
            <!-- Шапка: Имя, Город, Статус, Просмотры (Справа Лайк/Дизлайк если авторизован) -->
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h1 class="text-2xl font-bold text-foreground flex items-center gap-2">
                        {{ $user->name }}, <span>{{ $user->profile->age ?? '?' }}</span>
                    </h1>
                    <p class="text-muted-foreground text-sm mt-1 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        {{ $user->profile?->city?->name ?? 'Город не указан' }}
                    </p>
                    <!-- Статус и просмотры (Заглушка просмотров) -->
                    <div class="flex items-center gap-2 mt-2 text-xs">
                        @if($user->is_online)
                            <span class="text-green-600 font-medium flex items-center gap-1">
                                <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span> Онлайн
                            </span>
                        @else
                            <span class="text-muted-foreground">Был недавно</span>
                        @endif
                        <span class="text-muted-foreground">•</span>
                        <span class="text-muted-foreground">Сейчас просматривают: 5 человек</span> <!-- Заглушка -->
                    </div>
                </div>

                <!-- Кнопки Лайк/Дизлайк (Только для авторизованных) -->
                @auth
                    <div class="flex gap-2">
                        <button class="w-10 h-10 rounded-full bg-red-500/10 hover:bg-red-500/20 text-red-500 flex items-center justify-center transition-colors" title="Не одобрить">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                        <button class="w-10 h-10 rounded-full bg-green-500/10 hover:bg-green-500/20 text-green-500 flex items-center justify-center transition-colors" title="Лайк">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" /></svg>
                        </button>
                    </div>
                @endauth
            </div>

            <!-- Кнопки действий (Динамические) -->
            <div class="flex flex-wrap gap-3 mb-6">
                @auth
                    <!-- Авторизованный: 3 кнопки + Меню -->
                    <x-ui.button variant="default" class="flex-1 min-w-[140px]">
                        <span class="flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" /></svg>
                            Написать
                        </span>
                    </x-ui.button>
                    <x-ui.button variant="outline" class="flex-1 min-w-[140px]">
                        <span class="flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" /></svg>
                            В избранное
                        </span>
                    </x-ui.button>
                    
                    <!-- Триггер меню (Три точки) -->
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="h-9 px-3 border border-border rounded-md hover:bg-accent transition-colors flex items-center">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v.01M12 12v.01M12 19v.01" /></svg>
                        </button>
                        <div x-show="open" @click.outside="open = false" x-transition class="absolute right-0 mt-2 w-48 bg-popover border border-border rounded-md shadow-md z-50" style="display: none;">
                            <button class="w-full text-left px-4 py-2 text-sm hover:bg-accent transition-colors">Пожаловаться</button>
                            <button class="w-full text-left px-4 py-2 text-sm text-destructive hover:bg-accent transition-colors">Заблокировать</button>
                        </div>
                    </div>
                @else
                    <!-- Гость: Только одна кнопка, вызывает модалку -->
                    <x-ui.button variant="default" class="flex-1 min-w-[140px]" @click="$dispatch('open-login-modal')">
                        <span class="flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" /></svg>
                            Написать
                        </span>
                    </x-ui.button>
                @endauth
            </div>

            <!-- Баннер Премиума (Если авторизован и НЕТ премиума) -->
            @auth
                @if(!auth()->user()->has_active_premium)
                    <div class="mb-6 bg-gradient-to-r from-primary/10 to-primary/5 border border-primary/20 rounded-lg p-4 flex items-center justify-between">
                        <div>
                            <h4 class="font-semibold text-foreground text-sm">Хочешь больше возможностей?</h4>
                            <p class="text-muted-foreground text-xs mt-1">Получи VIP-статус и выделяйся в поиске!</p>
                        </div>
                        <x-ui.button size="sm" variant="default">Сделать VIP</x-ui.button>
                    </div>
                @endif
            @endauth

            <!-- Блок "Я ищу" -->
            <div class="mb-6 border-t border-border pt-4">
                <h3 class="text-sm font-medium text-muted-foreground mb-2">Я ищу</h3>
                <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-foreground">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-5.13a4 4 0 11-8 0 4 4 0 018 0zm6 0a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                        @if($user->preferences)
                            {{ $user->preferences->preferred_gender === 'male' ? 'Парней' : ($user->preferences->preferred_gender === 'female' ? 'Девушек' : 'Всех') }}
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        Возраст: {{ $user->preferences->preferred_age_min ?? 18 }} - {{ $user->preferences->preferred_age_max ?? 99 }} лет
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" /></svg>
                        Цель: {{ __('auth.' . $user->profile->dating_goal) }}
                    </div>
                </div>
            </div>

            <!-- Блок "Мои подарки" -->
            <div class="mb-6 border-t border-border pt-4">
                <h3 class="text-sm font-medium text-muted-foreground mb-2">Мои подарки</h3>
                <div class="bg-muted/20 rounded-lg p-4 flex flex-wrap gap-4">
                    @if($user->giftsReceived->isNotEmpty())
                        @foreach($user->giftsReceived as $gift)
                            <img src="{{ $gift->image_url }}" alt="{{ $gift->snapshot_name }}" class="w-16 h-16 object-contain rounded-md border border-border bg-background">
                        @endforeach
                    @else
                        <p class="text-xs text-muted-foreground py-2">Пока нет подарков. Будь первым!</p>
                    @endif
                </div>
            </div>

            <!-- Подробная информация (Всё, что заполнил юзер) -->
            <div class="border-t border-border pt-4 grid grid-cols-2 gap-4 text-sm">
                @if($user->profile->zodiac_sign)
                    <div>
                        <p class="text-muted-foreground text-xs mb-1">Знак зодиака</p>
                        <p class="font-medium text-foreground">{{ $user->profile->zodiac_name }}</p>
                    </div>
                @endif
                @if($user->profile->height)
                    <div>
                        <p class="text-muted-foreground text-xs mb-1">Рост</p>
                        <p class="font-medium text-foreground">{{ $user->profile->height }} см</p>
                    </div>
                @endif
                @if($user->profile->weight)
                    <div>
                        <p class="text-muted-foreground text-xs mb-1">Вес</p>
                        <p class="font-medium text-foreground">{{ $user->profile->weight }} кг</p>
                    </div>
                @endif
                @if($user->profile->children_status)
                    <div>
                        <p class="text-muted-foreground text-xs mb-1">Дети</p>
                        <p class="font-medium text-foreground">{{ __('profile_fields.children_status.' . $user->profile->children_status) }}</p>
                    </div>
                @endif
                @if($user->profile->education_level)
                    <div>
                        <p class="text-muted-foreground text-xs mb-1">Образование</p>
                        <p class="font-medium text-foreground">{{ __('profile_fields.education_level.' . $user->profile->education_level) }}</p>
                    </div>
                @endif
                <!-- Сюда добавишь остальные параметры (Курение, Алкоголь, Жильё и т.д.) по аналогии -->
            </div>
            
            @if($user->profile->bio)
            <div class="border-t border-border pt-4 mt-4">
                <h3 class="text-sm font-medium text-muted-foreground mb-2">О себе</h3>
                <p class="text-foreground text-sm leading-relaxed">{{ $user->profile->bio }}</p>
            </div>
            @endif

        </div>
    </div>

        <!-- Подключаем компонент Лайтбокса (Слушает события) -->
    <x-ui-2.lightbox-2 :photos="$user->photos" :name="$user->name" :is-auth="auth()->check()" />
</div>