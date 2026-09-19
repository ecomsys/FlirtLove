<div 
    x-data="{ 
        modalOpen: false,
        email: '',
        captchaImage: '',
        captchaInput: '',
        loading: false,
        errors: {},
        emailSent: false,
        emailProviderUrl: '#',
        
        openModal(email = '') {
            this.email = email || '';
            this.captchaInput = '';
            this.errors = {};
            this.emailSent = false;
            this.modalOpen = true;
            this.getCaptcha();
        },
        
        async getCaptcha() {
            try {
                const res = await fetch('/ajax/captcha/forgot', {
                    headers: { 
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await res.json();
                this.captchaImage = data.image;
            } catch(e) {
                console.error('Ошибка загрузки капчи', e);
            }
        },
        
        getEmailProviderUrl() {
            if (!this.email) return '#';
            let domain = this.email.split('@')[1];
            if (!domain) return '#';

            const providers = {
                'gmail.com': 'https://mail.google.com',
                'googlemail.com': 'https://mail.google.com',
                'mail.ru': 'https://e.mail.ru/inbox',
                'inbox.ru': 'https://e.mail.ru/inbox',
                'list.ru': 'https://e.mail.ru/inbox',
                'bk.ru': 'https://e.mail.ru/inbox',
                'yandex.ru': 'https://mail.yandex.ru',
                'yandex.by': 'https://mail.yandex.ru',
                'ya.ru': 'https://mail.yandex.ru',
                'outlook.com': 'https://outlook.live.com/mail/0/inbox',
                'hotmail.com': 'https://outlook.live.com/mail/0/inbox',
                'live.com': 'https://outlook.live.com/mail/0/inbox',
                'icloud.com': 'https://www.icloud.com/mail',
                'rambler.ru': 'https://mail.rambler.ru/',
            };

            return providers[domain] || 'https://' + domain;
        },
        
        async submit() {
            if (this.loading) return;
            this.loading = true;
            this.errors = {};
            
            try {
                const res = await fetch('/ajax/forgot-password', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        email: this.email,
                        captchaInput: this.captchaInput
                    })
                });
                
                const data = await res.json().catch(() => null);
                
                if (res.ok && data && data.success) {
                    this.emailSent = true;
                    this.emailProviderUrl = this.getEmailProviderUrl();
                } else if (data && data.errors) {
                    this.errors = data.errors;
                    if (this.errors.email || this.errors.captchaInput) {
                        this.getCaptcha();
                        this.captchaInput = '';
                    }
                } else {
                    this.errors = { email: ['Ошибка сервера. Обновите страницу.'] };
                }
            } catch(e) {
                console.error('Сетевая ошибка', e);
                this.errors = { email: ['Не удалось связаться с сервером.'] };
            } finally {
                this.loading = false;
            }
        }
    }"
    @open-forgot-password-modal.window="openModal($event.detail ? $event.detail.email : '')"
    @keydown.escape.window="modalOpen = false"
