@php
    $months = [];
    for ($m = 1; $m <= 12; $m++) {
        $months[$m] = \Carbon\Carbon::create()->month($m)->translatedFormat('F');
    }
    $days = range(1, 31);
    $years = range(2010, 1950);
@endphp

<div 
    x-data="{ 
        isOpen: false,
        form: {
            name: '',
            gender: '',
            birth_day: '',
            birth_month: '',
            birth_year: ''
        },
        get isDateValid() {
            if (!this.form.birth_day || !this.form.birth_month || !this.form.birth_year) return false;
            const d = new Date(this.form.birth_year, this.form.birth_month - 1, this.form.birth_day);
            return d.getFullYear() == this.form.birth_year && 
                   (d.getMonth() + 1) == this.form.birth_month && 
                   d.getDate() == this.form.birth_day;
        },
        open() {
            this.form = { name: '', gender: '', birth_day: '', birth_month: '', birth_year: '' };
            this.isOpen = true;
        },
        submitForm() {
            // 1. Показываем спиннер (Чистый JS, мгновенно)
            window.showPageLoader();
            
            // 2. Закрываем модалку
            this.isOpen = false;
            
            // 3. Собираем параметры из Alpine вручную
            const params = new URLSearchParams();
            if (this.form.name) params.append('name', this.form.name);
            if (this.form.gender) params.append('gender', this.form.gender);
            if (this.form.birth_day) params.append('birth_day', this.form.birth_day);
            if (this.form.birth_month) params.append('birth_month', this.form.birth_month);
            if (this.form.birth_year) params.append('birth_year', this.form.birth_year);
            
            // 4. Даем 50мс браузеру отрисовать спиннер, затем перенаправляем
            setTimeout(() => {
                window.location.href = '{{ route('register') }}?' + params.toString();
            }, 50);
        }
    }"
    @open-register-modal.window="open()"
    @keydown.escape.window="isOpen = false"
