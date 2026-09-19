@props(['user'])

<div 
    {{ $attributes->merge(['class' => 'fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/45 backdrop-blur-sm']) }}
    x-cloak 
    style="display: none;"
    @click.self="reportModal = false"
    @keydown.escape.window="reportModal = false"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
>
    <div 
        class="relative bg-card border border-border rounded-lg shadow-2xl max-w-md w-full mx-4"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="scale-95 opacity-0"
        x-transition:enter-end="scale-100 opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="scale-100 opacity-100"
        x-transition:leave-end="scale-95 opacity-0"
    >
        <form @submit.prevent="submitReport()">
            <div class="p-6 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-destructive/10 rounded-full">
                        <svg class="w-6 h-6 text-destructive" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                    </div>
                    <h2 class="text-lg font-semibold text-foreground">Подать жалобу</h2>
                </div>
                <p class="text-sm text-muted-foreground">Выберите причину. Модераторы рассмотрят её в ближайшее время.</p>

                <!-- Обертка с z-index чтобы селект перекрывал футер модалки -->
                <div class="space-y-2 relative z-[80]">
                    <label class="text-xs text-muted-foreground">Причина</label>
                    
                    <x-ui.select x-model="reportReason" x-modelable="value" class="w-full">
                        <x-ui.select-trigger class="w-full" aria-label="Причина">
                            <x-ui.select-value placeholder="Выберите причину..." />
                        </x-ui.select-trigger>
                        <x-ui.select-content class="z-[100]">
                            @foreach(\App\Enums\ReportReason::options() as $value => $label)
                                <x-ui.select-item value="{{ $value }}">{{ $label }}</x-ui.select-item>
                            @endforeach
                        </x-ui.select-content>
                    </x-ui.select>
                    
                </div>
                
                <div class="space-y-2">
                    <label class="text-xs text-muted-foreground">Комментарий (необязательно)</label>
                    <textarea x-model="reportDesc" placeholder="Дополнительное описание" class="w-full h-24 border border-border rounded-md bg-background p-3 text-sm resize-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring outline-none"></textarea>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 p-4 border-t border-border bg-muted/20 rounded-b-lg">
                <x-ui.button type="button" @click="reportModal = false" variant="outline" size="sm">Отмена</x-ui.button>
                <x-ui.button type="submit" variant="destructive" size="sm" x-bind:disabled="loading">
                    <span x-show="!loading">Отправить</span>
                    <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                </x-ui.button>
            </div>
        </form>
    </div>
</div>