>
    
    <!-- Затемненный фон -->
    <div x-show="modalOpen" x-cloak 
         @click.self="modalOpen = false" 
         class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm scrollbar-hidden flex justify-center py-8 px-4"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
         
        <!-- Сама модалка -->
        <div class="bg-card border border-border rounded-lg shadow-2xl max-w-2xl w-full mx-auto my-auto"
             x-show="modalOpen" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95">
            
            <!-- ЭКРАН 1: Письмо отправлено -->
            <template x-if="emailSent">
                <div class="p-6 space-y-4 text-center">
                    <div class="flex justify-end">
                        <x-ui.button variant="ghost" size="icon-sm" @click="modalOpen = false">
                            <x-lucide-x class="w-5 h-5" />
                        </x-ui.button>
                    </div>
                    <x-lucide-mail-check class="w-12 h-12 mx-auto text-green-500" />
                    <h4 class="text-lg font-semibold">Письмо отправлено!</h4>
                    <p class="text-sm text-muted-foreground">Мы отправили ссылку для сброса пароля на email: <strong x-text="email"></strong>. Проверьте вашу почту.</p>
                    
                    <div class="space-y-2 pt-2">
                        <x-ui.button as-child class="w-full">
                            <a :href="emailProviderUrl" target="_blank" class="flex items-center justify-center gap-2">
                                Перейти в почту
                            </a>
                        </x-ui.button>
                        <x-ui.button variant="outline" @click="modalOpen = false; window.dispatchEvent(new CustomEvent('open-login-modal'))" class="w-full">
                            Вернуться ко входу
                        </x-ui.button>
                    </div>
                </div>
            </template>

            <!-- ЭКРАН 2: Форма восстановления -->
            <template x-if="!emailSent">
                <div>
                    <div class="flex items-center justify-between p-4 border-b border-border">
                        <h3 class="text-xl font-semibold flex items-center gap-3">                
                            Восстановление пароля
                            <x-lucide-key class="w-5 h-5 text-yellow-500" />
                        </h3>
                        <x-ui.button variant="ghost" size="icon-sm" @click="modalOpen = false">
                            <x-lucide-x class="w-5 h-5" />
                        </x-ui.button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2">
                        <div class="p-6 space-y-4">
                            <div class="space-y-1">
                                <p class="font-medium text-foreground text-sm">Восстановить по почте</p>
                                <p class="text-xs text-muted-foreground">На ваш почтовый ящик придет информация о вашем аккаунте.</p>
                            </div>
                            
                            <form @submit.prevent="submit()" class="space-y-4">
                                <div class="space-y-1.5">
                                    <x-ui.label for="forgot-email">Ваш Email</x-ui.label>
                                    <x-ui.input id="forgot-email" type="email" autofocus autocomplete="email" placeholder="Введите ваш email" x-model="email" />
                                    <p x-show="errors.email" x-text="errors.email?.[0] || ''" class="text-xs text-destructive mt-1"></p>
                                </div>

                                <!-- Капча -->
                                <div class="space-y-1.5 pt-2">
                                    <x-ui.label>Символы на картинке</x-ui.label>
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-2">
                                            <img :src="captchaImage" alt="Captcha" class="flex-1 h-10 rounded border border-border bg-[#dcdee4] object-contain">
                                            <button type="button" class="shrink-0 h-10 w-10 flex items-center justify-center" @click="getCaptcha(); captchaInput = ''" title="Обновить картинку">
                                                <x-lucide-refresh-cw class="w-6 h-6" x-show="!loading" />
                                                <x-lucide-loader-2 class="w-6 h-6 animate-spin" x-show="loading" x-cloak style="display: none;" />
                                            </button>
                                        </div>
                                        <x-ui.input x-model="captchaInput" placeholder="Введите код" class="w-full" />
                                    </div>
                                    <p x-show="errors.captchaInput" x-text="errors.captchaInput?.[0] || ''" class="text-xs text-destructive mt-1"></p>
                                </div>

                                <x-ui.button type="submit" x-bind:disabled="loading" class="w-full">
                                    <span x-show="!loading">Восстановить</span>
                                    <span x-show="loading" x-cloak style="display: none;" class="flex items-center gap-2 justify-center">
                                        <x-lucide-loader-2 class="w-4 h-4 animate-spin" /> Отправка...
                                    </span>
                                </x-ui.button>
                            </form>

                            <div class="text-center">
                                <button type="button" @click="modalOpen = false; window.dispatchEvent(new CustomEvent('open-login-modal', { detail: { email: email } }))" class="text-xs text-primary hover:underline">
                                    Я вспомнил пароль. Вернуться ко входу
                                </button>
                            </div>
                        </div>

                        <!-- ПРАВАЯ КОЛОНКА: Плашка регистрации -->
                        <div class="bg-muted/30 border-t border-border md:border-t-0 md:border-l p-6 text-center space-y-2 flex flex-col">
                            <h4 class="font-semibold text-lg">У вас ещё нет анкеты?</h4>
                            <p class="text-sm text-muted-foreground">Вы найдете множество интересных людей из вашего города. Начните общаться прямо сейчас.</p>
                            <p class="text-xs text-muted-foreground pb-2">Регистрация займет меньше минуты.</p>
                            
                            <x-ui.button as-child @click="modalOpen = false">
                                <a href="{{ route('register') }}">Регистрация</a>
                            </x-ui.button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>