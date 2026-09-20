<div x-data="{    
    modalOpen: false,
    email: '',
    password: '',
    remember: false,
    captchaImage: '',
    captchaInput: '',
    loading: false,
    errors: {},

    openModal() {
        console.log('Модалка логина поймала событие! Открываюсь...');
        this.resetForm();
        this.modalOpen = true;
        this.getCaptcha();
    },

    resetForm() {
        this.email = '';
        this.password = '';
        this.captchaInput = '';
        this.remember = false;
        this.errors = {};
    },
    
    init() {
            if (new URLSearchParams(window.location.search).has('login')) {
                this.modalOpen = true;
                this.getCaptcha();
            }
        },

    async getCaptcha() {
        try {
            const res = await fetch('/ajax/captcha/login', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest' // <--- ФИКС ДЛЯ LARAVEL
                }
            });
            const data = await res.json();
            this.captchaImage = data.image;
        } catch (e) {
            console.error('Ошибка загрузки капчи', e);
        }
    },

    async submit() {
        if (this.loading) return;
        this.loading = true;
        this.errors = {};

        try {
            const res = await fetch('/ajax/login', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest' // <--- ФИКС ДЛЯ LARAVEL
                },
                body: JSON.stringify({
                    email: this.email,
                    password: this.password,
                    captchaInput: this.captchaInput,
                    remember: this.remember
                })
            });

            // Пытаемся распарсить JSON. Если пришел HTML (404/500) - поймаем ошибку
            const data = await res.json().catch(() => null);

            if (res.ok && data && data.success) {
                // Успешный логин -> редирект
                window.location.href = data.redirect;
            } else if (data && data.errors) {
                // Ошибки валидации (422)
                this.errors = data.errors;
                // Если ошибка связана с email или капчей, обновляем капчу
                if (this.errors.email || this.errors.captchaInput) {
                    this.getCaptcha();
                    this.captchaInput = '';
                }
            } else {
                // Сервер вернул HTML (например, страница 404 или истек CSRF)
                this.errors = { email: ['Ошибка сервера. Обновите страницу и попробуйте снова.'] };
            }
        } catch (e) {
            console.error('Сетевая ошибка', e);
            this.errors = { email: ['Не удалось связаться с сервером.'] };
        } finally {
            this.loading = false;
        }
    }
}" @open-login-modal.window="openModal()" @keydown.escape.window="modalOpen = false">

    <!-- Затемненный фон -->
    <div x-show="modalOpen" x-cloak @click.self="modalOpen = false"
        class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm scrollbar-hidden flex justify-center py-8 px-4"
        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">

        <!-- Сама модалка -->
        <div class="bg-card border border-border rounded-lg shadow-2xl max-w-2xl w-full my-auto" x-show="modalOpen"
            x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">

            <!-- Шапка -->
            <div class="p-5 space-y-2 border-b border-border">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-xl font-semibold">Вход на сайт</h3>
                        <p class="text-xs text-muted-foreground mt-1">Проверьте пароль и введите код подтверждения.</p>
                    </div>
                    <x-ui.button variant="ghost" size="icon-sm" @click="modalOpen = false">
                        <x-lucide-x class="w-5 h-5" />
                    </x-ui.button>
                </div>
            </div>

            <!-- Сетка: 2 колонки на десктопе, 1 на мобилке -->
            <div class="grid grid-cols-1 md:grid-cols-2">

                <!-- ЛЕВАЯ КОЛОНКА: Форма входа -->
                <div class="p-6 space-y-6">
                    <form @submit.prevent="submit()" class="space-y-4">

                        <!-- Email -->
                        <div class="space-y-1.5">
                            <x-ui.label for="login-email">Ваш Email</x-ui.label>
                            <x-ui.input id="login-email" type="email" autocomplete="username"
                                placeholder="example@gmail.com" x-model="email" />
                            <p x-show="errors.email" x-text="errors.email?.[0] || ''"
                                class="text-xs text-destructive mt-1"></p>

                            </p>
                        </div>

                        <!-- Пароль -->
                        <div class="space-y-1.5">
                            <x-ui.label for="login-password">Пароль</x-ui.label>
                            <div class="relative">
                                <x-ui.input id="login-password" type="password" autocomplete="current-password"
                                    placeholder="Введите пароль" x-model="password" />
                            </div>
                            <p x-show="errors.password" x-text="errors.password?.[0] || ''"
                                class="text-xs text-destructive mt-1"></p>

                        </div>

                        <!-- Строка: Напомнить пароль и Чужой компьютер -->
                        <div class="flex items-center justify-between pt-1">
                            <!-- Заменено на чистый Alpine dispatch -->
                            <button type="button"
                                @click="modalOpen = false; window.dispatchEvent(new CustomEvent('open-forgot-password-modal', { detail: { email: email } }))"
                                class="text-xs text-primary hover:underline">
                                Напомнить пароль
                            </button>
                            <x-ui.label class="flex items-center gap-2 text-xs font-normal cursor-pointer">
                                <x-checkbox x-model="remember" size="sm" /> Чужой компьютер
                            </x-ui.label>
                        </div>

                        <!-- Капча -->
                            <div class="space-y-1.5 pt-2">
                                <x-ui.label>Символы на картинке</x-ui.label>
                                <div class="space-y-2">
                                    <div class="flex items-center gap-2">
                                        <!-- Обернули картинку в контейнер с серым фоном -->
                                        <div class="relative flex-1 h-10 rounded border border-border bg-[#dcdee4] overflow-hidden flex items-center justify-center">
                                            <!-- Спиннер (показывается, пока captchaImage пустая) -->
                                            <svg x-show="!captchaImage" class="w-5 h-5 text-muted-foreground/50 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                            
                                            <!-- Сама картинка (показывается только когда ссылка загрузилась) -->
                                            <img x-show="captchaImage" :src="captchaImage" alt="Captcha" class="absolute inset-0 w-full h-full object-contain">
                                        </div>
                                        
                                        <button type="button" class="shrink-0 h-10 w-10 flex items-center justify-center" @click="getCaptcha(); captchaInput = ''" title="Обновить картинку">
                                            <x-lucide-refresh-cw class="w-6 h-6" x-show="!loading" />
                                            <x-lucide-loader-2 class="w-6 h-6 animate-spin" x-show="loading" x-cloak style="display: none;" />
                                        </button>
                                    </div>
                                    <x-ui.input x-model="captchaInput" placeholder="Введите код" class="w-full" />
                                </div>
                                <p x-show="errors.captchaInput" x-text="errors.captchaInput?.[0] || ''" class="text-xs text-destructive mt-1"></p>
                            </div>
                        <!-- Кнопка Войти -->
                        <x-ui.button type="submit" x-bind:disabled="loading" class="w-full">
                            <span x-show="!loading">Войти</span>
                            <span x-show="loading" x-cloak style="display: none;"
                                class="flex items-center gap-2 justify-center">
                                <x-lucide-loader-2 class="w-4 h-4 animate-spin" /> Вход...
                            </span>
                        </x-ui.button>

                        <!-- Соглашение -->
                        <p class="text-[10px] text-muted-foreground text-center leading-relaxed pt-1">
                            Нажимая кнопку "Войти", вы принимаете условия пользовательского соглашения и даете согласие
                            на обработку персональных данных.
                        </p>
                    </form>
                </div>

                <!-- ПРАВАЯ КОЛОНКА: Плашка регистрации -->
                <div
                    class="bg-muted/30 border-t border-border md:border-t-0 md:border-l p-6 text-center space-y-2 flex flex-col">
                    <h4 class="font-semibold text-lg">У вас ещё нет анкеты?</h4>
                    <p class="text-sm text-muted-foreground">Вы найдете множество интересных людей из вашего города.
                        Начните общаться прямо сейчас.</p>
                    <p class="text-xs text-muted-foreground pb-2">Регистрация займет меньше минуты.</p>

                    <x-ui.button as-child @click="modalOpen = false">
                        <a href="{{ route('register') }}">Регистрация</a>
                    </x-ui.button>
                </div>
            </div>
        </div>
    </div>
</div>
