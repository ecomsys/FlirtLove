<x-layouts.web>
    <div class="min-h-[calc(100dvh-4rem)] flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-card border border-border rounded-2xl shadow-xl p-8 text-center flex flex-col items-center gap-6">
            
            <!-- Анимированная зеленая галочка -->
            <div class="w-20 h-20 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                <svg class="w-12 h-12 text-green-500 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
            </div>

            <div class="space-y-2">
                <h1 class="text-2xl font-bold text-foreground">Платеж успешно завершен!</h1>
                <p class="text-muted-foreground">
                    Ваш баланс пополнен на <br>
                    <span class="text-xl font-bold text-primary">{{ $credits }} ед.</span>
                </p>
            </div>

            <!-- Кнопка возврата -->
            <x-ui.button as-child class="w-full" size="lg">
                <a href="{{ route('home') }}" class="flex items-center justify-center gap-2">
                    Продолжить знакомства
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                </a>
            </x-ui.button>
        </div>
    </div>
</x-layouts.web>