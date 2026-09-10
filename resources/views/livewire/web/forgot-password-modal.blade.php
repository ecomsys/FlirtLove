<div x-data="{ modalOpen: @entangle('showModal') }">
    
    <!-- Затемненный фон (Flex-center + Скролл без ползунка) -->
    <div x-show="modalOpen" x-cloak 
         @click.self="modalOpen = false" 
         class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm scrollbar-hidden flex justify-center py-8 px-4"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
         
        <!-- Сама модалка (my-auto центрирует её, а при нехватке места отступ схлопывается) -->
        <div class="bg-card border border-border rounded-lg shadow-2xl max-w-2xl w-full mx-auto my-auto"
             x-show="modalOpen" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             @keydown.escape.window="modalOpen = false">
            
            @if ($emailSent)
                <!-- Экран: Письмо отправлено -->
                <div class="p-6 space-y-4 text-center">
                    <div class="flex justify-end">
                        <x-ui.button variant="ghost" size="icon-sm" @click="modalOpen = false">
                            <x-lucide-x class="w-5 h-5" />
                        </x-ui.button>
                    </div>
                    <x-lucide-mail-check class="w-12 h-12 mx-auto text-green-500" />
                    <h4 class="text-lg font-semibold">Письмо отправлено!</h4>
                    <p class="text-sm text-muted-foreground">Мы отправили ссылку для сброса пароля на email: <strong>{{ $email }}</strong>. Проверьте вашу почту.</p>
                    <x-ui.button @click="modalOpen = false; $dispatch('open-login-modal')" class="w-full">
                        Вернуться ко входу
                    </x-ui.button>
                </div>
            @else
                <!-- Экран: Форма восстановления -->
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
                    

                    <form wire:submit="sendResetLink" class="space-y-4">
                        
                        <div class="space-y-1.5">
                            <x-ui.label for="forgot-email">Ваш Email</x-ui.label>
                            <x-ui.input id="forgot-email" wire:model="email" type="email" placeholder="Введите ваш email" />
                            @error('email') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                        </div>

                        <!-- НАШ РОДНОЙ ГЕНЕРАТОР КАПЧИ -->
                        <div class="space-y-1.5">
                            <x-ui.label>Цифры на картинке</x-ui.label>
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <img src="{{ $captchaImage }}" alt="Captcha" class="flex-1 h-10 rounded border border-border bg-white">
                                    <x-ui.button type="button" class="shrink-0" variant="outline" size="icon" wire:click="refreshCaptcha" wire:loading.attr="disabled" wire:target="refreshCaptcha" title="Обновить картинку">
                                        <x-lucide-refresh-cw class="w-4 h-4" wire:loading.remove wire:target="refreshCaptcha" />
                                        <x-lucide-loader-2 class="w-4 h-4 animate-spin inline" wire:loading wire:target="refreshCaptcha" />
                                    </x-ui.button>
                                </div>
                                <x-ui.input wire:model="captchaInput" placeholder="Введите код" class="w-full" />
                            </div>
                            <p class="text-[10px] text-muted-foreground">Если цифры на картинке неразборчивые, нажмите "Обновить".</p>
                            @error('captchaInput') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                        </div>

                        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="sendResetLink" class="w-full">
                            <span wire:loading.remove wire:target="sendResetLink">Восстановить</span>
                            <span wire:loading wire:target="sendResetLink" class="flex items-center gap-2">
                                <x-lucide-loader-2 class="w-4 h-4 animate-spin inline" /> Отправка...
                            </span>
                        </x-ui.button>
                    </form>

                    <div class="text-center">
                        <button type="button" @click="modalOpen = false; $dispatch('open-login-modal', { email: $wire.email })" class="text-xs text-primary hover:underline">
                            Я вспомнил пароль. Вернуться ко входу
                        </button>
                    </div>
                </div>

                <!-- Плашка: Регистрация -->
                <div class="bg-muted/30 md:border-l border-border p-6 text-center space-y-2">
                    <h4 class="font-semibold text-lg">У вас ещё нет анкеты?</h4>
                    <p class="text-sm text-muted-foreground">Вы найдете множество интересных людей из вашего города. Начните общаться прямо сейчас.</p>
                    <p class="text-xs text-muted-foreground pb-2">Регистрация займет меньше минуты.</p>
                    <x-ui.button as="a" href="/register" class="w-full">
                        Регистрация
                    </x-ui.button>
                </div>
                </div>
                
            @endif
        </div>
    </div>
</div>