<!-- Виджет: Войти -->
<x-ui.card class="p-5 space-y-3" x-data="{ sidebarEmail: '', sidebarPassword: '' }">
    <h3 class="font-semibold text-base text-center">Войти на FlirtLove</h3>
    
    <div class="space-y-1.5">
        <x-ui.label for="sidebar-email">Email</x-ui.label>
        <x-ui.input id="sidebar-email" type="email" x-model="sidebarEmail" placeholder="Введите email" />
    </div>

    <div class="space-y-1.5">
        <x-ui.label for="sidebar-password">Пароль</x-ui.label>
        <x-ui.input id="sidebar-password" type="password" x-model="sidebarPassword" placeholder="Введите пароль" />
    </div>
    
    <!-- Заменили Livewire.dispatch на window.dispatchEvent -->
    <x-ui.button @click="window.dispatchEvent(new CustomEvent('open-login-modal', { detail: { email: sidebarEmail, password: sidebarPassword } }))" class="w-full">
        Войти
    </x-ui.button>
    
    <div class="text-center">
        <button @click.prevent="window.dispatchEvent(new CustomEvent('open-forgot-password-modal', { detail: { email: sidebarEmail } }))" class="text-xs text-muted-foreground hover:text-primary transition-colors">
            Забыли пароль?
        </button>
    </div>

    <div class="relative py-2">
        <div class="border-t border-border"></div>
        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-card px-2 text-[10px] text-muted-foreground uppercase">или через</span>
    </div>

     {{-- логин через соцсети --}}
    <x-sidebar.guest.blocks.social-auth mode="login" />    
</x-ui.card>

<!-- Виджет: Регистрация -->
<x-ui.card class="p-5 space-y-3">
    <h3 class="font-semibold text-base text-center ">Регистрация</h3>
    <x-ui.button as-child class="w-full">
        <a href="/register">Создать аккаунт</a>
    </x-ui.button>
    
    <div class="relative py-2">
        <div class="border-t border-border"></div>
        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-card px-2 text-[10px] text-muted-foreground uppercase">или через</span>
    </div>

    {{-- регистрация через соцсети --}}
    <x-sidebar.guest.blocks.social-auth mode="register"/>    
</x-ui.card>

<!-- Виджет: Мы в соцсетях -->
<x-ui.card class="p-5 space-y-3">
    <h3 class="font-semibold text-base text-center">Мы в соцсетях</h3>
    
    <x-sidebar.guest.blocks.social-links />
</x-ui.card>    

<!-- Виджет: Мобильные приложения -->
<x-ui.card class="p-5 space-y-3">
    <h3 class="font-semibold text-base text-center ">Мобильное приложение</h3>
    <x-sidebar.guest.blocks.app-links />
</x-ui.card>

<!-- Виджет: Последние статьи -->
<x-ui.card class="p-5 space-y-3">
    <h3 class="font-semibold text-base text-center">Последние статьи</h3>
    <x-sidebar.guest.blocks.blog-links />
</x-ui.card>