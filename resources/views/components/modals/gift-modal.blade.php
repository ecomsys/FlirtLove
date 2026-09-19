@props(['user', 'gifts'])

@php
    $groupedGifts = $gifts->map(function ($gift) {
        if (empty($gift->type)) $gift->type = 'main';
        return $gift;
    })->groupBy('type');

    $categories = [
        'main' => 'Основные',
        'premium' => 'Премиум',
        'services' => 'Сервисы'
    ];

    $authUser = auth()->user();
    if ($authUser) {
        $authUser->loadMissing('profile.city');
        $authName = $authUser->name;
        $authAge = $authUser->profile?->age ?? '?';
        $authCity = $authUser->profile?->city?->name ?? 'Город не указан';
        $authAvatar = $authUser->avatar_url;
    } else {
        $authName = 'Гость';
        $authAge = '?';
        $authCity = 'Город не указан';
        $authAvatar = '';
    }
@endphp

<template x-teleport="body">
    <div 
         x-data="{ 
            isOpen: false, 
            isPreviewOpen: false,
            activeTab: 'main',
            
            openModal() { 
                window.modalScrollManager.lock(); 
                this.isOpen = true; 
            },
            
            close() { 
                this.isOpen = false; 
                window.modalScrollManager.unlock(); 
            },
            
            openPreview() { 
                this.isPreviewOpen = true; 
                window.modalScrollManager.lock(); 
            }, 
            
            closePreview() { 
                this.isPreviewOpen = false; 
                window.modalScrollManager.unlock(); 
            }, 
            
            selectGift(gift) {
                this.$dispatch('open-gift-checkout', gift);
                this.close();
            }
        }"
        @open-gift-modal.window="openModal()"
        @keydown.escape.window="if(isPreviewOpen) closePreview(); else if(isOpen) close();"
        x-cloak
    >
        <!-- ========================================== -->
        <!-- 1. ОСНОВНАЯ МОДАЛКА (Каталог)              -->
        <!-- ========================================== -->
        <div 
            x-show="isOpen"
            style="display: none;"
            class="fixed inset-0 z-[100] overflow-y-auto bg-black/45 backdrop-blur-sm"
            @click.self="close()"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        >
            <div class="flex min-h-full items-start justify-center p-4 py-20">
                <div 
                    class="relative bg-card border border-border rounded-2xl shadow-2xl w-full max-w-3xl min-h-[36rem] flex flex-col"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                >
                    <!-- ШАПКА -->
                    <div class="flex items-center justify-between p-4 border-b border-border">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 109.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1114.625 7.5H12m0 0V21" /></svg>
                            <h3 class="text-base font-semibold text-foreground">Магазин подарков</h3>
                        </div>
                        <button @click="close()" class="text-muted-foreground hover:text-foreground hover:bg-accent rounded-md p-1 transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <!-- ТАБЫ -->
                    <div class="flex border-b border-border px-4 gap-4">
                        @foreach($categories as $key => $label)
                            <button 
                                @click="activeTab = '{{ $key }}'"
                                :class="activeTab === '{{ $key }}' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'"
                                class="py-3 text-sm font-medium border-b-2 transition-colors"
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>

                    <!-- КОНТЕНТ -->
                    <div class="p-4 flex-1">
                        @foreach($categories as $key => $label)
                            <div x-show="activeTab === '{{ $key }}'">
                                @if($key === 'premium')
                                <div class="mb-4 p-3 bg-primary/5 border border-primary/20 rounded-lg flex flex-col sm:flex-row items-center justify-between gap-2 text-center sm:text-left">
                                    <p class="text-xs text-muted-foreground">
                                        <span class="font-semibold text-foreground">Премиум подарок</span> невозможно пропустить из-за особого способа доставки.
                                    </p>
                                    <button @click="openPreview()" class="text-xs font-medium text-primary hover:underline whitespace-nowrap flex items-center gap-1">
                                        Показать пример                                        
                                    </button>
                                </div>
                                @endif

                                <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                                    @forelse(($groupedGifts->get($key, collect())) as $gift)
                                        <div 
                                            @click="selectGift({ id: {{ $gift->id }}, name: '{{ $gift->name }}', price: {{ $gift->price }}, image: '{{ $gift->image_url }}' })"
                                            class="group cursor-pointer flex flex-col overflow-hidden"
                                        >
                                            <div class="relative w-full aspect-square flex items-center justify-center transition-colors group-hover:bg-blue-50/80">
                                                <x-media-image src="{{ $gift->image_url }}" alt="{{ $gift->name }}" class="w-full h-full object-contain"/>
                                            </div>
                                            <div class="w-full text-center text-[0.75rem] font-medium text-primary opacity-0 group-hover:opacity-80 transition-opacity h-6 flex items-center justify-center bg-blue-100">
                                                {{ $gift->price }} ед.
                                            </div>
                                        </div>
                                    @empty
                                        <p class="col-span-full text-center text-muted-foreground text-sm py-10">В этой категории пока нет подарков.</p>
                                    @endforelse
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- 2. МОДАЛКА ПРЕВЬЮ (Пример премиум подарка) -->
        <!-- ========================================== -->
        <div 
            x-show="isPreviewOpen"
            style="display: none;"
            class="fixed inset-0 z-[110] overflow-y-auto bg-black/85 backdrop-blur-sm"
            @click.self="closePreview()"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        >
            <div class="flex min-h-full items-center justify-center p-4 py-16">
                <div 
                    class="relative bg-card border border-border rounded-2xl shadow-2xl w-full max-w-lg flex flex-col overflow-hidden"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="scale-95 opacity-0"
                    x-transition:enter-end="scale-100 opacity-100"
                >
                    <!-- ШАПКА -->
                    <div class="flex items-center justify-between p-4 border-b border-border">
                        <h3 class="text-base font-semibold text-foreground">Пример просмотра премиум подарка</h3>
                        <button @click="closePreview()" class="text-muted-foreground hover:text-foreground hover:bg-accent rounded-md p-1 transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <!-- КОНТЕНТ -->
                    <div class="flex flex-col items-center text-center gap-6 pt-6">
                        <img src="https://emojicdn.elk.sh/🎁" alt="Premium Gift" class="w-52 h-52 object-contain drop-shadow-lg animate-pulse">
                        
                        <div class="w-full flex items-start gap-3 bg-muted/50 p-4 text-left border-y border-border">
                                                       
                            <x-avatar src="{{ $authAvatar }}" name="{{ $authName }}" size="lg" user-id="{{ $authUser->id }}" />                                                                                
                            
                            <div class="flex-1 min-w-0">
                                <div class="flex items-baseline gap-2">
                                    <p class="font-semibold text-foreground text-sm">{{ $authName }}, {{ $authAge }}</p>
                                    <p class="text-xs text-muted-foreground">{{ $authCity }}</p>
                                </div>
                                <p class="text-sm text-foreground/80 mt-1 break-words">
                                    Пример комментария: "Подарок лучше любых слов!"
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- ФУТЕР -->
                    <div class="flex justify-center p-4">
                        <x-ui.button @click="closePreview()" variant="default" size="sm" class="px-10">
                            Закрыть
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>