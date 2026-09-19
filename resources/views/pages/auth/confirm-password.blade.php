<x-layouts.guest> {{-- Или твой лаяут для гостей, например <x-app-layout> --}}
    <div 
        x-data="{ 
            password: '', 
            loading: false, 
            errors: {},
            
            async submit() {
                if (this.loading) return;
                this.loading = true;
                this.errors = {};
                
                try {
                    const res = await fetch('{{ route('password.confirm.post') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ password: this.password })
                    });
                    
                    const data = await res.json().catch(() => null);
                    
                    if (res.ok && data && data.success) {
                        window.location.href = data.redirect;
                    } else if (data && data.errors) {
                        this.errors = data.errors;
                    } else {
                        this.errors = { password: ['Ошибка сервера. Попробуйте снова.'] };
                    }
                } catch(e) {
                    this.errors = { password: ['Не удалось связаться с сервером.'] };
                } finally {
                    this.loading = false;
                }
            }
        }"
        class="w-full max-w-md mx-auto p-4 bg-background text-foreground min-h-[calc(100dvh-4rem)] flex flex-col justify-center"
    >
        <!-- Заголовок -->
        <div class="text-center mb-4">
            <h1 class="text-2xl font-semibold">{{ __('auth.confirm_password') }}</h1>
            <p class="text-sm text-muted-foreground mt-1">{{ __('auth.confirm_password_before_continuing') }}</p>
        </div>

        <form @submit.prevent="submit()" class="space-y-5">
            <!-- Пароль -->
            <div class="space-y-2">
                <x-ui.label for="password" class="text-sm font-medium text-muted-foreground">
                    {{ __('auth.password') }}
                </x-ui.label>
                <x-ui.input
                    x-model="password"
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="w-full bg-input border-border focus-visible:ring-ring autofill:bg-input autofill:text-foreground autofill:shadow-none"
                    placeholder="{{ __('auth.ph_password') }}"
                />
                <p x-show="errors.password" x-text="errors.password?.[0] || ''" class="text-sm text-destructive"></p>
            </div>

            <!-- Кнопка отправки с состоянием загрузки -->
            <x-ui.button
                type="submit"
                x-bind:disabled="loading"
                class="w-full py-3 bg-primary text-primary-foreground hover:bg-primary/90 transition-colors"
            >
                <span x-show="!loading">{{ __('common.confirm') }}</span>
                <span x-show="loading" x-cloak style="display: none;" class="flex items-center justify-center gap-2">
                    <x-lucide-loader-2 class="w-5 h-5 animate-spin inline" />
                    {{ __('common.processing') }}
                </span>
            </x-ui.button>
        </form>
    </div>
</x-layouts.guest>