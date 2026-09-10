<?php

use App\Actions\Admin\DeleteUserAction;
use App\Models\Photo;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.admin')] class extends Component 
{
    public int $userId;
    
    #[Url(as: 'tab', except: 'profile', history: true)]
    public string $activeTab = 'profile';

        public function mount(int $user): void
    {
        // Переводим $user из маршрута в $userId для свойства компонента
        $this->userId = $user;
        
        if (!in_array($this->activeTab, $this->allowedTabs)) {
            $this->activeTab = 'profile';
        }
    }

   #[Computed]
    public function allowedTabs(): array
    {
        $currentAdmin = auth()->user();
        $tabs = ['profile'];

        if (in_array($currentAdmin->role, ['moderator', 'admin'])) {
            array_push($tabs, 'reports', 'blocks', 'bans', 'photos', 'photo-comments', 'diaries', 'diary-comments', 'dating', 'chats', 'events');
        }

        if ($currentAdmin->role === 'admin') {
            array_push($tabs, 'sessions', 'admin-logs', 'finances', 'gifts', 'broadcasts', 'cards', 'promocodes', 'auth-logs');
        }

        return $tabs;
    }

    public function canSeeTab(string $tab): bool
    {
        return in_array($tab, $this->allowedTabs);
    }

    #[Computed]   
    public function user(): User
    {
        return User::withTrashed()
            ->with(['profile.city.state', 'profile.country', 'preferences'])
            ->findOrFail($this->userId);
    }

    #[Computed]
    public function avatarUrl(): string
    {
        $photo = Photo::where('user_id', $this->userId)
            ->where('status', 'approved')
            ->where('type', 'profile')
            ->orderBy('is_primary', 'desc')
            ->orderBy('position', 'asc')
            ->first();

        return $photo?->thumb_url ?: '';
    }

    public function setTab(string $tab): void
    {
        if (!$this->canSeeTab($tab)) return;
        $this->activeTab = $tab;
    }

    #[On('user-updated')] 
    #[On('user-action-performed')]
    public function refreshUser(): void
    {
        unset($this->user);
        unset($this->avatarUrl);
    }

    public function openBanModal(string $banType): void
    {
        $this->dispatch('open-ban-modal', userIds: [$this->userId], banType: $banType)->to('admin.ban-user-modal');
    }

    public function openDeleteModal(): void
    {
        $this->dispatch('open-delete-modal', userId: $this->userId)->to('admin.delete-user-modal');
    }

    public function toggleBan(): void
    {
        $action = app(\App\Actions\Admin\ToggleUserBanAction::class);
        $result = $action->execute($this->user, 'Снят бан модератором');
        
        $this->dispatch('show-toast', type: $result['success'] ? 'success' : 'error', message: $result['message']);
        $this->refreshUser();
    }

    public function restoreUser(DeleteUserAction $action): void
    {
        $user = $this->user;
        if (!$user->trashed()) return;

        $action->restore($user, auth()->user());

        $this->dispatch('show-toast', type: 'success', message: "Пользователь {$user->name} восстановлен");
        $this->refreshUser();
    }
}; 
?>

