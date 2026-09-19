@props(['user'])

@php
    // Получаем баланс текущего юзера для отображения в чекауте
    $authUser = auth()->user();
    $balance = $authUser ? ($authUser->balance->credits ?? 0) : 0;
@endphp

<template x-teleport="body">
    <div 
        x-data="{ 
            isOpen: false, 
            selectedGift: null,
            message: '',
            loading: false,
            csrf: '{{ csrf_token() }}',
            userBalance: {{ $balance }},
            
            openModal(gift) { 
                this.selectedGift = gift; 
                this.isOpen = true; 
                window.modalScrollManager.lock(); 
            },
            close() { 
                this.isOpen = false; 
                this.selectedGift = null;
                this.message = '';
                window.modalScrollManager.unlock(); 
            },
            backToCatalog() {
                this.close();
                this.$dispatch('open-gift-modal');
            },
            get enoughCredits() {
                return this.selectedGift && this.userBalance >= this.selectedGift.price;
            },
            async sendGift() {
                if (!this.selectedGift || this.loading || !this.enoughCredits) return;
                this.loading = true;
                
                try {
                    const res = await fetch(`/user/{{ $user->slug }}/gift`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': this.csrf,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            gift_id: this.selectedGift.id,
                            message: this.message
                        })
                    });
                    
                    const data = await res.json();
                    
                    if (data.success) {
                        this.$dispatch('show-toast', { type: 'success', message: data.message || 'Подарок успешно отправлен!' });
                        this.close();
                        setTimeout(() => window.location.reload(), 500);
                    } else {
                        this.$dispatch('show-toast', { type: 'error', message: data.message || 'Ошибка отправки' });
                        if (data.redirect) {
                            setTimeout(() => window.location.href = data.redirect, 1000);
                        }
                    }
                } catch(e) {
                    console.error(e);
                    this.$dispatch('show-toast', { type: 'error', message: 'Ошибка сети' });
                } finally {
                    this.loading = false;
                }
            }
        }"
        @open-gift-checkout.window="openModal($event.detail)"
        @keydown.escape.window="isOpen && close()"
        x-cloak
    >
        <!-- ОВЕРЛЕЙ: Скроллится именно этот блок (overflow-y-auto без скрытий) -->
        <div 
            x-show="isOpen"
            style="display: none;"
            class="fixed inset-0 z-[110] overflow-y-auto bg-black/45 backdrop-blur-sm"
            @click.self="close()"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        >
            <!-- ВНУТРЕННИЙ КОНТЕЙНЕР: Растягивает оверлей и прибивает модалку к верху -->
            <div class="flex min-h-full items-start justify-center p-4 py-20">
                
                <!-- ТЕЛО МОДАЛКИ (Растёт вниз, никаких внутренних скроллов) -->
                <div 
                    class="relative bg-card border border-border rounded-2xl shadow-2xl w-full max-w-md my-0 flex flex-col"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                >
                    <!-- ШАПКА -->
                    <div class="flex items-center justify-between p-4 border-b border-border">
                        <h3 class="text-base font-semibold text-foreground">Оформление подарка</h3>
                        <button @click="close()" class="text-muted-foreground hover:text-foreground hover:bg-accent rounded-md p-1 transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <!-- КОНТЕНТ -->
                    <div class="p-6 flex flex-col items-center text-center gap-5">
                        <!-- Кнопка "Назад в каталог" -->
                        <button @click="backToCatalog()" class="self-start text-xs text-muted-foreground hover:text-foreground flex items-center gap-1 -mt-2">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                            Назад в каталог
                        </button>
                        
                        <x-media-image x-bind:src="selectedGift?.image" class="w-28 h-28 object-contain drop-shadow-lg" alt="Gift"/>
                        
                        <div>
                            <h4 class="font-semibold text-foreground text-lg" x-text="selectedGift?.name"></h4>
                            <p class="text-sm text-yellow-500 font-medium mt-1">
                                <span x-text="selectedGift?.price"></span> ед.
                            </p>
                        </div>

                        <!-- БАЛАНС ЮЗЕРА -->
                        <div class="w-full flex items-center justify-center gap-2 text-sm py-2 px-4 bg-muted/50 rounded-lg">
                            <span class="text-muted-foreground">Ваш баланс:</span>
                            <span class="font-semibold" :class="enoughCredits ? 'text-green-500' : 'text-destructive'">
                                <span x-text="userBalance"></span> ед.
                            </span>
                        </div>

                        <div class="w-full">
                            <x-ui.textarea 
                                x-model="message" 
                                placeholder="Добавить сообщение (необязательно)..." 
                                maxlength="150"                                
                            ></x-ui.textarea>
                            <p class="text-right text-xs text-muted-foreground mt-1" x-text="`${message.length}/150`"></p>
                        </div>
                    </div>

                    <!-- ФУТЕР -->
                    <div class="p-4 border-t border-border bg-muted/20 rounded-b-2xl">
                        <!-- ЕСЛИ КРЕДИТОВ ХВАТАЕТ -->
                        <template x-if="enoughCredits">
                            <x-ui.button 
                                @click="sendGift()" 
                                x-bind:disabled="loading"
                                class="w-full bg-gradient-to-r from-primary to-primary/80 text-primary-foreground hover:from-primary/90"
                            >
                                <span x-show="!loading">Подарить за <span x-text="selectedGift?.price"></span> ед.</span>
                                <svg x-show="loading" class="w-5 h-5 animate-spin mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            </x-ui.button>
                        </template>

                        <!-- ЕСЛИ КРЕДИТОВ НЕ ХВАТАЕТ -->
                        <template x-if="!enoughCredits">
                           <button @click="close(); window.dispatchEvent(new CustomEvent('open-billing-modal'))" class="w-full flex items-center justify-center gap-2 bg-destructive text-destructive-foreground hover:bg-destructive/90 font-medium py-2.5 rounded-md transition-colors text-sm">
                                Недостаточно кредитов. Пополнить
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>