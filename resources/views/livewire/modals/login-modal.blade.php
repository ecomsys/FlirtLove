<div x-data="{ modalOpen: new URLSearchParams(window.location.search).has('login') }" 
     @open-login-modal.window="modalOpen = true"
     @open-login-modal-with-creds.window="modalOpen = true">
    
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
        <div class="bg-card border border-border rounded-lg shadow-2xl max-w-2xl w-full my-auto"
             x-show="modalOpen" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             @keydown.escape.window="modalOpen = false">
            
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
                    <form wire:submit="login" class="space-y-4">
                        
                        <!-- Email -->
                        <div class="space-y-1.5">
                            <x-ui.label for="login-email">Ваш Email</x-ui.label>
                            <x-ui.input id="login-email" wire:model="email" type="email" autocomplete="username" placeholder="example@gmail.com" />
                            @error('email') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                        </div>

                        <!-- Пароль (добавили кнопку показа пароля) -->
                        <div class="space-y-1.5">
                            <x-ui.label for="login-password">Пароль</x-ui.label>                        
                            <div class="relative">
                                <x-ui.input id="login-password" wire:model="password" type="password" autocomplete="current-password" placeholder="Введите пароль" />                                
                            </div>                                                   
                            @error('password') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                        </div>

                        <!-- Строка: Напомнить пароль и Чужой компьютер -->
                        <div class="flex items-center justify-between pt-1">
                            <button type="button" @click="modalOpen = false; Livewire.dispatch('open-forgot-password-modal', { email: $wire.email })" class="text-xs text-primary hover:underline">
                                Напомнить пароль
                            </button>
                            <x-ui.label class="flex items-center gap-2 text-xs font-normal cursor-pointer">
                                <x-checkbox wire:model="remember" size="sm"/> Чужой компьютер
                            </x-ui.label>
                        </div>

                        <!-- Капча -->
                        <div class="space-y-1.5 pt-2">
                            <x-ui.label>Символы на картинке</x-ui.label>
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <img src="{{ $captchaImage }}" alt="Captcha" class="flex-1 h-10 rounded border border-border bg-[#dcdee4] object-contain">
                                    <button type="button" class="shrink-0 h-10 w-10 flex items-center justify-center" wire:click="refreshCaptcha" wire:loading.attr="disabled" wire:target="refreshCaptcha" title="Обновить картинку">
                                        <x-lucide-refresh-cw class="w-6 h-6" wire:loading.remove.delay wire:target="refreshCaptcha" />
                                        <x-lucide-loader-2 class="w-6 h-6 animate-spin inline" wire:loading.delay wire:target="refreshCaptcha" />
                                    </button>
                                </div>
                                <x-ui.input wire:model="captchaInput" placeholder="Введите код" class="w-full" />
                            </div>
                            @error('captchaInput') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                        </div>

                        <!-- Кнопка Войти -->
                        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="login" class="w-full">
                            <span wire:loading.remove.delay wire:target="login">Войти</span>
                            <span wire:loading.delay wire:target="login" class="flex items-center gap-2">
                                <x-lucide-loader-2 class="w-4 h-4 animate-spin inline" /> Вход...
                            </span>
                        </x-ui.button>

                        <!-- Соглашение -->
                        <p class="text-[10px] text-muted-foreground text-center leading-relaxed pt-1">
                            Нажимая кнопку "Войти", вы принимаете условия пользовательского соглашения и даете согласие на обработку персональных данных.
                        </p>
                    </form>
                </div>

                <!-- ПРАВАЯ КОЛОНКА: Плашка регистрации -->              
                <div class="bg-muted/30 border-t border-border md:border-t-0 md:border-l p-6 text-center space-y-2 flex flex-col">
                    <h4 class="font-semibold text-lg">У вас ещё нет анкеты?</h4>
                    <p class="text-sm text-muted-foreground">Вы найдете множество интересных людей из вашего города. Начните общаться прямо сейчас.</p>
                    <p class="text-xs text-muted-foreground pb-2">Регистрация займет меньше минуты.</p>
                    
                    <!-- Добавляем @click="modalOpen = false" для мгновенного закрытия -->
                    <x-ui.button as="a" href="{{ route('register') }}" wire:navigate @click="modalOpen = false" class="w-full">
                        Регистрация
                    </x-ui.button>
                </div>
            </div>
        </div>
    </div>
</div>