<div class="space-y-3">
    {{-- ШАПКА ПРОФИЛЯ --}}
    <!-- ФИКС: Статичный wire:key на обертке, чтобы не моргала вся шапка -->
    <div class="flex items-center justify-between flex-wrap gap-4" wire:key="user-header-{{ $this->user->id }}">
        <div class="flex items-center gap-4">
            @php
                $previousUrl = url()->previous();
                $backUrl = ($previousUrl && $previousUrl !== url()->current()) 
                    ? $previousUrl 
                    : route('admin.users.index');
            @endphp

            <a href="{{ $backUrl }}" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            
            <x-avatar src="{{ $this->avatarUrl }}" name="{{ $this->user->name }}" size="lg" userId="{{ $this->user->id }}" showStatus="true" :isOnline="$this->user->is_online" />
            <div>
                <h1 class="text-2xl font-semibold flex items-center gap-2">
                    <x-user-status-sign :user="$this->user" />
                    {{ $this->user->name }}
                    <span class="text-xs text-muted-foreground font-normal">(ID: {{ $this->user->id }})</span>
                    @if($this->user->has_active_premium) <x-lucide-crown class="w-5 h-5 text-yellow-500" /> @endif                  
                </h1>
                <p class="text-sm text-muted-foreground flex items-center gap-1.5 flex-wrap">
                    <span>{{ $this->user->email }}</span>
                    @if($this->user->profile?->city?->name || $this->user->profile?->country?->name)
                        <span class="text-border">•</span>
                        <span class="flex items-center gap-1">
                            <x-lucide-map-pin class="w-3 h-3" />
                            @if($this->user->profile?->city?->name)
                                {{ $this->user->profile->city->name }}
                            @endif
                            @if($this->user->profile?->city?->state?->name)
                                , {{ $this->user->profile->city->state->name }}
                            @endif
                            @if($this->user->profile?->country?->name)
                                , {{ $this->user->profile->country->name }}
                            @endif
                        </span>
                    @endif
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="mailto:{{ $this->user->email }}" class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-9 px-4 py-2">
                <x-lucide-mail class="w-4 h-4" /> Email
            </a>

             @if(in_array(auth()->user()->role, ['admin', 'moderator']))
                <!-- ФИКС: Динамический wire:key ТОЛЬКО на дропдауне, чтобы сбрасывать Alpine -->
                <x-ui.dropdown-menu wire:key="header-actions-{{ $this->user->id }}-{{ $this->user->status }}">
                    <x-ui.dropdown-menu-trigger>
                        <x-ui.button variant="outline" size="icon">
                            <x-lucide-settings class="w-4 h-4" />
                        </x-ui.button>
                    </x-ui.dropdown-menu-trigger>
                    <x-ui.dropdown-menu-content align="end">
                        @if($this->user->deleted_at)
                            <x-ui.dropdown-menu-label>Аккаунт удален</x-ui.dropdown-menu-label>
                            <x-ui.dropdown-menu-separator />
                            <x-ui.dropdown-menu-item wire:click="restoreUser" wire:confirm="Восстановить аккаунт пользователя?">
                                <x-lucide-rotate-ccw class="w-4 h-4 text-green-500" /> Восстановить
                            </x-ui.dropdown-menu-item>
                            <x-ui.dropdown-menu-item href="{{ route('admin.users.index') }}" wire:navigate>
                                <x-lucide-arrow-left class="w-4 h-4" /> Назад к списку
                            </x-ui.dropdown-menu-item>
                        @else
                            <x-ui.dropdown-menu-label>Действия</x-ui.dropdown-menu-label>
                            <x-ui.dropdown-menu-separator />

                            @if($this->user->status === 'banned' || $this->user->status === 'shadowbanned')
                                <x-ui.dropdown-menu-item wire:click="toggleBan" wire:confirm="Снять бан с пользователя?">
                                    <x-lucide-unlock class="w-4 h-4 text-green-500" /> Разбанить
                                </x-ui.dropdown-menu-item>
                            @else
                                <x-ui.dropdown-menu-item wire:click="openBanModal('shadow')">
                                    <x-lucide-eye-off class="w-4 h-4 text-purple-500" /> Теневой бан...
                                </x-ui.dropdown-menu-item>
                                <x-ui.dropdown-menu-item wire:click="openBanModal('temp')">
                                    <x-lucide-clock class="w-4 h-4 text-yellow-500" /> Бан на 3 дня...
                                </x-ui.dropdown-menu-item>
                                <x-ui.dropdown-menu-item wire:click="openBanModal('permanent')">
                                    <x-lucide-lock class="w-4 h-4 text-red-500" /> Вечный бан...
                                </x-ui.dropdown-menu-item>
                            @endif

                            <x-ui.dropdown-menu-separator />
                            <x-ui.dropdown-menu-item wire:click="openDeleteModal" variant="destructive">
                                <x-lucide-trash-2 class="w-4 h-4" /> Удалить...
                            </x-ui.dropdown-menu-item>
                        @endif
                    </x-ui.dropdown-menu-content>
                </x-ui.dropdown-menu>
            @endif
        </div>
    </div>

    {{-- ОБЕРТКА ДЛЯ ТАБОВ И КОНТЕНТА С ОВЕРЛЕЕМ --}}
    <div class="relative">
        
        @if($this->user->deleted_at)
            <div x-data="{ showOverlay: true }" x-show="showOverlay" x-transition.opacity
                 class="fixed left-[16rem] top-[4rem] right-0 bottom-0 z-20 flex flex-col items-center justify-center pointer-events-none bg-blue-500/5 backdrop-blur-[1px] rounded-lg pb-12">
                
                <div class="relative flex flex-col items-center gap-2 p-6 pointer-events-auto bg-card/95 border border-dashed border-border rounded-xl shadow-2xl text-center max-w-sm">
                    
                    <button @click="showOverlay = false" class="absolute top-3 right-3 p-1 rounded-md text-muted-foreground hover:bg-accent hover:text-foreground transition-colors" title="Закрыть и просмотреть данные">
                        <x-lucide-x class="w-4 h-4" />
                    </button>

                    <x-lucide-snowflake class="w-12 h-12 text-blue-500 animate-pulse" />
                    <span class="font-bold text-lg text-foreground">Аккаунт деактивирован</span>
                    <p class="text-sm text-muted-foreground">
                        Пользователь заморожен. Данные доступны для просмотра и копирования.
                    </p>
                    <div class="flex items-center gap-3 mt-4">
                        <x-ui.button wire:click="restoreUser" variant="default" size="sm" wire:confirm="Восстановить аккаунт пользователя?">
                            <x-lucide-rotate-ccw class="w-4 h-4 mr-2" /> Восстановить аккаунт
                        </x-ui.button>
                        <x-ui.button variant="outline" size="sm" wire:navigate href="{{ route('admin.users.index') }}">
                            <x-lucide-arrow-left class="w-4 h-4 mr-2" /> К списку
                        </x-ui.button>
                    </div>
                </div>
            </div>
        @endif

        {{-- МЕНЮ ТАБОВ --}}
        <div class="border-b border-border">
            <nav class="flex gap-x-4 flex-wrap">
                @if($this->canSeeTab('profile'))
                    <button wire:click="setTab('profile')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'profile' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                        <x-lucide-user class="w-4 h-4 inline mr-1" /> Анкета
                    </button>
                @endif

                @if($this->canSeeTab('bans'))
                    <button wire:click="setTab('bans')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'bans' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                        <x-lucide-shield class="w-4 h-4 inline mr-1" /> Статус и Баны
                    </button>
                @endif

                @if($this->canSeeTab('sessions'))
                    <button wire:click="setTab('sessions')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'sessions' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                        <x-lucide-shield-check class="w-4 h-4 inline mr-1" /> Сессии
                    </button>
                @endif

                @if($this->canSeeTab('reports'))
                    <button wire:click="setTab('reports')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'reports' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                        <x-lucide-flag class="w-4 h-4 inline mr-1" /> Жалобы
                    </button>
                @endif

                @if($this->canSeeTab('blocks'))
                    <button wire:click="setTab('blocks')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'blocks' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                        <x-lucide-ban class="w-4 h-4 inline mr-1" /> Блокировки
                    </button>
                @endif

                @if($this->canSeeTab('photos'))
                    <button wire:click="setTab('photos')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'photos' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                        <x-lucide-image class="w-4 h-4 inline mr-1" /> Фото
                    </button>
                @endif       

                @if($this->canSeeTab('photo-comments'))
                    <button wire:click="setTab('photo-comments')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'photo-comments' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                        <x-lucide-message-square class="w-4 h-4 inline mr-1" /> Комм. фото
                    </button>
                @endif    

                @if($this->canSeeTab('diaries'))
                    <button wire:click="setTab('diaries')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'diaries' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                        <x-lucide-book-open class="w-4 h-4 inline mr-1" /> Дневники
                    </button>
                @endif

                @if($this->canSeeTab('diary-comments'))
                    <button wire:click="setTab('diary-comments')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'diary-comments' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                        <x-lucide-message-square class="w-4 h-4 inline mr-1" /> Комм. дневников
                    </button>
                @endif

                @if($this->canSeeTab('dating'))
                    <button wire:click="setTab('dating')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'dating' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                        <x-lucide-heart class="w-4 h-4 inline mr-1" /> Знакомства
                    </button>
                @endif

                @if($this->canSeeTab('finances'))
                    <button wire:click="setTab('finances')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'finances' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                        <x-lucide-wallet class="w-4 h-4 inline mr-1" /> Финансы
                    </button>
                @endif     

                @if($this->canSeeTab('chats'))
                    <button wire:click="setTab('chats')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'chats' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                        <x-lucide-message-circle class="w-4 h-4 inline mr-1" /> Чаты
                    </button>
                @endif

                @if($this->canSeeTab('gifts'))
                    <button wire:click="setTab('gifts')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'gifts' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                        <x-lucide-gift class="w-4 h-4 inline mr-1" /> Подарки
                    </button>
                @endif

                @if($this->canSeeTab('broadcasts'))
                    <button wire:click="setTab('broadcasts')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'broadcasts' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                        <x-lucide-bell class="w-4 h-4 inline mr-1" /> Уведомления
                    </button>
                @endif

                @if($this->canSeeTab('admin-logs'))
                    <button wire:click="setTab('admin-logs')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'admin-logs' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                        <x-lucide-history class="w-4 h-4 inline mr-1" /> Логи админов
                    </button>
                @endif

                @if($this->canSeeTab('cards'))
                    <button wire:click="setTab('cards')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'cards' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                        <x-lucide-credit-card class="w-4 h-4 inline mr-1" /> Карты
                    </button>
                @endif

                @if($this->canSeeTab('promocodes'))
                    <button wire:click="setTab('promocodes')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'promocodes' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                        <x-lucide-ticket class="w-4 h-4 inline mr-1" /> Промокоды
                    </button>
                @endif

                @if($this->canSeeTab('events'))
                    <button wire:click="setTab('events')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'events' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                        <x-lucide-rss class="w-4 h-4 inline mr-1" /> Лента событий
                    </button>
                @endif

                @if($this->canSeeTab('auth-logs'))
                    <button wire:click="setTab('auth-logs')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'auth-logs' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                        <x-lucide-shield-alert class="w-4 h-4 inline mr-1" /> История входов
                    </button>
                @endif
            </nav>
        </div>

        {{-- КОНТЕНТ ТАБОВ --}}
        <div class="relative bg-card border border-border rounded-lg p-6 mt-4 min-h-[400px]">
            
            {{-- НОВЫЙ КОМПОНЕНТ СПИННЕРА --}}
            <x-loading-overlay fixed="true" wire:loading.delay wire:key="overlay-loading-page"/>

            @if($activeTab === 'profile')
                <livewire:admin.users.tabs.profile :userId="$this->userId" :key="'profile-'.$this->userId" />
            @elseif($activeTab === 'bans')
                <livewire:admin.users.tabs.status-bans :userId="$this->userId" :key="'bans-'.$this->userId" />
            @elseif($activeTab === 'sessions')
                <livewire:admin.users.tabs.sessions :userId="$this->userId" :key="'sessions-'.$this->userId" />
            @elseif($activeTab === 'reports')
                <livewire:admin.users.tabs.reports :userId="$this->userId" :key="'reports-'.$this->userId" />                    
            @elseif($activeTab === 'blocks')
                <livewire:admin.users.tabs.blocks :userId="$this->userId" :key="'blocks-'.$this->userId" />
            @elseif($activeTab === 'photos')        
                <livewire:admin.users.tabs.photos :userId="$this->userId" :key="'photos-'.$this->userId" />
            @elseif($activeTab === 'photo-comments')
                <livewire:admin.users.tabs.photo-comments :userId="$this->userId" :key="'photo-comments-'.$this->userId" />
            @elseif($activeTab === 'diaries')
                <livewire:admin.users.tabs.diaries :userId="$this->userId" :key="'diaries-'.$this->userId" />
            @elseif($activeTab === 'diary-comments')
                <livewire:admin.users.tabs.diary-comments :userId="$this->userId" :key="'diary-comments-'.$this->userId" />
            @elseif($activeTab === 'dating')
                <livewire:admin.users.tabs.dating :userId="$this->userId" :key="'dating-'.$this->userId" />
            @elseif($activeTab === 'finances')
                <livewire:admin.users.tabs.finances :userId="$this->userId" :key="'finances-'.$this->userId" />
            @elseif($activeTab === 'chats')
                <livewire:admin.users.tabs.chats :userId="$this->userId" :key="'chats-'.$this->userId" />
            @elseif($activeTab === 'gifts')
                <livewire:admin.users.tabs.gifts :userId="$this->userId" :key="'gifts-'.$this->userId" />
            @elseif($activeTab === 'broadcasts')
                <livewire:admin.users.tabs.broadcasts :userId="$this->userId" :key="'broadcasts-'.$this->userId" />
            @elseif($activeTab === 'admin-logs')
                <livewire:admin.users.tabs.admin-logs :userId="$this->userId" :key="'admin-logs-'.$this->userId" />
            @elseif($activeTab === 'cards')
                <livewire:admin.users.tabs.cards :userId="$this->userId" :key="'cards-'.$this->userId" />
            @elseif($activeTab === 'promocodes')
                <livewire:admin.users.tabs.promocodes :userId="$this->userId" :key="'promocodes-'.$this->userId" />
            @elseif($activeTab === 'events')
                <livewire:admin.users.tabs.events :userId="$this->userId" :key="'events-'.$this->userId" />
            @elseif($activeTab === 'auth-logs')
                <livewire:admin.users.tabs.auth-logs :userId="$this->userId" :key="'auth-logs-'.$this->userId" />        
            @endif
        </div>
    </div>
</div>