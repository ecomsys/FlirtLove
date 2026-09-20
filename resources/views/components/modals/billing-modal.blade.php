<div x-data="{
    isOpen: false,
    selectedPlan: 250,
    paymentMethod: 'card',
    autoTopUp: false,
    loading: false,

    // НОВЫЙ МЕТОД: Выбор тарифа            
    selectPlan(amount) {
        this.selectedPlan = amount;
        // Если выбрали 80 руб, принудительно снимаем галку автопополнения
        if (amount === 80) {
            this.autoTopUp = false;
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
            if (this.loading) return;
            this.loading = true;

            // СОБИРАЕМ ДАННЫЕ
            const payload = {
                amount: this.selectedPlan,
                payment_method: this.paymentMethod,
                auto_top_up: this.autoTopUp,
                return_url: window.location.pathname + window.location.search
            };
            
            // ВЫВОДИМ В КОНСОЛЬ
            console.log('🚀 Отправляем данные на покупку кредитов:', payload);

            try {
                const res = await fetch('{{ route('billing.pay') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();

            // Если запрос прошел и есть ссылка -> редирект
            if (res.ok && data.success && data.redirect_url) {
                window.location.href = data.redirect_url;
            }
            // Если есть ошибки валидации (422)
            else if (data.errors) {
                let errorMessages = Object.values(data.errors).flat().join(' ');
                this.$dispatch('show-toast', { type: 'error', message: errorMessages || 'Проверьте введенные данные.' });
                this.loading = false;
            }
            // Если сервер вернул общую ошибку
            else {
                this.$dispatch('show-toast', { type: 'error', message: data.message || 'Ошибка инициализации платежа' });
                this.loading = false;
            }
        } catch (e) {
            console.error(e);
            this.$dispatch('show-toast', { type: 'error', message: 'Ошибка сети' });
            this.loading = false;
        }
    }
}" @open-billing-modal.window="open()" @keydown.escape.window="isOpen && close()" x-cloak>
    <!-- ОВЕРЛЕЙ -->
    <div x-show="isOpen" style="display: none;" class="fixed inset-0 z-[120] overflow-y-auto bg-black/45 backdrop-blur-sm"
        @click="close()" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <!-- ВНУТРЕННИЙ КОНТЕЙНЕР -->
        <div class="flex min-h-full items-start justify-center p-4 py-20">

            <!-- ТЕЛО МОДАЛКИ -->
            <div class="relative bg-card border border-border rounded-2xl shadow-2xl w-full max-w-lg my-0 flex flex-col"
                @click.stop x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                <!-- ШАПКА -->
                <div class="flex items-center justify-between p-4 border-b border-border">
                    <h3 class="text-base font-semibold text-foreground">Пополнение счета</h3>
                    <button @click="close()"
                        class="text-muted-foreground hover:text-foreground hover:bg-accent rounded-md p-1 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- КОНТЕНТ -->
                <div class="p-5 flex flex-col gap-4">

                    <!-- Блок бонусов -->
                    <div class="text-center">
                        <p class="text-sm font-medium text-primary">Получайте уникальные бонусы!</p>
                        <p class="text-xs text-muted-foreground mt-1">Чем больше сумма пополнения, тем больше скидка!
                        </p>
                    </div>

                    <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider text-center">Выберите
                        подходящий вариант:</p>

                    <!-- Сетка тарифов (Заменили @click на вызов метода) -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <!-- 80 руб -->
                        <div @click="selectPlan(80)"
                            :class="selectedPlan === 80 ? 'border-primary ring-2 ring-primary/20 bg-primary/5' :
                                'border-border hover:bg-muted/50'"
                            class="cursor-pointer p-3 rounded-xl border-2 text-center transition-all flex flex-col items-center justify-center">
                            <p class="font-bold text-base text-foreground">80 ед.</p>
                            <p class="text-xs text-muted-foreground mt-1">80 рублей</p>
                        </div>

                        <!-- 250 руб -->
                        <div @click="selectPlan(250)"
                            :class="selectedPlan === 250 ? 'border-primary ring-2 ring-primary/20 bg-primary/5' :
                                'border-border hover:bg-muted/50'"
                            class="cursor-pointer p-3 rounded-xl border-2 text-center transition-all relative flex flex-col items-center justify-center">
                            <span
                                class="absolute -top-2 right-1 bg-green-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full whitespace-nowrap">+50</span>
                            <p class="font-bold text-base text-foreground">300 ед.</p>
                            <p class="text-xs text-muted-foreground mt-1">250 рублей</p>
                        </div>

                        <!-- 500 руб -->
                        <div @click="selectPlan(500)"
                            :class="selectedPlan === 500 ? 'border-primary ring-2 ring-primary/20 bg-primary/5' :
                                'border-border hover:bg-muted/50'"
                            class="cursor-pointer p-3 rounded-xl border-2 text-center transition-all relative flex flex-col items-center justify-center">
                            <span
                                class="absolute -top-2 right-1 bg-green-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full whitespace-nowrap">+150</span>
                            <p class="font-bold text-base text-foreground">650 ед.</p>
                            <p class="text-xs text-muted-foreground mt-1">500 рублей</p>
                        </div>

                        <!-- 900 руб -->
                        <div @click="selectPlan(900)"
                            :class="selectedPlan === 900 ? 'border-primary ring-2 ring-primary/20 bg-primary/5' :
                                'border-border hover:bg-muted/50'"
                            class="cursor-pointer p-3 rounded-xl border-2 text-center transition-all relative flex flex-col items-center justify-center">
                            <span
                                class="absolute -top-2 right-1 bg-green-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full whitespace-nowrap">+350</span>
                            <p class="font-bold text-base text-foreground">1250 ед.</p>
                            <p class="text-xs text-muted-foreground mt-1">900 рублей</p>
                        </div>
                    </div>

                    <!-- Выбор метода оплаты -->
                    <div class="flex gap-2 mt-1">
                        <button @click="paymentMethod = 'card'"
                            :class="paymentMethod === 'card' ? 'border-primary text-primary' :
                                'border-border text-muted-foreground'"
                            class="flex-1 flex items-center justify-center gap-2 py-2.5 border-2 rounded-lg text-sm font-medium transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                            </svg>
                            Картой
                        </button>
                        <button @click="paymentMethod = 'yoomoney'"
                            :class="paymentMethod === 'yoomoney' ? 'border-primary text-primary' :
                                'border-border text-muted-foreground'"
                            class="flex-1 flex items-center justify-center gap-2 py-2.5 border-2 rounded-lg text-sm font-medium transition-colors">
                            <span class="text-xs font-bold">ЮMoney</span>
                        </button>
                    </div>

                    <!-- Бейджи безопасности -->
                    <div class="flex items-center justify-center gap-4 text-[10px] text-muted-foreground">
                        <span class="flex items-center gap-1"><svg class="w-3 h-3 text-green-500" fill="currentColor"
                                viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z"
                                    clip-rule="evenodd" />
                            </svg> Защищенное соединение</span>
                        <span class="flex items-center gap-1"><svg class="w-3 h-3 text-green-500" fill="currentColor"
                                viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M2.5 4A1.5 1.5 0 001 5.5V6h18v-.5A1.5 1.5 0 0017.5 4h-15zM19 8.5H1V14.5A1.5 1.5 0 002.5 16h15a1.5 1.5 0 001.5-1.5V8.5z"
                                    clip-rule="evenodd" />
                            </svg> Данные защищены</span>
                    </div>

                    <!-- Автопополнение (Скрываем, если выбран тариф 80) -->
                    <div x-show="selectedPlan !== 80" x-cloak style="display: none;" x-transition
                        class="p-3 bg-muted/30 rounded-lg border border-border">
                        <div class="flex items-start gap-2">
                            <x-checkbox x-model="autoTopUp" id="autoTopUp" size="sm" variant="primary" />
                            <label for="autoTopUp"
                                class="text-[0.65rem] text-muted-foreground leading-relaxed cursor-pointer">
                                <span class="font-semibold text-foreground">Автопополнение баланса:</span> при остатке
                                менее 200 Единиц с привязанной карты автоматически списывается 200 ₽ — на баланс
                                начисляется 250 Единиц. Продолжая с галочкой, вы даёте согласие на автоматическое
                                списание средств. Отозвать согласие можно в настройках аккаунта.
                            </label>
                        </div>
                    </div>

                    <!-- Ссылка на правила -->
                    <p class="text-center text-[11px] text-muted-foreground">
                        Оплачивая сервис, вы соглашаетесь с <a href="#"
                            class="text-primary hover:underline">правилами использования</a>.
                    </p>
                </div>

                <!-- ФУТЕР -->
                <div class="p-4 border-t border-border bg-muted/20 rounded-b-2xl flex justify-center">
                    <x-ui.button @click="processPayment()" x-bind:disabled="loading"
                        class="max-w-[12rem] w-full bg-gradient-to-r from-primary to-primary/80 text-primary-foreground hover:from-primary/90">
                        <span x-show="!loading">Оплатить <span x-text="selectedPlan"></span> ₽</span>
                        <svg x-show="loading" class="w-5 h-5 animate-spin mx-auto" fill="none"
                            viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </x-ui.button>
                </div>
            </div>
        </div>
    </div>
</div>
