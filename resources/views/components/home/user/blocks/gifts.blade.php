@props(['user'])

@php
    $gifts = $user->giftsReceived()->with('sender.photos')->latest()->take(6)->get();
    $hasPremium = auth()->check() && auth()->user()->has_active_premium;
    
    // УМНАЯ ЛОГИКА: Определяем, чей это профиль
    $isOwnProfile = auth()->check() && auth()->id() === $user->id;
@endphp

<div 
    x-data="{ 
        activeGift: null,
        isVisible: false, 
        giftLeft: 0,
        giftTop: 0,
        giftBottom: 0,
        placement: 'top',
        hasPremium: {{ $hasPremium ? 'true' : 'false' }},
        hideTimeout: null,
        
        showGift(event, gift) {
            clearTimeout(this.hideTimeout);
            const rect = event.currentTarget.getBoundingClientRect();
            this.giftLeft = rect.left + (rect.width / 2);
            
            if (rect.top > 150) {
                this.placement = 'top';
                this.giftBottom = window.innerHeight - rect.top + 12;
                this.giftTop = null;
            } else {
                this.placement = 'bottom';
                this.giftTop = rect.bottom + 12;
                this.giftBottom = null;
            }
            
            if (this.giftLeft < 150) this.giftLeft = 150;
            if (this.giftLeft > window.innerWidth - 150) this.giftLeft = window.innerWidth - 150;
            
            this.activeGift = gift;
            this.isVisible = true;
        },
        hideGift() {
            this.hideTimeout = setTimeout(() => {
                this.isVisible = false;
            }, 150);
        },
        cancelHide() {
            clearTimeout(this.hideTimeout);
        }
    }"
    class="mb-6 border-t border-border pt-4 relative"
