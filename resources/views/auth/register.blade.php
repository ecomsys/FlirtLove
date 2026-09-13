<x-layouts.guest>

<div class="mx-auto flex min-h-[calc(100dvh-4rem)] w-full max-w-2xl items-center px-4 py-8 sm:px-6" x-data="registrationForm({
    csrf: '{{ csrf_token() }}',
    initialCaptcha: '{{ $captchaImage }}'
})">
    <div class="flex w-full flex-col bg-background text-foreground lg:flex-row lg:items-stretch">

        <!-- ЛЕВАЯ КОЛОНКА (форма регистрации) -->
        <div class="mx-auto flex w-full max-w-md flex-col justify-center lg:mx-0 lg:max-w-none lg:w-[448px] lg:flex-none lg:border-r lg:border-border lg:pr-8">

            <!-- Прогресс-бар -->
            <div class="mx-auto mb-8 flex w-full max-w-[240px] flex-col items-center gap-2">
                <div class="flex w-full items-center gap-2">
                    <div class="h-1.5 flex-1 rounded-full transition-colors duration-300" :class="step >= 1 ? 'bg-green-600' : 'bg-muted'"></div>
                    <div class="h-1.5 flex-1 rounded-full transition-colors duration-300" :class="step >= 2 ? 'bg-green-600' : 'bg-muted'"></div>
                    <div class="h-1.5 flex-1 rounded-full transition-colors duration-300" :class="step >= 3 ? 'bg-green-600' : 'bg-muted'"></div>
                </div>
                <p class="text-xs text-muted-foreground">Шаг <span x-text="step"></span> из 3</p>
            </div>

            <h1 class="mb-6 text-center text-2xl font-semibold tracking-tight">{{ __('auth.registration') }}</h1>

            <!-- ЭТАП 1 -->
            <form x-show="step === 1" @submit.prevent="submitStep1" class="w-full space-y-4">
                
                <!-- Имя -->
                <div class="space-y-2">
                    <label for="name" class="text-sm font-medium text-muted-foreground">{{ __('auth.name') }}</label>
                    <x-ui.input x-model="form.name" id="name" type="text" required placeholder="{{ __('auth.ph_name') }}" />
                    <p x-show="errors.name" x-text="errors.name" class="text-sm text-destructive"></p>
                </div>

                <!-- Пол -->
                <div class="space-y-2">
                    <label class="text-sm font-medium text-muted-foreground">{{ __('auth.gender') }}</label>
                    <div class="grid grid-cols-2 gap-3 sm:gap-4">

                        <!-- Мужчина -->
                        <button type="button" x-on:click="form.gender = 'male'"
                            class="flex cursor-pointer flex-col items-center gap-2 rounded-lg border p-4 outline-none transition-all duration-200 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                            :class="form.gender === 'male' ? 'border-primary bg-primary/10 shadow-sm' : 'border-border hover:bg-accent hover:border-muted-foreground/20'">
                            {{-- male gender --}}
                            <x-svg.man class="h-12 w-12 sm:h-14 sm:w-14 transition-colors duration-200 "/>                            
                            <span class="text-sm font-medium transition-colors duration-200" :class="form.gender === 'male' ? 'text-primary' : 'text-foreground'">{{ __('auth.male') }}</span>
                        </button>

                        <!-- Женщина -->
                        <button type="button" x-on:click="form.gender = 'female'"
                            class="flex cursor-pointer flex-col items-center gap-2 rounded-lg border p-4 outline-none transition-all duration-200 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                            :class="form.gender === 'female' ? 'border-primary bg-primary/10 shadow-sm' : 'border-border hover:bg-accent hover:border-muted-foreground/20'">
                            {{-- femali gender --}}
                            <x-svg.woman class="h-12 w-12 transition-colors duration-200 sm:h-14 sm:w-14"/>
                            <span class="text-sm font-medium transition-colors duration-200" :class="form.gender === 'female' ? 'text-primary' : 'text-foreground'">{{ __('auth.female') }}</span>
                        </button>
                    </div>
                    <p x-show="errors.gender" x-text="errors.gender" class="text-sm text-destructive"></p>
                </div>

                <!-- Дата рождения -->
                <div class="space-y-2">
                    <label class="text-sm font-medium text-muted-foreground">{{ __('auth.dob') }}</label>
                    <div class="grid w-full grid-cols-3 gap-2">
                        <!-- День -->
                        <x-ui.select x-model="form.birth_day" x-modelable="value" class="w-full">
                            <x-ui.select-trigger class="w-full bg-input border-border">
                                <x-ui.select-value placeholder="{{ __('auth.day') }}" />
                            </x-ui.select-trigger>
                            <x-ui.select-content side="top" align="end"
                                class="max-h-[24rem] min-w-[var(--radix-select-trigger-width)] overflow-y-auto [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-muted-foreground/30 [&::-webkit-scrollbar-thumb]:rounded-full hover:[&::-webkit-scrollbar-thumb]:bg-muted-foreground/50">
                                @foreach ($days as $day)
                                    <x-ui.select-item value="{{ $day }}">{{ $day }}</x-ui.select-item>
                                @endforeach
                            </x-ui.select-content>
                        </x-ui.select>

                        <!-- Месяц -->
                        <x-ui.select x-model="form.birth_month" x-modelable="value" class="w-full">
                            <x-ui.select-trigger class="w-full bg-input border-border">
                                <x-ui.select-value placeholder="{{ __('auth.month') }}" />
                            </x-ui.select-trigger>
                            <x-ui.select-content side="top" align="end"
                                class="max-h-[24rem] min-w-[var(--radix-select-trigger-width)] overflow-y-auto [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-muted-foreground/30 [&::-webkit-scrollbar-thumb]:rounded-full hover:[&::-webkit-scrollbar-thumb]:bg-muted-foreground/50">
                                @foreach ($months as $value => $label)
                                    <x-ui.select-item value="{{ $value }}">{{ ucfirst($label) }}</x-ui.select-item>
                                @endforeach
                            </x-ui.select-content>
                        </x-ui.select>

                        <!-- Год -->
                        <x-ui.select x-model="form.birth_year" x-modelable="value" class="w-full">
                            <x-ui.select-trigger class="w-full bg-input border-border">
                                <x-ui.select-value placeholder="{{ __('auth.year') }}" />
                            </x-ui.select-trigger>
                            <x-ui.select-content side="top" align="end"
                                class="max-h-[24rem] min-w-[var(--radix-select-trigger-width)] overflow-y-auto [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-muted-foreground/30 [&::-webkit-scrollbar-thumb]:rounded-full hover:[&::-webkit-scrollbar-thumb]:bg-muted-foreground/50">
                                @foreach ($years as $year)
                                    <x-ui.select-item value="{{ $year }}">{{ $year }}</x-ui.select-item>
                                @endforeach
                            </x-ui.select-content>
                        </x-ui.select>
                    </div>
                    <p x-show="errors.birth_day" x-text="errors.birth_day" class="text-sm text-destructive"></p>
                </div>

                <!-- Кнопка Далее (Шаг 1) -->
                <x-ui.button type="submit" variant="default" size="sm" class="w-full"
                    x-bind:disabled="!(form.name && form.gender && form.birth_day && form.birth_month && form.birth_year) || loading">                    
                    <span x-show="!loading">{{ __('common.next') }}</span>
                    <x-lucide-loader-2 x-show="loading" class="w-5 h-5 animate-spin inline"/> 
                </x-ui.button>
            </form>

            <!-- ЭТАП 2 -->
            <form x-show="step === 2" x-cloak @submit.prevent="submitStep2" class="w-full space-y-4">
                <h2 class="text-center text-lg font-medium text-muted-foreground">{{ __('auth.goal') }}</h2>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-6 sm:gap-4">
                    <!-- Друзья -->
                    <button type="button" x-on:click="form.dating_goal = 'friends'"
                        class="col-span-1 sm:col-span-2 flex cursor-pointer flex-col items-center gap-2 rounded-lg border p-3 outline-none transition-all duration-200 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background sm:gap-3 sm:p-4"
                        :class="form.dating_goal === 'friends' ? 'border-primary bg-primary/10 shadow-sm' : 'border-border hover:bg-accent hover:border-muted-foreground/20'">
                        <svg class="h-10 w-10 transition-colors duration-200 sm:h-12 sm:w-12" :class="form.dating_goal === 'friends' ? 'text-primary' : 'text-muted-foreground'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                        </svg>
                        <span class="text-center text-sm font-medium transition-colors duration-200" :class="form.dating_goal === 'friends' ? 'text-primary' : 'text-foreground'">{{ __('auth.friends') }}</span>
                    </button>

                    <!-- Семья -->
                    <button type="button" x-on:click="form.dating_goal = 'family'"
                        class="col-span-1 sm:col-span-2 flex cursor-pointer flex-col items-center gap-2 rounded-lg border p-3 outline-none transition-all duration-200 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background sm:gap-3 sm:p-4"
                        :class="form.dating_goal === 'family' ? 'border-primary bg-primary/10 shadow-sm' : 'border-border hover:bg-accent hover:border-muted-foreground/20'">
                        <svg class="h-10 w-10 transition-colors duration-200 sm:h-12 sm:w-12" :class="form.dating_goal === 'family' ? 'text-primary' : 'text-muted-foreground'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 21h18M8.25 12h7.5M6 6.75h12M6 12V6.75M18 12V6.75M4.5 3.75h15M4.5 21v-8.25" />
                        </svg>
                        <span class="text-center text-sm font-medium transition-colors duration-200" :class="form.dating_goal === 'family' ? 'text-primary' : 'text-foreground'">{{ __('auth.family') }}</span>
                    </button>

                    <!-- Путешествия -->
                    <button type="button" x-on:click="form.dating_goal = 'travel'"
                        class="col-span-1 sm:col-span-2 flex cursor-pointer flex-col items-center gap-2 rounded-lg border p-3 outline-none transition-all duration-200 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background sm:gap-3 sm:p-4"
                        :class="form.dating_goal === 'travel' ? 'border-primary bg-primary/10 shadow-sm' : 'border-border hover:bg-accent hover:border-muted-foreground/20'">
                        <svg class="h-10 w-10 transition-colors duration-200 sm:h-12 sm:w-12" :class="form.dating_goal === 'travel' ? 'text-primary' : 'text-muted-foreground'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                        </svg>
                        <span class="text-center text-sm font-medium transition-colors duration-200" :class="form.dating_goal === 'travel' ? 'text-primary' : 'text-foreground'">{{ __('auth.travel') }}</span>
                    </button>

                    <!-- Романтика -->
                    <button type="button" x-on:click="form.dating_goal = 'romantic'"
                        class="col-span-1 sm:col-span-3 flex cursor-pointer flex-col items-center gap-2 rounded-lg border p-3 outline-none transition-all duration-200 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background sm:gap-3 sm:p-4"
                        :class="form.dating_goal === 'romantic' ? 'border-primary bg-primary/10 shadow-sm' : 'border-border hover:bg-accent hover:border-muted-foreground/20'">
                        <svg class="h-10 w-10 transition-colors duration-200 sm:h-12 sm:w-12" :class="form.dating_goal === 'romantic' ? 'text-primary' : 'text-muted-foreground'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
                        </svg>
                        <span class="text-center text-sm font-medium transition-colors duration-200" :class="form.dating_goal === 'romantic' ? 'text-primary' : 'text-foreground'">{{ __('auth.romantic') }}</span>
                    </button>

                    <!-- Свободные отношения -->
                    <button type="button" x-on:click="form.dating_goal = 'casual'"
                        class="col-span-2 sm:col-span-3 flex cursor-pointer flex-col items-center gap-2 rounded-lg border p-3 outline-none transition-all duration-200 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background sm:gap-3 sm:p-4"
                        :class="form.dating_goal === 'casual' ? 'border-primary bg-primary/10 shadow-sm' : 'border-border hover:bg-accent hover:border-muted-foreground/20'">
                        <svg class="h-10 w-10 transition-colors duration-200 sm:h-12 sm:w-12" :class="form.dating_goal === 'casual' ? 'text-primary' : 'text-muted-foreground'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 0 1-6.364 0M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Z" />
                        </svg>
                        <span class="text-center text-sm font-medium transition-colors duration-200" :class="form.dating_goal === 'casual' ? 'text-primary' : 'text-foreground'">{{ __('auth.casual') }}</span>
                    </button>
                </div>

                <p x-show="errors.dating_goal" x-text="errors.dating_goal" class="text-center text-sm text-destructive"></p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <!-- Назад -->                  
                    <x-ui.button type="button" x-on:click="step = 1" x-bind:disabled="loading" 
                        variant="outline" size="sm" class="w-full">                   
                        {{ __('common.back') }}                        
                    </x-ui.button>
                    
                    <!-- Далее -->           
                     <x-ui.button type="submit" variant="default" size="sm" class="w-full"
                        x-bind:disabled="!form.dating_goal || loading">                    
                        <span x-show="!loading">{{ __('common.next') }}</span>
                        <x-lucide-loader-2 x-show="loading" class="w-5 h-5 animate-spin inline"/> 
                    </x-ui.button>
                </div>
            </form>

            <!-- ЭТАП 3 -->
            <form x-show="step === 3" x-cloak @submit.prevent="submitRegister" class="w-full space-y-4">
                <h2 class="text-center text-base font-medium text-muted-foreground">{{ __('auth.final_step') }}</h2>

                <!-- Город -->
                <div class="space-y-2">
                    <label for="city" class="text-sm font-medium text-muted-foreground">{{ __('auth.city') }}</label>
                    <x-ui.input x-model="form.city" id="city" type="text" required                        
                        placeholder="{{ __('auth.ph_city') }}" />
                    <p x-show="errors.city" x-text="errors.city" class="text-sm text-destructive"></p>
                </div>

                <!-- Email -->
                <div class="space-y-2">
                    <label for="email" class="text-sm font-medium text-muted-foreground">{{ __('auth.email') }}</label>
                    <x-ui.input x-model="form.email" id="email" type="email" required                        
                        placeholder="{{ __('auth.ph_email') }}" />
                    <p x-show="errors.email" x-text="errors.email" class="text-sm text-destructive"></p>
                </div>

                <!-- Пароль -->
                <div class="space-y-2">
                    <label for="password" class="text-sm font-medium text-muted-foreground">{{ __('auth.password') }}</label>
                    <x-ui.input x-model="form.password" id="password" type="password" required                        
                        placeholder="{{ __('auth.ph_create_password') }}" />
                    <p x-show="errors.password" x-text="errors.password" class="text-sm text-destructive"></p>
                </div>

                <!-- Капча -->
                <div class="space-y-2">
                    <label for="captchaInput" class="text-sm font-medium text-muted-foreground">Символы на картинке</label>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <div class="flex items-center gap-2 w-[16rem] shrink-0">
                            <div class="flex h-11 min-w-0 flex-1 items-center justify-center overflow-hidden rounded-md border border-border bg-background">
                                <img :src="captchaImage" alt="Капча" class="h-full w-auto max-w-full object-contain" />
                            </div>
                            <button type="button" x-on:click="refreshCaptcha" :disabled="loadingCaptcha"
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground disabled:opacity-50"
                                title="Обновить картинку" aria-label="Обновить картинку">
                                <svg x-show="!loadingCaptcha" class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg>
                                <x-lucide-loader-2 x-show="loadingCaptcha" class="w-5 h-5 animate-spin inline"/>                                
                            </button>
                        </div>
                        <div class="min-w-0 flex-1">
                            <x-ui.input x-model="form.captchaInput" id="captchaInput" type="text" required
                                class="w-full bg-input border border-border rounded-md p-2 focus-visible:ring-2 focus-visible:ring-ring outline-none"
                                placeholder="Введите код" />
                        </div>
                    </div>
                    <p x-show="errors.captchaInput" x-text="errors.captchaInput" class="text-sm text-destructive"></p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <x-ui.button type="button" x-on:click="step = 2" x-bind:disabled="loading" variant="outline" size="sm">                        
                        {{ __('common.back') }}
                    </x-ui.button>
                    <x-ui.button x-bind:disabled="!(form.city && form.email && form.password && form.captchaInput) || loading"
                        type="submit" variant="default" size="sm">
                        <span x-show="!loading">{{ __('common.register') }}</span>                        
                        <x-lucide-loader-2 x-show="loading" class="w-5 h-5 animate-spin inline"/>   
                    </x-ui.button>
                </div>
            </form>

            <!-- Футер -->
            <div class="mt-6 flex flex-col items-center gap-1.5 text-center">
                <div class="flex items-center justify-center gap-1.5 text-xs text-muted-foreground">
                    <svg class="h-3.5 w-3.5 shrink-0 text-yellow-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                    <span>Регистрация через Google недоступна</span>
                </div>
                <p class="text-xs leading-relaxed text-muted-foreground/70">
                    Регистрируясь, вы принимаете
                    <a href="/a-conditions" class="underline hover:no-underline">условия пользовательского соглашения</a>
                    и даете согласие на обработку
                    <a href="/personal_data" class="underline hover:no-underline">персональных данных</a>
                </p>
            </div>

        </div>

        <!-- ПРАВАЯ КОЛОНКА (соцсети) -->
        <div class="hidden lg:flex lg:min-w-[14rem] lg:flex-1 flex-col lg:pl-8">            
            <x-sidebar.guest.social-auth-vertical class="w-full" mode="login" /> 
        </div>

    </div>
