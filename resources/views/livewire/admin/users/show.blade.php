<?php

use App\Models\AdminLog;
use App\Models\Photo;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url; // <--- ДОБАВИЛИ ИМПОРТ
use Livewire\Volt\Component;

new #[Layout('layouts.admin')] class extends Component 
{
    public int $userId;
    
    // ФИКС: Привязываем таб к URL. history: true - записывает каждый переход в историю браузера
    #[Url(as: 'tab', except: 'profile', history: true)]
    public string $activeTab = 'profile';

    private array $allowedTabs = ['profile', 'bans', 'reports','blocks', 'photos', 'photo-comments', 'finance', 'social', 'diaries', 'diary-comments', 'dating', 'finances'];

    public function mount(int $user): void
    {
        $this->userId = $user;
        
        // Защита: если кто-то ввел несуществующий таб в URL, возвращаем на профиль
        if (!in_array($this->activeTab, $this->allowedTabs)) {
            $this->activeTab = 'profile';
        }
    }

    #[Computed]
    public function user(): User
    {
        return User::withTrashed()
            ->with(['profile', 'preferences'])
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

    // ФИКС: Убрали session(), теперь URL обновляется автоматически благодаря #[Url]
    public function setTab(string $tab): void
    {
        if (!in_array($tab, $this->allowedTabs)) return;
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

    public function restoreUser(): void
    {
        $user = $this->user;
        if (!$user->trashed()) return;

        $user->restore();
        $user->update(['status' => 'active']);

        AdminLog::record('user.restore', $user, auth()->user());

        $this->dispatch('show-toast', type: 'success', message: "Пользователь {$user->name} восстановлен");
        $this->refreshUser();
    }
}; 
?>

<div class="space-y-3">
    {{-- ШАПКА ПРОФИЛЯ --}}
    {{-- Обращаемся к $this->user и $this->avatarUrl (Volt автоматически мапит их) --}}
    <div class="flex items-center justify-between flex-wrap gap-4" wire:key="user-header-{{ $this->user->id }}-{{ $this->user->status }}-{{ $this->user->deleted_at }}">
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
                    @if($this->user->is_verified) <x-lucide-badge-check class="w-5 h-5 text-blue-500" /> @endif
                </h1>
                <p class="text-sm text-muted-foreground">{{ $this->user->email }}</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <x-ui.button variant="outline" onclick="window.location.href='mailto:{{ $this->user->email }}'">
                <x-lucide-mail class="w-4 h-4" /> Email
            </x-ui.button>

            <x-ui.dropdown-menu>
                <x-ui.dropdown-menu-trigger>
                    <x-ui.button variant="outline" size="icon">
                        <x-lucide-settings class="w-4 h-4" />
                    </x-ui.button>
                </x-ui.dropdown-menu-trigger>
                <x-ui.dropdown-menu-content align="end">
                    @if($this->user->deleted_at)
                        <x-ui.dropdown-menu-label>Аккаунт удален</x-ui.dropdown-menu-label>
                        <x-ui.dropdown-menu-separator />
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
        </div>
    </div>

        {{-- EMPTY STATE ДЛЯ УДАЛЕННОГО ЮЗЕРА --}}
    @if($this->user->deleted_at)
        <div class="flex flex-col items-center justify-center py-16 text-center border border-dashed border-border rounded-lg bg-card">
            <x-lucide-user-x class="w-16 h-16 text-red-500/20 mb-4" />
            <h2 class="text-xl font-semibold text-foreground">Аккаунт удален</h2>
            <p class="text-sm text-muted-foreground mt-1 max-w-sm">
                Пользователь был деактивирован. Данные сохранены в базе для безопасности, но профиль недоступен для просмотра.
            </p>
            
            <div class="flex items-center gap-3 mt-6">
                <x-ui.button wire:click="restoreUser" variant="default" size="sm" wire:confirm="Восстановить аккаунт пользователя?">
                    <x-lucide-rotate-ccw class="w-4 h-4 mr-2" /> Восстановить аккаунт
                </x-ui.button>
                
                <x-ui.button variant="outline" size="sm" wire:navigate href="{{ route('admin.users.index') }}">
                    <x-lucide-arrow-left class="w-4 h-4 mr-2" /> К списку пользователей
                </x-ui.button>
            </div>
        </div>
    @else
        {{-- МЕНЮ ТАБОВ --}}
        <div class="border-b border-border">
            <nav class="flex gap-x-4 flex-wrap">
                <button wire:click="setTab('profile')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'profile' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                    <x-lucide-user class="w-4 h-4 inline mr-1" /> Анкета
                </button>
                <button wire:click="setTab('bans')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'bans' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                    <x-lucide-shield class="w-4 h-4 inline mr-1" /> Статус и Баны
                </button>
                <button wire:click="setTab('reports')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'reports' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                    <x-lucide-flag class="w-4 h-4 inline mr-1" /> Жалобы
                </button>
                <button wire:click="setTab('blocks')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'blocks' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                    <x-lucide-ban class="w-4 h-4 inline mr-1" /> Блокировки
                </button>
                <button wire:click="setTab('photos')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'photos' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                    <x-lucide-image class="w-4 h-4 inline mr-1" /> Фото
                </button>       
                <button wire:click="setTab('photo-comments')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'photo-comments' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                    <x-lucide-message-square class="w-4 h-4 inline mr-1" /> Комментарии к фото
                </button>    
                <button wire:click="setTab('diaries')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'diaries' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                    <x-lucide-book-open class="w-4 h-4 inline mr-1" /> Дневники
                </button>
                 <button wire:click="setTab('diary-comments')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'diary-comments' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                    <x-lucide-message-square class="w-4 h-4 inline mr-1" /> Комментарии к дневникам
                </button>
                <button wire:click="setTab('dating')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'dating' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                    <x-lucide-wallet class="w-4 h-4 inline mr-1" /> Знакомства
                </button>
                <button wire:click="setTab('finances')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'finances' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                    <x-lucide-wallet class="w-4 h-4 inline mr-1" /> Финансы
                </button>
                <button wire:click="setTab('social')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'social' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                    <x-lucide-heart class="w-4 h-4 inline mr-1" /> Социальный граф
                </button>
            </nav>
        </div>

                {{-- КОНТЕНТ ТАБОВ --}}
         <div class="bg-card border border-border rounded-lg p-6">
            @if($activeTab === 'profile')
                <livewire:admin.users.tabs.profile :userId="$this->userId" :key="'profile-'.$this->userId" />
            @elseif($activeTab === 'bans')
                <livewire:admin.users.tabs.status-bans :userId="$this->userId" :key="'bans-'.$this->userId" />
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
            {{--  @elseif($activeTab === 'social') 
                <div class="text-center text-muted-foreground py-12">Компонент графа будет тут</div> --}}
            @endif
        </div> 
    @endif
</div>