<?php

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin')] class extends Component 
{
    public User $user;

    public function mount(User $user): void
    {
        $this->user = $user->load([
            'profile',
            'preferences',
            'photos' => fn($q) => $q->where('status', 'approved')->orderBy('is_primary', 'desc')->limit(1)
        ]);
    }

    // Этот метод слушает события от дочерних компонентов. 
    // Если юзера забанили в табе банов, мы обновим его здесь, чтобы шапка изменилась.
    #[On('user-updated')] 
    public function refreshUser(): void
    {
        $this->user->refresh();
    }
}; 
?>

<div 
    x-data="{ tab: localStorage.getItem('admin_user_tab') || 'profile' }" 
    class="space-y-6"
>
       {{-- ШАПКА ПРОФИЛЯ --}}
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-4">
            @php
                // Определяем URL для возврата:
                // Берем предыдущий URL, но если он совпадает с текущим (например, юзер нажал F5), 
                // то отправляем на список юзеров, чтобы не зациклить его на этой же странице.
                $previousUrl = url()->previous();
                $backUrl = ($previousUrl && $previousUrl !== url()->current()) 
                    ? $previousUrl 
                    : route('admin.users.index');
            @endphp

            <a href="{{ $backUrl }}" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            
            <x-avatar src="{{ $user->avatar_url }}" name="{{ $user->name }}" size="lg" />
            <div>
                <h1 class="text-2xl font-semibold flex items-center gap-2">
                    {{ $user->name }}
                    <span class="text-xs text-muted-foreground font-normal">(ID: {{ $user->id }})</span>
                    @if($user->has_active_premium) <x-lucide-crown class="w-5 h-5 text-yellow-500" /> @endif
                    @if($user->is_verified) <x-lucide-badge-check class="w-5 h-5 text-blue-500" /> @endif
                </h1>
                <p class="text-sm text-muted-foreground">{{ $user->email }}</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            {{-- Быстрые глобальные действия --}}
            <x-ui.button variant="outline" onclick="window.location.href='mailto:{{ $user->email }}'">
                <x-lucide-mail class="w-4 h-4" /> Email
            </x-ui.button>
        </div>
    </div>
    {{-- МЕНЮ ТАБОВ --}}
    <div class="border-b border-border">
        <nav class="flex gap-4 flex-wrap">
            <button @click="tab = 'profile'; localStorage.setItem('admin_user_tab', 'profile')" :class="tab === 'profile' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                <x-lucide-user class="w-4 h-4 inline mr-1" /> Анкета
            </button>
            <button @click="tab = 'bans'; localStorage.setItem('admin_user_tab', 'bans')" :class="tab === 'bans' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                <x-lucide-shield class="w-4 h-4 inline mr-1" /> Статус и Баны
            </button>
            <button @click="tab = 'photos'; localStorage.setItem('admin_user_tab', 'photos')" :class="tab === 'photos' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                <x-lucide-image class="w-4 h-4 inline mr-1" /> Фото
            </button>
            <button @click="tab = 'finance'; localStorage.setItem('admin_user_tab', 'finance')" :class="tab === 'finance' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                <x-lucide-wallet class="w-4 h-4 inline mr-1" /> Финансы
            </button>
            <button @click="tab = 'social'; localStorage.setItem('admin_user_tab', 'social')" :class="tab === 'social' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                <x-lucide-heart class="w-4 h-4 inline mr-1" /> Социальный граф
            </button>
        </nav>
    </div>

    {{-- КОНТЕНТ ТАБОВ (Подключаем дочерние Volt-компоненты) --}}
    <div class="bg-card border border-border rounded-lg p-6">
        
        <!-- Используем x-show, чтобы компоненты инициализировались один раз и не теряли состояние при переключении -->
        <div x-show="tab === 'profile'">
            <livewire:admin.users.tabs.profile :user="$user" :key="'profile-'.$user->id" />
        </div>

        <div x-show="tab === 'bans'">
            <livewire:admin.users.tabs.status-bans :user="$user" :key="'bans-'.$user->id" />
        </div>

        <div x-show="tab === 'photos'">
            <div class="text-center text-muted-foreground py-12">Компонент фото будет тут</div>
        </div>

        <div x-show="tab === 'finance'">
            <div class="text-center text-muted-foreground py-12">Компонент финансов будет тут</div>
        </div>

        <div x-show="tab === 'social'">
            <div class="text-center text-muted-foreground py-12">Компонент графа будет тут</div>
        </div>

    </div>
</div>