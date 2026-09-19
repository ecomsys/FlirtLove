<x-layouts.guest>
    <div 
        x-data="{ 
            token: '{{ $token }}',
            email: '{{ $email }}',
            password: '',
            password_confirmation: '',
            loading: false, 
            errors: {},
            
            async submit() {
                if (this.loading) return;
                this.loading = true;
                this.errors = {};
                
                try {
                    const res = await fetch('{{ route('password.update') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            token: this.token,
                            email: this.email,
                            password: this.password,
                            password_confirmation: this.password_confirmation
                        })
                    });
                    
                    const data = await res.json().catch(() => null);
                    
                    if (res.ok && data && data.success) {
                        window.location.href = data.redirect;
                    } else if (data && data.errors) {
                        this.errors = data.errors;
                    } else {
                        this.errors = { email: ['Ошибка сервера. Попробуйте снова.'] };
                    }
                } catch(e) {
                    this.errors = { email: ['Не удалось связаться с сервером.'] };
                } finally {
                    this.loading = false;
                }
            }
        }"
        class="w-full max-w-md mx-auto p-4 bg-background text-foreground min-h-[calc(100dvh-4rem)] flex flex-col justify-center"
    >
        <!-- Заголовок -->
        <div class="text-center mb-4">
            <h1 class="text-2xl font-semibold">{{ __('auth.create_new_password') }}</h1>
            <p class="text-sm text-muted-foreground mt-1">{{ __('auth.set_new_password') }}</p>
        </div>

        <form @submit.prevent="submit()" class="space-y-5">
            <!-- Email Address (readonly) -->
            <div class="space-y-2">
                <x-ui.label for="email" class="text-sm font-medium text-muted-foreground">
                    {{ __('auth.email') }}
                </x-ui.label>
                <x-ui.input 
                    x-model="email"
                    id="email" 
                    name="email" 
                    type="email" 
                    required 
                    readonly
                    autocomplete="username"
                    class="w-full bg-muted/50 border-border cursor-not-allowed opacity-75 focus-visible:ring-ring"
                />
                <p x-show="errors.email" x-text="errors.email?.[0] || ''" class="text-sm text-destructive"></p>
            </div>

            <!-- Password -->
            <div class="space-y-2">
                <x-ui.label for="password" class="text-sm font-medium text-muted-foreground">
                    {{ __('auth.new_password') }}
                </x-ui.label>
                <x-ui.input 
                    x-model="password"
                    id="password" 
                    name="password" 
                    type="password" 
                    required 
                    autofocus 
                    autocomplete="new-password"
                    class="w-full bg-input border-border focus-visible:ring-ring autofill:bg-input autofill:text-foreground autofill:shadow-none"
                    placeholder="{{ __('auth.enter_new_password') }}"
                />
                <p x-show="errors.password" x-text="errors.password?.[0] || ''" class="text-sm text-destructive"></p>
            </div>

            <!-- Confirm Password -->
            <div class="space-y-2">
                <x-ui.label for="password_confirmation" class="text-sm font-medium text-muted-foreground">
                    {{ __('auth.confirm_password') }}
                </x-ui.label>
                <x-ui.input 
                    x-model="password_confirmation"
                    id="password_confirmation" 
                    name="password_confirmation" 
                    type="password" 
                    required 
                    autocomplete="new-password"
                    class="w-full bg-input border-border focus-visible:ring-ring autofill:bg-input autofill:text-foreground autofill:shadow-none"
                    placeholder="{{ __('auth.confirm_your_password') }}"
                />
                <p x-show="errors.password_confirmation" x-text="errors.password_confirmation?.[0] || ''" class="text-sm text-destructive"></p>
            </div>

            <!-- Submit Button -->
            <x-ui.button 
                type="submit" 
                x-bind:disabled="loading"
                class="w-full py-3 bg-primary text-primary-foreground hover:bg-primary/90 transition-colors"
            >
                <span x-show="!loading">{{ __('auth.reset_password') }}</span>
                <span x-show="loading" x-cloak style="display: none;" class="flex items-center justify-center gap-2">
                    <x-lucide-loader-2 class="w-5 h-5 animate-spin inline" />
                    {{ __('common.processing') }}
                </span>
            </x-ui.button>
        </form>
    </div>
</x-layouts.guest>