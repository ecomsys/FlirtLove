<x-layouts.guest>
    <section 
        x-data="{ 
            currentEmail: '{{ auth()->user()->email }}',
            showEmailForm: false,
            newEmail: '',
            loadingResend: false,
            loadingChange: false,
            resendStatus: false,
            errors: {},
            
            get emailProviderUrl() {
                if (!this.currentEmail) return '#';
                let domain = this.currentEmail.split('@')[1];
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
            
            async sendVerification() {
                if (this.loadingResend) return;
                this.loadingResend = true;
                this.resendStatus = false;
                
                try {
                    const res = await fetch('{{ route('verification.send') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const data = await res.json();
                    
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    } else if (data.success) {
                        this.resendStatus = true;
                        this.$dispatch('show-toast', { type: 'success', message: '{{ __('auth.resend_success') }}' });
                    }
                } catch(e) {
                    this.$dispatch('show-toast', { type: 'error', message: 'Ошибка сети' });
                } finally {
                    this.loadingResend = false;
                }
            },
            
            async changeEmail() {
                if (this.loadingChange) return;
                this.loadingChange = true;
                this.errors = {};
                
                try {
                    const res = await fetch('{{ route('verification.change') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ email: this.newEmail })
                    });
                    const data = await res.json().catch(() => null);
                    
                    if (res.ok && data && data.success) {
                        this.currentEmail = data.email;
                        this.showEmailForm = false;
                        this.newEmail = '';
                        this.$dispatch('show-toast', { type: 'success', message: '{{ __('auth.resend_success') }}' });
                    } else if (data && data.errors) {
                        this.errors = data.errors;
                    } else {
                        this.$dispatch('show-toast', { type: 'error', message: 'Ошибка сервера' });
                    }
                } catch(e) {
                    this.$dispatch('show-toast', { type: 'error', message: 'Ошибка сети' });
                } finally {
                    this.loadingChange = false;
                }
            }
        }"
        class="w-full"
    >
        <div class="bg-card text-card-foreground px-4">
            <div class="relative max-w-md mx-auto px-6 py-10 bg-card text-card-foreground flex items-center gap-4">
                <div class="hidden md:flex w-20 h-20 shrink-0 rounded-full bg-primary/10 items-center justify-center">
                    <svg class="w-10 h-10 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-semibold mb-2">{{ __('auth.check_email') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        <span>{{ __('auth.check_email_desc') }}</span>
                        <span class="text-primary font-medium" x-text="currentEmail"></span>
                    </p>
                </div>
            </div>
        </div>

        <div class="w-full max-w-md mx-auto py-10 px-4 bg-background text-foreground">
            <!-- Кнопки действий -->
            <div class="max-w-[18rem] flex flex-col gap-3 mb-10 mx-auto">
                <!-- Динамическая кнопка перехода в почту -->
                <x-ui.button as-child class="w-full">
                    <a :href="emailProviderUrl" target="_blank" class="flex items-center justify-center gap-2">
                        {{ __('auth.go_to_email') }}
                    </a>
                </x-ui.button>

                <!-- Кнопка повторной отправки со спиннером -->
                <x-ui.button 
                    variant="outline" 
                    @click="sendVerification()" 
                    x-bind:disabled="loadingResend" 
                    class="w-full"
                >
                    <span x-show="!loadingResend">{{ __('auth.resend_email') }}</span>
                    <span x-show="loadingResend" x-cloak style="display: none;" class="flex items-center justify-center gap-2">                    
                        <x-lucide-loader-2 class="w-5 h-5 animate-spin inline"/> 
                        {{ __('common.processing') }}
                    </span>
                </x-ui.button>

                <!-- Уведомление об успешной отправке -->
                <template x-if="resendStatus">
                    <div class="p-3 rounded-md bg-primary/10 border border-primary/20 text-primary text-sm text-center">
                        {{ __('auth.resend_success') }}
                    </div>
                </template>
            </div>

            <!-- Разделитель -->
            <div class="relative mb-8">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-border"></div>
                </div>
                <div class="relative flex justify-center text-xs uppercase">
                    <span class="bg-background px-2 text-muted-foreground">{{ __('auth.what_if_no_email') }}</span>
                </div>
            </div>

            <!-- Блок помощи -->
            <div class="space-y-4 text-sm mb-8">
                <p class="text-muted-foreground">{{ __('auth.check_spam') }}</p>
            </div>

            <!-- Разделитель -->
            <div class="relative my-8">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-border"></div>
                </div>
                <div class="relative flex justify-center text-xs uppercase">
                    <span class="bg-background px-2 text-muted-foreground">{{ __('auth.wrong_email') }}</span>
                </div>
            </div>

            <!-- Блок смены почты -->
            <div class="max-w-[18rem] mx-auto">
                <template x-if="!showEmailForm">
                    <x-ui.button variant="outline" @click="showEmailForm = true" class="w-full">
                        {{ __('auth.change_email') }}
                    </x-ui.button>
                </template>

                <template x-if="showEmailForm">
                    <form @submit.prevent="changeEmail()" class="space-y-3">
                        <div>
                            <x-ui.label for="newEmail" class="text-xs text-muted-foreground">{{ __('auth.new_email_address') }}</x-ui.label>
                            <x-ui.input x-model="newEmail" id="newEmail" type="email" autofocus class="mt-1 block w-full" placeholder="new@example.com" />
                            <p x-show="errors.email" x-text="errors.email?.[0] || ''" class="text-xs text-destructive mt-1"></p>
                        </div>
                        
                        <div class="flex gap-2">
                            <x-ui.button type="submit" x-bind:disabled="loadingChange" class="flex-1">
                                <span x-show="!loadingChange">{{ __('auth.save_and_send') }}</span>
                                <span x-show="loadingChange" x-cloak style="display: none;" class="flex items-center justify-center gap-2">                                
                                    <x-lucide-loader-2 class="w-5 h-5 animate-spin inline"/>
                                </span>
                            </x-ui.button>
                            <x-ui.button type="button" variant="outline" @click="showEmailForm = false" class="flex-1">
                                {{ __('common.cancel') }}
                            </x-ui.button>
                        </div>
                    </form>
                </template>

                <!-- Форма выхода -->
                <form method="POST" action="{{ route('logout') }}" class="block w-full text-center mt-6">
                    @csrf
                    <button type="submit" class="text-sm text-muted-foreground hover:text-destructive transition-colors">
                        {{ __('common.logout') }}
                    </button>
                </form>
            </div>
        </div>
    </section>
</x-layouts.guest>