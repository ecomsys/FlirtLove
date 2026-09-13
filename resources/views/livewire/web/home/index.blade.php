<!-- === САЙДБАР (Левая колонка) === -->
<x-slot:sidebar>
    @guest
        @include('livewire.web.sidebar.guest')
    @else
        @include('livewire.web.sidebar.inapp')
    @endguest
</x-slot:sidebar>

<!-- === ОСНОВНОЙ КОНТЕНТ (Правая колонка) === -->
<div class="space-y-6">
    
    <!-- 1. Панель фильтров (Над лентой) -->
    <div class="bg-card border border-border rounded-xl p-4 flex flex-wrap items-center gap-4">
        <div class="flex items-center gap-2">
            <span class="text-sm font-medium text-muted-foreground">Пол:</span>
            <x-ui.select wire:model.live="gender">
                <x-ui.select-trigger class="w-[120px]"></x-ui.select-trigger>
                <x-ui.select-content>
                    <x-ui.select-item value="any">Все</x-ui.select-item>
                    <x-ui.select-item value="male">Парни</x-ui.select-item>
                    <x-ui.select-item value="female">Девушки</x-ui.select-item>
                </x-ui.select-content>
            </x-ui.select>
        </div>

        <label class="flex items-center gap-2 text-sm cursor-pointer px-3 py-2 border border-border rounded-md hover:bg-muted/20 transition-colors">
            <input type="checkbox" wire:model.live="onlineOnly" class="rounded">
            Только онлайн
        </label>

        <div class="ml-auto text-xs text-muted-foreground">
            Найдено: {{ $users->total() }}
        </div>
    </div>

    <!-- 2. Сетка анкет -->
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
        @forelse ($users as $user)
            <div wire:key="user-{{ $user->id }}" class="relative group rounded-xl overflow-hidden border border-border bg-card shadow-sm hover:shadow-md transition-shadow">
                <div class="aspect-[3/4] bg-muted relative">
                    @if($user->photos->isNotEmpty())
                        <img src="{{ $user->photos->first()->path_medium }}" alt="{{ $user->name }}" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                    @else
                        <div class="absolute inset-0 flex items-center justify-center text-muted-foreground">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                        </div>
                    @endif
                    
                    @if($user->is_online)
                        <span class="absolute top-2 right-2 bg-green-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full flex items-center gap-1">
                            <span class="w-1.5 h-1.5 bg-white rounded-full animate-pulse"></span> Онлайн
                        </span>
                    @endif

                    <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent p-3 pt-10">
                        <h3 class="text-white font-semibold text-lg flex items-center gap-1">
                            {{ $user->name }}, <span class="font-normal">{{ $user->profile->age ?? '?' }}</span>
                            @if($user->has_active_premium)
                                <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" /></svg>
                            @endif
                        </h3>
                        <p class="text-gray-300 text-xs">{{ $user->profile?->city?->name ?? 'Город не указан' }}</p>
                    </div>
                </div>

                <div class="p-2 bg-card flex gap-2">
                    @auth
                        <!-- Заглушка для авторизованного (позже здесь будет переход в чат) -->
                        <button class="flex-1 bg-primary/10 text-primary hover:bg-primary/20 text-xs font-medium py-2 rounded-md transition-colors flex items-center justify-center gap-1">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" /></svg>
                            Написать
                        </button>
                    @else
                        <!-- ФИКС: Прямой вызов модалки с логином и капчей -->
                        <button @click="$dispatch('open-login-modal')" class="w-full bg-primary/10 text-primary hover:bg-primary/20 text-xs font-medium py-2 rounded-md transition-colors flex items-center justify-center gap-1">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" /></svg>
                            Написать
                        </button>
                    @endauth
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-16 text-muted-foreground">
                <p>По вашим критериям никого не найдено. Попробуйте изменить фильтры.</p>
            </div>
        @endforelse
    </div>

    <!-- 3. Кнопка Показать больше (Пагинация) -->
    <div class="pt-4">
        {{ $users->links() }}
    </div>

    <!-- 4. БЛОК ИНФОРМАЦИИ ТОЛЬКО ДЛЯ ГОСТЯ -->
    @guest
        <div class="mt-8 bg-card border border-border rounded-xl p-8 text-center space-y-4">
            <h3 class="text-2xl font-bold">Добро пожаловать на FlirtLove! ❤️</h3>
            <p class="text-muted-foreground max-w-2xl mx-auto">
                Наш сайт знакомств поможет вам найти серьезные отношения, друзей или просто интересных собеседников. 
                Регистрируйтесь бесплатно, чтобы получить доступ к чатам, лайкам и полному просмотру анкет.
            </p>
            <div class="flex flex-wrap justify-center gap-4 pt-2">
                <a href="/register" class="bg-primary text-primary-foreground px-6 py-3 rounded-lg font-medium hover:bg-primary/90 transition-colors">Создать аккаунт</a>
                <!-- ФИКС: Кнопка открывает модалку напрямую -->
                <button @click="$dispatch('open-login-modal')" class="border border-border text-foreground px-6 py-3 rounded-lg font-medium hover:bg-muted transition-colors">Войти</button>
            </div>
            <div class="flex flex-wrap justify-center gap-6 pt-6 text-xs text-muted-foreground">
                <a href="#" class="hover:text-primary">Правила сайта</a>
                <a href="#" class="hover:text-primary">Политика конфиденциальности</a>
                <a href="#" class="hover:text-primary">Поддержка</a>
            </div>
        </div>
    @endguest
</div>