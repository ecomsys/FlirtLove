<div x-data="{
    isOpen: false,
    selectedPlanId: {{ $premiumPlans->first()->id ?? 'null' }},
    paymentMethod: 'card',
    autoRenew: false,
    loading: false,

    // Передаем данные о тарифах из PHP в Alpine
    plans: {{ $premiumPlans->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'price' => (float)$p->price, 'duration_days' => $p->duration_days])->values()->toJson() }},

    // Геттер для получения объекта выбранного тарифа
    get selectedPlan() {
        return this.plans.find(p => p.id == this.selectedPlanId) || null;
    },

    selectPlan(id) {
        this.selectedPlanId = id;
        // Если тариф короткий (менее 7 дней), принудительно снимаем галку
        const plan = this.plans.find(p => p.id == id);
        if (!plan || plan.duration_days < 7) {
            this.autoRenew = false;
        }
    },

    open() {
        this.isOpen = true;
        window.modalScrollManager.lock();
    },
    close() {
        this.isOpen = false;
        window.modalScrollManager.unlock();
    },

    async processPayment() {
            if (this.loading || !this.selectedPlanId) return;
            this.loading = true;

            // СОБИРАЕМ ДАННЫЕ В ОТДЕЛЬНЫЙ ОБЪЕКТ, ЧТОБЫ ПОСМОТРЕТЬ В КОНСОЛИ
            const payload = {
                plan_id: this.selectedPlanId,
                payment_method: this.paymentMethod,
                auto_renew: this.autoRenew,
                return_url: window.location.pathname + window.location.search
            };
            
            // ВЫВОДИМ В КОНСОЛЬ БРАУЗЕРА
            console.log('🚀 Отправляем данные на оплату Премиума:', payload);

            try {
                const res = await fetch('{{ route('premium.pay') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(payload) // Отправляем собранный объект
                });

                const data = await res.json();

            if (res.ok && data.success && data.redirect_url) {
                window.location.href = data.redirect_url;
            } else if (data.errors) {
                let errorMessages = Object.values(data.errors).flat().join(' ');
                this.$dispatch('show-toast', { type: 'error', message: errorMessages || 'Проверьте введенные данные.' });
                this.loading = false;
            } else {
                this.$dispatch('show-toast', { type: 'error', message: data.message || 'Ошибка инициализации платежа' });
                this.loading = false;
            }
        } catch (e) {
            console.error(e);
            this.$dispatch('show-toast', { type: 'error', message: 'Ошибка сети' });
            this.loading = false;
        }
    }
}" x-init="window.addEventListener('open-premium-modal', () => { open(); });" @keydown.escape.window="isOpen && close()" x-cloak>
    
    <!-- ОВЕРЛЕЙ -->
    <div x-show="isOpen" style="display: none;" class="fixed inset-0 z-[120] overflow-y-auto bg-black/45 backdrop-blur-sm"
        @click="close()" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        
        <div class="flex min-h-full items-start justify-center p-4 py-20">
            <!-- ТЕЛО МОДАЛКИ -->
            <div class="relative bg-card border border-border rounded-2xl shadow-2xl w-full max-w-xl my-0 flex flex-col"
                @click.stop x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                
                <!-- ШАПКА -->
                <div class="flex items-center justify-between p-4 border-b border-border">
                    <h3 class="text-base font-semibold text-foreground">Подключение Премиум</h3>
                    <button @click="close()" class="text-muted-foreground hover:text-foreground hover:bg-accent rounded-md p-1 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- КОНТЕНТ -->
                <div class="p-4 flex flex-col gap-3">
                    <div class="text-center">
                        <p class="text-sm font-medium text-primary">Получайте бонусы при подключении!</p>
                        <p class="text-xs text-muted-foreground mt-0.5">Чем длиннее период, тем больше скидка!</p>
                    </div>

                    <!-- СЕТКА ТАРИФОВ -->
                    <div class="grid grid-cols-2 gap-3">
                        @foreach($premiumPlans as $plan)
                            <div @click="selectPlan({{ $plan->id }})" :class="selectedPlanId === {{ $plan->id }} ? 'border-primary ring-2 ring-primary/20 bg-primary/5' : 'border-border hover:bg-muted/50'" class="cursor-pointer p-3 rounded-xl border-2 transition-all flex flex-col">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-bold text-sm text-foreground">{{ $plan->name }}</span>
                                    @if($plan->slug === 'premium-2-days') <span class="bg-red-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full">Акция</span> @endif
                                    @if($plan->slug === 'premium-3-months') <span class="bg-green-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full">Лучшая цена</span> @endif
                                </div>
                                <div class="flex items-baseline gap-1.5">
                                    @if($plan->old_price)
                                        <span class="text-yellow-500 line-through text-sm font-medium">{{ $plan->old_price }} ₽</span>
                                    @endif
                                    <span class="font-bold text-xl text-primary">{{ $plan->price }} ₽</span>
                                </div>
                                <span class="text-[11px] text-muted-foreground mt-1">Общая сумма: {{ $plan->price }} р.</span>
                            </div>
                        @endforeach
                    </div>

                    <!-- Выбор метода оплаты -->
                    <div class="flex gap-2 mt-1">
                        <button @click="paymentMethod = 'card'" :class="paymentMethod === 'card' ? 'border-primary text-primary' : 'border-border text-muted-foreground'" class="flex-1 flex items-center justify-center gap-2 py-2 border-2 rounded-lg text-sm font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" /></svg>
                            Картой
                        </button>
                        <button @click="paymentMethod = 'yoomoney'" :class="paymentMethod === 'yoomoney' ? 'border-primary text-primary' : 'border-border text-muted-foreground'" class="flex-1 flex items-center justify-center gap-2 py-2 border-2 rounded-lg text-sm font-medium transition-colors">
                            <span class="text-xs font-bold">ЮMoney</span>
                        </button>
                    </div>

                    <!-- Бейджи безопасности -->
                    <div class="flex items-center justify-center gap-4 text-[10px] text-muted-foreground">
                        <span class="flex items-center gap-1"><svg class="w-3 h-3 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd" /></svg> Защищенное соединение</span>
                        <span class="flex items-center gap-1"><svg class="w-3 h-3 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M2.5 4A1.5 1.5 0 001 5.5V6h18v-.5A1.5 1.5 0 0017.5 4h-15zM19 8.5H1V14.5A1.5 1.5 0 002.5 16h15a1.5 1.5 0 001.5-1.5V8.5z" clip-rule="evenodd" /></svg> Данные защищены</span>
                    </div>

                    <!-- АВТОПРОДЛЕНИЕ (Динамический текст + скрытие для коротких тарифов) -->
                    <div x-show="selectedPlan && selectedPlan.duration_days >= 7" x-cloak style="display: none;" x-transition class="p-2.5 bg-muted/30 rounded-lg border border-border">
                        <div class="flex items-start gap-2">
                            <x-checkbox x-model="autoRenew" id="autoRenew" size="sm" variant="primary" />
                            <label for="autoRenew" class="text-[0.65rem] text-muted-foreground leading-relaxed cursor-pointer">
                                <!-- Динамически подставляем цену и период -->
                                <span x-html="'Полная стоимость сервиса со скидкой <strong class=\'text-foreground\'>' + (selectedPlan?.price || 0) + ' рублей</strong> за ' + (selectedPlan?.name || '') + ', в случае вашего бездействия платеж за использование сервиса ' + (selectedPlan?.price || 0) + ' рублей будет списываться автоматически до момента отписки держателем карты, раз в ' + (selectedPlan?.name || '') + '.'"></span>
                            </label>
                        </div>
                    </div>

                    <p class="text-center text-[11px] text-muted-foreground">
                        Оплачивая сервис, вы соглашаетесь с <a href="#" class="text-primary hover:underline">правилами использования</a>.
                    </p>
                </div>

                <!-- ФУТЕР -->
                <div class="p-4 border-t border-border bg-muted/20 rounded-b-2xl flex justify-center">
                    <x-ui.button @click="processPayment()" x-bind:disabled="loading"
                        class="max-w-[14rem] w-full bg-gradient-to-r from-orange-500 to-amber-500 text-white hover:from-orange-600 hover:to-amber-600">
                        <span x-show="!loading">Оплатить <span x-text="selectedPlan?.price + ' ₽'"></span></span>
                        <svg x-show="loading" class="w-5 h-5 animate-spin mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    </x-ui.button>
                </div>
            </div>
        </div>
    </div>
</div>