>
    <h3 class="text-base font-medium text-accent-foreground mb-3">
        {{ $isOwnProfile ? 'Мои подарки' : 'Подарки' }}
    </h3>
        
    <div class="bg-muted/30 dark:bg-muted/20 border border-border/50 rounded-xl p-4">
        <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-7 gap-3">
            
            <!-- Кнопка "Подарить" (Показывается всем, кроме хозяина профиля) -->
            @if (!$isOwnProfile)
            <x-web-ui.tooltip text="Подарить подарок">
                <div 
                    @auth @click="window.dispatchEvent(new CustomEvent('open-gift-modal'))" @endauth
                    @guest @click="window.dispatchEvent(new CustomEvent('open-register-modal'))" @endguest
                    class="aspect-square rounded-lg border border-dashed border-primary/50 flex items-center justify-center cursor-pointer hover:border-primary hover:bg-accent transition-colors text-muted-foreground hover:text-primary group relative"
                >
                    <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 109.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1114.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                    </svg>
                    <div class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-primary text-primary-foreground rounded-full flex items-center justify-center shadow-sm group-hover:scale-110 transition-transform">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    </div>
                </div>
            </x-web-ui.tooltip>
            @endif

            <!-- Вывод подарков -->
            @forelse($gifts as $gift)
                @php
                    if ($gift->created_at->isToday()) {
                        $dateString = 'Сегодня';
                    } elseif ($gift->created_at->isYesterday()) {
                        $dateString = 'Вчера';
                    } else {
                        $dateString = $gift->created_at->translatedFormat('j F Y');
                    }
                @endphp

    
                <div 
                    class="aspect-square rounded-lg overflow-hidden cursor-pointer hover:shadow-md hover:scale-105 transition-all bg-background/50"
                  @mouseenter="showGift($event, {{ Illuminate\Support\Js::from([
                    'is_private' => $gift->is_private,
                    'sender_slug' => $gift->sender?->slug, 
                    'sender_name' => $gift->sender?->name ?? 'Аноним',
                    'sender_avatar' => $gift->is_private ? '' : ($gift->sender?->avatar_url ?? ''),
                    'date' => $dateString,
                    'message' => $hasPremium ? $gift->message : null,
                    'has_message' => $gift->message !== null,
                ]) }})"
                    @mouseleave="hideGift()"
                >
                    <x-media-image src="{{ $gift->image_url }}" alt="{{ $gift->snapshot_name }}" class="w-full h-full object-cover" />
                </div>
            @empty
                <p class="text-base text-center text-muted-foreground py-2 col-span-3 sm:col-span-5 md:col-span-6 self-center">
                    Подарков пока нет. @if(!$isOwnProfile) Будь первым! @else Ждите сюрпризов! @endif
                </p>
            @endforelse
        </div>
    </div>

    <!-- ЕДИННАЯ ХОВЕР-КАРТОЧКА (Телепортируется в body) -->
    <template x-teleport="body">
        <div 
            x-show="isVisible"    
            x-cloak 
            style="display: none;"
            @mouseenter="cancelHide()"   
            @mouseleave="hideGift()"     
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-1"
            class="fixed z-[80] w-72 p-4 bg-popover border border-border rounded-xl shadow-2xl"
            x-bind:style="`
                left: ${giftLeft}px; 
                transform: translateX(-50%); 
                ${placement === 'top' ? `bottom: ${giftBottom}px` : `top: ${giftTop}px`}
            `"
        >
            <!-- Носик (Стрелочка) -->
            <div class="absolute left-1/2 -translate-x-1/2 w-3 h-3 rotate-45 bg-popover border-border"
                 x-bind:class="placement === 'top' ? 'bottom-[-7px] border-b border-r' : 'top-[-7px] border-t border-l'">
            </div>

            <div class="flex gap-3 relative">                              
                                
                <div class="shrink-0">
                    <!-- Инкогнито (для приватных) -->
                    <template x-if="activeGift?.is_private">
                        <div class="w-12 h-12 rounded-md bg-muted flex items-center justify-center text-muted-foreground border border-border">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </div>
                    </template>
                    
                    <!-- Реальная аватарка (КЛИКАБЕЛЬНАЯ) -->
                    <template x-if="!activeGift?.is_private">
                        <a :href="`/user/${activeGift?.sender_slug}`" class="block hover:opacity-80 transition-opacity">
                             <div class="w-12 h-12 rounded-sm overflow-hidden bg-muted border border-border flex items-center justify-center">
                                <img x-show="activeGift?.sender_avatar" :src="activeGift?.sender_avatar" class="w-full h-full object-cover" alt="Avatar">
                                
                                <div x-show="!activeGift?.sender_avatar" class="w-full h-full flex items-center justify-center font-semibold text-primary bg-primary/10">
                                    <span x-text="activeGift?.sender_name ? activeGift.sender_name.charAt(0).toUpperCase() : '?'"></span>
                                </div>
                            </div>
                        </a>
                    </template>
                </div>
                
                <!-- Информация -->
                <div class="flex-1 min-w-0">
                    <template x-if="activeGift?.is_private">
                        <h4 class="text-sm font-semibold text-foreground truncate">Приватный подарок</h4>
                    </template>
                    <template x-if="!activeGift?.is_private">
                        <a :href="`/user/${activeGift?.sender_slug}`" class="text-sm font-semibold text-blue-500 truncate hover:underline hover:text-primary block" x-text="activeGift?.sender_name"></a>                        
                    </template>

                    <div class="text-muted-foreground flex items-center gap-1.5 pt-0.5 text-xs">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0V11.25A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>
                        <span x-text="activeGift?.date"></span>
                    </div>
                    
                    <template x-if="!activeGift?.is_private">
                        <div class="mt-2 pt-2 border-t border-border text-sm text-muted-foreground">
                            <template x-if="!activeGift?.has_message">
                                <p class="italic opacity-70">Без сообщения</p>
                            </template>
                            <template x-if="activeGift?.has_message && hasPremium">
                                <p class="line-clamp-3 break-words" x-text="activeGift?.message"></p>
                            </template>
                            <template x-if="activeGift?.has_message && !hasPremium">
                                <p>
                                    Просмотр сообщения недоступен 
                                    <a href="/premium" class="text-blue-500 hover:underline hover:text-primary cursor-pointer">(Показать)</a>
                                </p>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>