>
    <!-- ОВЕРЛЕЙ -->
    <div 
        x-show="isOpen" 
        x-cloak 
        style="display: none;"
        class="fixed inset-0 z-[90] overflow-y-auto bg-black/50 backdrop-blur-sm scrollbar-hidden flex justify-center items-center py-8 px-4"
        @click.self="isOpen = false"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <!-- ТЕЛО МОДАЛКИ (ИСПОЛЬЗУЕМ x-if ДЛЯ ПОЛНОГО УДАЛЕНИЯ ИЗ DOM) -->
        <template x-if="isOpen">
            <div class="bg-card border border-border rounded-lg shadow-2xl max-w-md w-full mx-auto my-auto flex flex-col"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95">
                
                <!-- ШАПКА -->
                <div class="flex items-center justify-between p-4 border-b border-border">
                    <h3 class="text-xl font-semibold">Создайте анкету</h3>
                    <button @click="isOpen = false" class="text-muted-foreground hover:text-foreground">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- КОНТЕНТ -->
                <div class="px-6 pb-6 pt-3">               
                    
                    <!-- ФОРМА. Предотвращаем стандартный переход, вызываем submitForm() -->
                    <form class="space-y-4" @submit.prevent="submitForm()">
                        
                        <!-- Имя -->
                        <div class="space-y-2">
                            <label for="reg-name" class="text-sm font-medium text-muted-foreground">Ваше имя</label>
                            <x-ui.input x-model="form.name" id="reg-name" name="name" type="text" required placeholder="Например, Анна" />
                        </div>

                        <!-- Пол -->
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-muted-foreground">Кого вы ищете?</label>
                            <div class="grid grid-cols-2 gap-3 sm:gap-4">
                                <button type="button" @click="form.gender = 'male'"
                                    class="flex cursor-pointer flex-col items-center gap-2 rounded-lg border p-4 outline-none transition-all duration-200 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                                    :class="form.gender === 'male' ? 'border-primary bg-primary/10 shadow-sm' : 'border-border hover:bg-accent hover:border-muted-foreground/20'">
                                    <x-svg.man class="h-12 w-12 sm:h-14 sm:w-14 transition-colors duration-200" />                            
                                    <span class="text-sm font-medium transition-colors duration-200" :class="form.gender === 'male' ? 'text-primary' : 'text-foreground'">Парня</span>
                                </button>

                                <button type="button" @click="form.gender = 'female'"
                                    class="flex cursor-pointer flex-col items-center gap-2 rounded-lg border p-4 outline-none transition-all duration-200 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                                    :class="form.gender === 'female' ? 'border-primary bg-primary/10 shadow-sm' : 'border-border hover:bg-accent hover:border-muted-foreground/20'">
                                    <x-svg.woman class="h-12 w-12 transition-colors duration-200 sm:h-14 sm:w-14"/>
                                    <span class="text-sm font-medium transition-colors duration-200" :class="form.gender === 'female' ? 'text-primary' : 'text-foreground'">Девушку</span>
                                </button>
                            </div>
                        </div>

                        <!-- Дата рождения -->
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-muted-foreground">Дата рождения</label>
                            <div class="grid w-full grid-cols-3 gap-2">
                                <x-ui.select x-model="form.birth_day" x-modelable="value" class="w-full">
                                    <x-ui.select-trigger class="w-full bg-input border-border">
                                        <x-ui.select-value placeholder="День" />
                                    </x-ui.select-trigger>
                                    <x-ui.select-content side="top" align="end" class="z-[100] max-h-[24rem] min-w-[var(--radix-select-trigger-width)] overflow-y-auto [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-muted-foreground/30 [&::-webkit-scrollbar-thumb]:rounded-full hover:[&::-webkit-scrollbar-thumb]:bg-muted-foreground/50">
                                        @foreach ($days as $day)
                                            <x-ui.select-item value="{{ $day }}">{{ $day }}</x-ui.select-item>
                                        @endforeach
                                    </x-ui.select-content>
                                </x-ui.select>

                                <x-ui.select x-model="form.birth_month" x-modelable="value" class="w-full">
                                    <x-ui.select-trigger class="w-full bg-input border-border">
                                        <x-ui.select-value placeholder="Месяц" />
                                    </x-ui.select-trigger>
                                    <x-ui.select-content side="top" align="end" class="z-[100] max-h-[24rem] min-w-[var(--radix-select-trigger-width)] overflow-y-auto [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-muted-foreground/30 [&::-webkit-scrollbar-thumb]:rounded-full hover:[&::-webkit-scrollbar-thumb]:bg-muted-foreground/50">
                                        @foreach ($months as $value => $label)
                                            <x-ui.select-item value="{{ $value }}">{{ ucfirst($label) }}</x-ui.select-item>
                                        @endforeach
                                    </x-ui.select-content>
                                </x-ui.select>

                                <x-ui.select x-model="form.birth_year" x-modelable="value" class="w-full">
                                    <x-ui.select-trigger class="w-full bg-input border-border">
                                        <x-ui.select-value placeholder="Год" />
                                    </x-ui.select-trigger>
                                    <x-ui.select-content side="top" align="end" class="z-[100] max-h-[24rem] min-w-[var(--radix-select-trigger-width)] overflow-y-auto [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-muted-foreground/30 [&::-webkit-scrollbar-thumb]:rounded-full hover:[&::-webkit-scrollbar-thumb]:bg-muted-foreground/50">
                                        @foreach ($years as $year)
                                            <x-ui.select-item value="{{ $year }}">{{ $year }}</x-ui.select-item>
                                        @endforeach
                                    </x-ui.select-content>
                                </x-ui.select>
                            </div>
                            <p x-show="form.birth_day && form.birth_month && form.birth_year && !isDateValid" class="text-sm text-destructive">
                                Такой даты не существует. Проверьте месяц и день.
                            </p>
                        </div>

                        <x-ui.button type="submit" class="w-full" x-bind:disabled="!(form.name && form.gender && isDateValid)">
                            Далее
                        </x-ui.button>
                        
                        <p class="text-[10px] text-muted-foreground text-center leading-relaxed pt-1">
                            Регистрируясь, вы принимаете условия <a href="#" class="text-primary hover:underline">пользовательского соглашения</a> и даете согласие на обработку персональных данных
                        </p>
                    </form>

                    <div class="text-center mt-4 border-t border-border pt-4">
                        <button @click="isOpen = false; window.dispatchEvent(new CustomEvent('open-login-modal'))" class="text-xs text-primary hover:underline">
                            У меня уже есть аккаунт. Войти
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>