</div>

<!-- Alpine.js Component Script -->
<script>
function registrationForm({ csrf, initialCaptcha }) {
    return {
        step: 1,
        loading: false,
        loadingCaptcha: false,
        csrf: csrf,
        captchaImage: initialCaptcha,
        errors: {},
        form: {
            name: '',
            gender: '',
            birth_day: '',
            birth_month: '',
            birth_year: '',
            dating_goal: '',
            city: '',
            email: '',
            password: '',
            captchaInput: ''
        },

                async submitRequest(url, onSuccess) {
            this.loading = true;
            this.errors = {};
            
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'Accept': 'application/json' // КРИТИЧЕСКИ важный заголовок для Laravel
                    },
                    body: JSON.stringify(this.form)
                });

                // Проверяем, вернул ли сервер JSON. Если HTML — значит произошла ошибка
                const contentType = response.headers.get("content-type");
                if (!contentType || !contentType.includes("application/json")) {
                    // Если это HTML (например, страница 404 или 500 ошибки), читаем её как текст
                    const htmlText = await response.text();
                    console.error("Сервер вернул HTML вместо JSON. Код статуса:", response.status, htmlText);
                    alert('Ошибка сервера (статус ' + response.status + '). Открой консоль (F12) для деталей.');
                    return;
                }

                const data = await response.json();

                if (!response.ok) {
                    // Обработка ошибок валидации Laravel (422)
                    if (response.status === 422 && data.errors) {
                        // Laravel возвращает массивы ошибок. Берем первое сообщение.
                        const formattedErrors = {};
                        for (const key in data.errors) {
                            formattedErrors[key] = data.errors[key][0];
                        }
                        this.errors = formattedErrors;

                        // Если сервер вернул новую капчу (при ошибке ввода) — обновляем её
                        if (data.captchaImage) {
                            this.captchaImage = data.captchaImage;
                            this.form.captchaInput = ''; // Очищаем поле ввода
                        }
                    } else {
                        alert(data.message || 'Произошла ошибка. Попробуйте еще раз.');
                    }
                } else {
                    onSuccess(data);
                }
            } catch (error) {
                console.error('Fetch Error:', error);
                alert('Критическая ошибка сети. Проверьте подключение.');
            } finally {
                this.loading = false;
            }
        },

        submitStep1() {
            this.submitRequest('/register/step1', (data) => {
                this.step = 2;
            });
        },

        submitStep2() {
            this.submitRequest('/register/step2', (data) => {
                if (data.captchaImage) {
                    this.captchaImage = data.captchaImage;
                }
                this.step = 3;
            });
        },

        submitRegister() {
            this.submitRequest('/register', (data) => {
                if (data.redirect) {
                    window.location.href = data.redirect;
                }
            });
        },

        async refreshCaptcha() {
            this.loadingCaptcha = true;
            try {
                const response = await fetch('/register/captcha', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrf,
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();
                if (data.captchaImage) {
                    this.captchaImage = data.captchaImage;
                    this.form.captchaInput = ''; // очищаем поле ввода
                    // удаляем ошибку капчи если она была
                    if (this.errors.captchaInput) {
                        delete this.errors.captchaInput;
                    }
                }
            } catch (e) {
                console.error('Captcha refresh error', e);
            } finally {
                this.loadingCaptcha = false;
            }
        }
    }
}
</script>
</x-layouts.guest>