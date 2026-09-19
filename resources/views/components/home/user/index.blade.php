@props(['user', 'isFavorited' => false, 'isBlocked' => false, 'isReported' => false])

<div class="space-y-6" x-data="{ isBlockedAlert: {{ $isBlocked ? 'true' : 'false' }} }" @blocked-changed.window="isBlockedAlert = $event.detail">

    {{-- Реактивный алерт о блокировке --}}
    <template x-if="isBlockedAlert">
        <x-ui.alert
            class="bg-destructive/10 text-destructive rounded-md border-0 border-l-4 border-destructive [&>svg]:text-destructive">
            <x-lucide-circle-x />
            <x-ui.alert-title>Вы заблокировали этого пользователя. Вы не можете писать друг другу.</x-ui.alert-title>
        </x-ui.alert>
    </template>

    <!-- Карточка профиля -->
    <div class="bg-card border border-border rounded-xl shadow-sm overflow-hidden flex flex-col md:flex-row gap-8 p-6">
        <!-- Левая часть: Фото -->
        <div class="w-full md:w-[33%] shrink-0">
            <x-media-box.photo-gallery :photos="$user->photos" :photos-count="$user->photos_count" :is-premium="$user->has_active_premium" :name="$user->name" />
        </div>

        <!-- Правая часть: Информация -->
        <div class="flex-1 py-2 flex flex-col">
            <!-- Шапка: Имя, Город, Статус, Просмотры (Справа Лайк/Дизлайк если авторизован) -->


            {{-- Первый ряд --}}
            {{-- ИМЯ --}}
            <h1 class="text-2xl font-bold text-foreground flex items-center gap-2.5">
                <!-- Статус верификации (Слева от имени) -->
                @if ($user->is_verified)
                    <x-web-ui.tooltip text="Верификация по фото пройдена">
                        <svg class="w-5 h-5 text-blue-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.912 2.912c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.912 2.912 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.912-2.912 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.912-2.912zm7.066 5.717a1 1 0 00-1.414-1.414l-3.5 3.5-1.5-1.5a1 1 0 00-1.414 1.414l2.207 2.207a1 1 0 001.414 0l4.207-4.207z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-web-ui.tooltip>
                @endif

                {{ $user->name }}, <span>{{ $user->profile->age ?? '?' }}</span>

                <!-- Знак зодиака (Справа от возраста) -->
                <x-web-ui.zodiac-sign :sign="$user->profile->zodiac_sign" />
            </h1>

            {{-- второй ряд --}}
            <div class="flex items-center justify-between gap-3  mb-4">


                <div class="flex flex-col gap-2 ">
                    {{-- РЕГИОН --}}
                    <p class="text-blue-500 text-sm mt-1 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        {{ $user->profile?->city?->name ?? 'Город не указан' }}
                    </p>

                    <!-- Статус и Просмотры -->
                    <div class="flex items-center justify-between gap-3">
                        <!-- Если юзер давно не заходил, компонент просто ничего не выведет -->
                        <x-web-ui.user-status :last-seen="$user->last_seen" :is-online="$user->is_online" />

                        <!-- Счётчик просмотров всегда будет справа -->
                        <x-home.user.blocks.viewers-counter :count="5" />
                    </div>
                </div>

                <!-- Кнопки Лайк/Дизлайк (Только для авторизованных) -->
                @auth
                    <x-home.user.blocks.swipe-buttons :user="$user" />
                @endauth
            </div>

            {{-- Статус (Headline) --}}
            @if ($user->profile?->headline)
                <div
                    class="inline-block bg-background border border-border rounded-full px-8 py-4 mb-6 shadow-lg shadow-[inset_0_0.125rem_0.25rem_rgba(0,0,0,0.15)]">
                    <p class="text-sm text-foreground/80 italic font-light leading-relaxed">
                        {{ $user->profile?->headline }}
                    </p>
                </div>
            @endif


            {{-- Кнопки действий (Компонент) --}}
            <x-home.user.blocks.action-buttons :user="$user" :is-favorited="$isFavorited" :is-blocked="$isBlocked" :is-reported="$isReported" />

            <!-- Баннер Премиума (Если авторизован и НЕТ премиума) -->
            @auth
                @if (!auth()->user()->has_active_premium)
                    <x-home.user.blocks.premium-banner />
                @endif
            @endauth

            <!-- Блок "Я ищу" (Фильтры) -->
            @if ($user->preferences && $user->profile)
                <div class="mb-6 border-t border-border pt-4">
                    <h3 class="text-base font-medium text-accent-foreground mb-1">Я ищу</h3>

                    <p class="text-base text-muted-foreground">
                        Ищу
                        @if ($user->preferences->preferred_gender === 'male')
                            парней
                        @elseif ($user->preferences->preferred_gender === 'female')
                            девушек
                        @endif
                        от {{ $user->preferences->preferred_age_min ?? 18 }}
                        до {{ $user->preferences->preferred_age_max ?? 99 }} лет,
                        для {{ mb_strtolower(__('auth.' . $user->profile->dating_goal)) }}.
                    </p>
                </div>
            @endif

            <!-- Блок "Кого я хочу найти" (Текстовое описание) -->
            @if (!empty($user->profile->looking_for))
                <div class="mb-6 border-t border-border pt-4">
                    <h3 class="text-base font-medium text-accent-foreground mb-2">Кого я хочу найти</h3>
                    <p class="text-sm text-foreground/80 italic leading-relaxed">
                        {{ $user->profile->looking_for }}
                    </p>
                </div>
            @endif         

            <!-- Блок "Мои подарки" -->
            <x-home.user.blocks.gifts :user="$user" />

            <!-- Блок "Интересы" -->
            @if (!empty($user->profile->interests) && is_array($user->profile->interests))
                <div class="mb-6 border-t border-border pt-4">
                    <h3 class="text-base font-medium text-accent-foreground mb-3">Интересы</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($user->profile->interests as $interest)
                            <x-ui.badge variant="outline" class=" rounded-full">{{ $interest }}</x-ui.badge>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Личные данные юзера --}}
            <x-home.user.blocks.personal-info :user="$user" />

            {{-- Автопортрет --}}
            <x-home.user.blocks.self-portrait :user="$user" />        

             <!-- Адрес страницы -->            
            <div class="mb-6 border-t border-border pt-4">
                <div class="flex flex-col sm:flex-row sm:items-baseline gap-1 sm:gap-4">
                    <span class="text-muted-foreground text-sm font-light sm:w-2/5">Адрес страницы</span>
                    <a href="{{ route('user.show', $user) }}" 
                    class="text-blue-500 dark:text-blue-400 text-sm hover:underline sm:w-3/5 truncate"
                    target="_blank">
                        {{ route('user.show', $user) }}
                    </a>
                </div>
            </div>

        </div>
    </div>
    
    <!-- Магазин подарков и Оплата -->
    @auth
        @if(auth()->id() !== $user->id)
            {{-- Модалка - "Каталог подарков" --}}
            <x-modals.gift-modal :user="$user" :gifts="\App\Models\Gift::active()->get()" />

            {{-- Модалка - "Оплата подарка" --}}
            <x-modals.gift-checkout-modal :user="$user" />            

            {{-- Модалка - "Купить Кредиты" --}}
            <x-modals.billing-modal />
        @endif
    @endauth

    <!-- Подключаем компонент Лайтбокса (Слушает события) -->
    <x-media-box.photo-lightbox :photos="$user->photos" :name="$user->name" :is-auth="auth()->check()" />
</div>
