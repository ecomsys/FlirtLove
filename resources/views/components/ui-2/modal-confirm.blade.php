<div 
    {{ $attributes->merge(['class' => 'fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm']) }}
    x-cloak 
    style="display: none;"
    @click.self="$dispatch('close')"
    @keydown.escape.window="$dispatch('close')"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
>
    <div
        class="relative bg-card border border-border rounded-lg shadow-2xl max-w-sm w-full mx-4 overflow-hidden"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="scale-95 opacity-0"
        x-transition:enter-end="scale-100 opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="scale-100 opacity-100"
        x-transition:leave-end="scale-95 opacity-0"
    >
        <div class="p-6 space-y-4 text-center">
            <div class="mx-auto p-2 rounded-full w-12 h-12 flex items-center justify-center"
                x-bind:class="isBlocked ? 'bg-green-500/10' : 'bg-destructive/10'"
            >
                <template x-if="isBlocked">
                    <svg class="w-6 h-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                </template>
                <template x-if="!isBlocked">
                    <svg class="w-6 h-6 text-destructive" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728" /></svg>
                </template>
            </div>
            <h2 class="text-lg font-semibold text-foreground" x-text="isBlocked ? 'Разблокировать пользователя?' : 'Заблокировать пользователя?'"></h2>
            <p class="text-sm text-muted-foreground" x-text="isBlocked ? 'Пользователь снова сможет писать вам и видеть профиль.' : 'Он больше не сможет писать вам или видеть ваш профиль.'"></p>
        </div>
        <div class="flex items-center justify-center gap-2 p-4 border-t border-border bg-muted/20">
            <x-ui.button @click="$dispatch('close')" variant="outline" size="sm">Отмена</x-ui.button>
            
            <!-- КНОПКА ЗАБЛОКИРОВАТЬ (Красная) -->
            <x-ui.button 
                x-show="!isBlocked" 
                @click="$dispatch('confirm')" 
                variant="destructive" 
                size="sm" 
                x-bind:disabled="loading"
            >
                <span x-show="!loading">Заблокировать</span>
                <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            </x-ui.button>

            <!-- КНОПКА РАЗБЛОКИРОВАТЬ (Зеленая) -->
            <x-ui.button 
                x-show="isBlocked" 
                @click="$dispatch('confirm')" 
                variant="success" 
                size="sm" 
                x-bind:disabled="loading"
            >
                <span x-show="!loading">Разблокировать</span>
                <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            </x-ui.button>
        </div>
    </div>
</div>