<?php

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.admin')] class extends Component 
{
    public User $user;
    public string $activeTab = 'profile';

    public function mount(User $user): void
    {
        $this->user = $user;
        
        // Восстанавливаем активный таб из сессии
        $this->activeTab = session('admin_user_tab', 'profile');
        
        // Грузим связи для первого рендера
        $this->loadUserData();
    }

    /**
     * Хук Livewire: вызывается при каждом запросе (клике).
     * Восстанавливает потерянные при сериализации связи.
     */
    public function hydrate(): void
    {
        $this->loadUserData();
    }

    /**
     * Жадная загрузка связей юзера для шапки.
     */
    private function loadUserData(): void
    {
        $this->user->load([
            'profile',
            'preferences',
            'photos' => fn($q) => $q->where('status', 'approved')->orderBy('is_primary', 'desc')->limit(1)
        ]);
    }

    /**
     * Переключение таба с сохранением в сессию.
     */
    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        session(['admin_user_tab' => $tab]);
    }

    /**
     * Слушаем события от дочерних компонентов (например, если забанили в табе).
     */
    #[On('user-updated')] 
    public function refreshUser(): void
    {
        $this->user->refresh();
        $this->loadUserData();
    }
}; 
?>

<div class="space-y-6">
    {{-- ШАПКА ПРОФИЛЯ --}}
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-4">
            @php
                // Защита от зацикливания кнопки "Назад"
                $previousUrl = url()->previous();
                $backUrl = ($previousUrl && $previousUrl !== url()->current()) 
                    ? $previousUrl 
                    : route('admin.users.index');
            @endphp

            <a href="{{ $backUrl }}" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            
            <x-avatar src="{{ $user->avatar_url }}" name="{{ $user->name }}" size="lg" userId="{{ $user->id }}" showStatus="true" :isOnline="$user->is_online" />
            <div>
                <h1 class="text-2xl font-semibold flex items-center gap-2">
                    <x-user-status-sign :user="$user" />
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
            <button wire:click="setTab('profile')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'profile' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                <x-lucide-user class="w-4 h-4 inline mr-1" /> Анкета
            </button>
            <button wire:click="setTab('bans')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'bans' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                <x-lucide-shield class="w-4 h-4 inline mr-1" /> Статус и Баны
            </button>
             <button wire:click="setTab('reports')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'reports' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                <x-lucide-flag class="w-4 h-4 inline mr-1" /> Жалобы
            </button>
            <button wire:click="setTab('photos')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'photos' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                <x-lucide-image class="w-4 h-4 inline mr-1" /> Фото
            </button>       
            <button wire:click="setTab('comments')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'comments' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                <x-lucide-message-square class="w-4 h-4 inline mr-1" /> Комментарии
            </button>    
            <button wire:click="setTab('finance')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'finance' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                <x-lucide-wallet class="w-4 h-4 inline mr-1" /> Финансы
            </button>
            <button wire:click="setTab('social')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'social' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                <x-lucide-heart class="w-4 h-4 inline mr-1" /> Социальный граф
            </button>
        </nav>
    </div>

    {{-- КОНТЕНТ ТАБОВ --}}
    <div class="bg-card border border-border rounded-lg p-6">
        
        {{-- Используем @if для ленивой инициализации компонентов --}}
        @if($activeTab === 'profile')
            <livewire:admin.users.tabs.profile :user="$user" :key="'profile-'.$user->id" />
        @elseif($activeTab === 'bans')
            <livewire:admin.users.tabs.status-bans :user="$user" :key="'bans-'.$user->id" />
        @elseif($activeTab === 'reports')
            <livewire:admin.users.tabs.reports :user="$user" :key="'reports-'.$user->id" />                    
        @elseif($activeTab === 'photos')        
            <livewire:admin.users.tabs.photos :user="$user" :key="'photos-'.$user->id" />
        @elseif($activeTab === 'comments')
            <livewire:admin.users.tabs.comments :user="$user" :key="'comments-'.$user->id" />
        @elseif($activeTab === 'finance')
            <div class="text-center text-muted-foreground py-12">Компонент финансов будет тут</div>
        @elseif($activeTab === 'social')
            <div class="text-center text-muted-foreground py-12">Компонент графа будет тут</div>
        @endif

    </div>
</div>