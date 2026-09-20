<x-layouts.inapp-page>
    <div class="min-h-[calc(100dvh-4rem)] flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-card border border-border rounded-2xl shadow-2xl overflow-hidden flex flex-col"
             x-data="{
                status: 'pending',
                planName: '',
                endsAt: '',
                failReason: '',
                returnUrl: '{{ $returnUrl }}',
                tier: '{{ $tier }}',
                init() {
                    const interval = setInterval(async () => {
                        const res = await fetch('{{ route('subscription.status', $transactionId) }}');
                        const data = await res.json();
                        this.status = data.status;
                        this.planName = data.planName;
                        this.endsAt = data.endsAt;
                        this.failReason = data.fail_reason;

                        if (this.status !== 'pending') {
                            clearInterval(interval);
                        }
                    }, 2000);
                }
             }"
             x-init="init()"
        >
            
            <!-- ЭКРАН ЗАГРУЗКИ (Pending) -->
            <template x-if="status === 'pending'">
                <div class="p-12 text-center flex flex-col items-center gap-6">
                    <svg class="w-16 h-16 text-primary animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <div class="space-y-2">
                        <h1 class="text-xl font-bold text-foreground">Оплата обрабатывается банком</h1>
                        <p class="text-muted-foreground text-sm">Пожалуйста, подождите. Это может занять несколько секунд.</p>
                    </div>
                </div>
            </template>

            <!-- ЭКРАН УСПЕХА (Success) -->
            <template x-if="status === 'success'">
                <div>
                    <div class="p-8 text-center flex flex-col items-center gap-4 @if($tier === 'vip') bg-gradient-to-b from-blue-500/10 to-transparent @else bg-gradient-to-b from-orange-500/10 to-transparent @endif">
                        <div class="relative w-20 h-20 flex items-center justify-center">
                            <div class="absolute inset-0 rounded-full @if($tier === 'vip') bg-blue-500/20 @else bg-orange-500/20 @endif animate-ping"></div>
                            <div class="relative w-20 h-20 rounded-full flex items-center justify-center @if($tier === 'vip') bg-blue-500/10 dark:bg-blue-900/30 @else bg-orange-500/10 dark:bg-orange-900/30 @endif">
                                <svg class="w-12 h-12 @if($tier === 'vip') text-blue-500 @else text-orange-500 @endif" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <h1 class="text-4xl font-extrabold tracking-tight @if($tier === 'vip') text-transparent bg-clip-text bg-gradient-to-r from-blue-500 to-indigo-500 @else text-transparent bg-clip-text bg-gradient-to-r from-orange-500 to-amber-500 @endif uppercase" x-text="tier"></h1>
                            <p class="text-muted-foreground font-medium text-lg">Оплата прошла успешно!</p>
                        </div>
                    </div>
                    <div class="p-6 pt-4 flex flex-col gap-6 items-center text-center">
                        <p class="text-foreground/90">
                            Вы успешно подключили тариф <strong x-text="planName"></strong>.<br>
                            <span x-show="endsAt">Действует до: <span class="font-bold text-foreground" x-text="endsAt"></span></span>
                        </p>
                        <div class="w-full space-y-3">
                            <a :href="returnUrl" class="w-full flex items-center justify-center gap-2 py-3 px-6 font-semibold rounded-lg shadow-lg transition-all text-white @if($tier === 'vip') bg-gradient-to-r from-blue-500 to-indigo-500 @else bg-gradient-to-r from-orange-500 to-amber-500 @endif">
                                Продолжить
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                            </a>
                            <a href="{{ route('home') }}" class="w-full flex items-center justify-center gap-2 py-3 px-6 font-medium rounded-lg border border-border text-muted-foreground hover:bg-muted/50 hover:text-foreground transition-colors">
                                На главную
                            </a>
                        </div>
                    </div>
                </div>
            </template>

            <!-- ЭКРАН ОШИБКИ (Failed) -->
            <template x-if="status === 'failed'">
                <div class="p-12 text-center flex flex-col items-center gap-6">
                    <div class="w-20 h-20 rounded-full bg-destructive/10 flex items-center justify-center">
                        <svg class="w-12 h-12 text-destructive" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                    </div>
                    <div class="space-y-2">
                        <h1 class="text-xl font-bold text-foreground">Ошибка оплаты</h1>
                        <p class="text-muted-foreground text-sm" x-text="failReason || 'Платеж не прошел. Попробуйте снова.'"></p>
                    </div>
                    <a :href="returnUrl" class="w-full max-w-xs py-3 px-6 font-semibold rounded-lg border border-border text-foreground hover:bg-muted/50 transition-colors">
                        Попробовать снова
                    </a>
                </div>
            </template>

        </div>
    </div>
</x-layouts.inapp-page>