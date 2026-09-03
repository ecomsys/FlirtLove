<?php

use App\Actions\Admin\ToggleUserBanAction;
use App\Actions\Admin\DeleteUserAction;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    public int $perPage = 15;
    
    #[Url(as: 'q', except: '')]
    public string $search = '';
    
    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';
    
    #[Url(as: 'gender', except: '')]
    public string $genderFilter = '';
    
    #[Url(as: 'vip', except: '')]
    public string $premiumFilter = '';

    public string $sortDirection = 'desc';
    
    public array $selectedUsers = [];
    public bool $selectAll = false;

    private ToggleUserBanAction $toggleUserBanAction;
    private DeleteUserAction $deleteUserAction;

    public function boot(ToggleUserBanAction $toggleUserBanAction, DeleteUserAction $deleteUserAction): void
    {
        $this->toggleUserBanAction = $toggleUserBanAction;
        $this->deleteUserAction = $deleteUserAction;
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingGenderFilter(): void { $this->resetPage(); }
    public function updatingPremiumFilter(): void { $this->resetPage(); }
    public function updatingPerPage(): void { $this->resetPage(); }

    #[On('user-action-performed')]
    public function refreshUsers(): void
    {
        unset($this->users);
        unset($this->stats);
        $this->selectedUsers = [];
        $this->selectAll = false;
    }

    public function openBanModal(int $userId, string $banType): void
    {
        // Защита от саппорта
        if (!in_array(auth()->user()->role, ['admin', 'moderator'])) {
            $this->dispatch('show-toast', type: 'error', message: 'У вас нет прав для этого действия.');
            return;
        }
        $this->dispatch('open-ban-modal', userIds: [$userId], banType: $banType)->to('admin.ban-user-modal');
    }

    public function openMassBanModal(string $banType): void
    {
        // Защита от саппорта
        if (!in_array(auth()->user()->role, ['admin', 'moderator'])) {
            $this->dispatch('show-toast', type: 'error', message: 'У вас нет прав для этого действия.');
            return;
        }

        if (empty($this->selectedUsers)) {
            $this->dispatch('show-toast', type: 'error', message: 'Выберите хотя бы одного юзера');
            return;
        }
        $this->dispatch('open-ban-modal', userIds: $this->selectedUsers, banType: $banType)->to('admin.ban-user-modal');
    }

    public function openDeleteModal(int $userId): void
    {
        // Защита от саппорта
        if (!in_array(auth()->user()->role, ['admin', 'moderator'])) {
            $this->dispatch('show-toast', type: 'error', message: 'У вас нет прав для этого действия.');
            return;
        }
        $this->dispatch('open-delete-modal', userId: $userId)->to('admin.delete-user-modal');
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->genderFilter = '';
        $this->premiumFilter = '';
        $this->resetPage();
    }

    public function toggleSort(): void
    {
        $this->sortDirection = $this->sortDirection === 'desc' ? 'asc' : 'desc';
        $this->resetPage();
    }

    public function updatedSelectAll($value): void
    {
        if ($value) {
            $this->selectedUsers = $this->users->pluck('id')->map(fn($id) => (string) $id)->toArray();
        } else {
            $this->selectedUsers = [];
        }
    }

    #[Computed]
    public function users()
    {
        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
        $search = trim($this->search);

        return User::query()
            ->select(['id', 'name', 'email', 'created_at', 'last_seen', 'last_login_ip', 'status', 'is_premium', 'premium_expires_at', 'is_verified', 'has_completed_onboarding', 'deleted_at'])
            ->excludeStaff()
            ->withTrashed()
            ->with([
                'profile',
                'photos' => fn($q) => $q->select(['id', 'user_id', 'path_thumb', 'is_primary', 'status', 'position', 'type'])
                                        ->where('status', 'approved')
                                        ->where('type', 'profile')
                                        ->orderBy('is_primary', 'desc')
                                        ->orderBy('position', 'asc')
                                        ->limit(1)
            ])
            ->when($search, function ($query) use ($search, $operator) {
                $query->where(function ($q) use ($search, $operator) {
                    $q->where('name', $operator, "%{$search}%")
                      ->orWhere('email', $operator, "%{$search}%");
                    
                    if (is_numeric($search)) {
                        $q->orWhere('id', (int) $search);
                    }
                    
                    $q->orWhereHas('profile', function ($sub) use ($search, $operator) {
                        $sub->where('city', $operator, "%{$search}%");
                    });
                });
            })
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->genderFilter, fn($q) => $q->whereHas('profile', fn($sub) => $sub->where('gender', $this->genderFilter)))
            ->when($this->premiumFilter === 'yes', fn($q) => $q->where('is_premium', true))
            ->when($this->premiumFilter === 'no', fn($q) => $q->where('is_premium', false))
            ->orderBy('created_at', $this->sortDirection)
            ->orderBy('id', $this->sortDirection)
            ->paginate($this->perPage);
    }

    #[Computed]
    public function stats(): array
    {
        $total = User::excludeStaff()->count();
        $statusCounts = User::excludeStaff()
            ->selectRaw("status, count(*) as aggregate")
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $premium = User::excludeStaff()->where('is_premium', true)->count();
        $verified = User::excludeStaff()->where('is_verified', true)->count();

        return [
            'total' => $total,
            'banned' => $statusCounts['banned'] ?? 0,
            'shadowbanned' => $statusCounts['shadowbanned'] ?? 0,
            'premium' => $premium,
            'verified' => $verified,
        ];
    }

    public function toggleBan(int $userId): void
    {
        // Защита от саппорта
        if (!in_array(auth()->user()->role, ['admin', 'moderator'])) {
            $this->dispatch('show-toast', type: 'error', message: 'У вас нет прав для этого действия.');
            return;
        }

        $user = User::find($userId);
        if (!$user) return;

        $result = $this->toggleUserBanAction->execute($user, 'Снят бан модератором');
        $this->dispatch('show-toast', type: $result['success'] ? 'success' : 'error', message: $result['message']);
        $this->refreshUsers();
    }

    public function restoreUser(int $userId): void
    {
        // Защита от саппорта
        if (!in_array(auth()->user()->role, ['admin', 'moderator'])) {
            $this->dispatch('show-toast', type: 'error', message: 'У вас нет прав для этого действия.');
            return;
        }

        $user = User::withTrashed()->find($userId);
        if (!$user || !$user->trashed()) return;

        $this->deleteUserAction->restore($user, auth()->user());
        $this->dispatch('show-toast', type: 'success', message: "Пользователь {$user->name} восстановлен");
        $this->refreshUsers();
    }
}; 
?>


<div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            @php
                $previousUrl = url()->previous();
                $backUrl = ($previousUrl && $previousUrl !== url()->current()) 
                    ? $previousUrl 
                    : route('admin.dashboard');
            @endphp

            <a href="{{ $backUrl }}" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-semibold flex items-center gap-2">
                    <x-lucide-users class="w-6 h-6" />
                    Пользователи 
                    <span class="text-sm font-normal text-muted-foreground">(всего: {{ $this->users->total() }})</span>
                </h1>
                <p class="text-sm text-muted-foreground">Управление аккаунтами, баны и модерация</p>
            </div>
        </div>

        <x-ui.select wire:model.live="perPage">
            <x-ui.select-trigger class="w-[10rem]"><x-ui.select-value placeholder="Показывать: 15" /></x-ui.select-trigger>
            <x-ui.select-content>
                <x-ui.select-item value="15">Показывать: 15</x-ui.select-item>
                <x-ui.select-item value="25">Показывать: 25</x-ui.select-item>
                <x-ui.select-item value="50">Показывать: 50</x-ui.select-item>
            </x-ui.select-content>
        </x-ui.select>
    </div>

    <div class="flex justify-between items-center gap-4">
        <!-- Кнопка массовых действий (ТОЛЬКО ДЛЯ АДМИНОВ И МОДЕРАТОРОВ) -->
        <div class="block">
            @if(in_array(auth()->user()->role, ['admin', 'moderator']))
                @if(!empty($this->selectedUsers))                
                    <x-ui.dropdown-menu>
                        <x-ui.dropdown-menu-trigger>
                            <x-ui.button variant="outline" size="md" class="gap-2">
                                <x-lucide-zap class="w-4 h-4" />
                                Действия ({{ count($this->selectedUsers) }})
                                <x-lucide-chevron-down class="w-4 h-4 transition-transform duration-200" />
                            </x-ui.button>
                        </x-ui.dropdown-menu-trigger>
                        <x-ui.dropdown-menu-content align="end">
                            <x-ui.dropdown-menu-label>Массовые действия</x-ui.dropdown-menu-label>
                            <x-ui.dropdown-menu-separator />
                            <x-ui.dropdown-menu-item wire:click="openMassBanModal('shadow')">
                                <x-lucide-eye-off class="w-4 h-4 text-purple-500" /> Теневой бан
                            </x-ui.dropdown-menu-item>
                            <x-ui.dropdown-menu-item wire:click="openMassBanModal('temp')">
                                <x-lucide-clock class="w-4 h-4 text-yellow-500" /> Бан на 3 дня
                            </x-ui.dropdown-menu-item>
                            <x-ui.dropdown-menu-item wire:click="openMassBanModal('permanent')" variant="destructive">
                                <x-lucide-lock class="w-4 h-4 text-red-500" /> Вечный бан
                            </x-ui.dropdown-menu-item>
                        </x-ui.dropdown-menu-content>
                    </x-ui.dropdown-menu>        
                @endif
            @endif
        </div>

        <!-- Фильтры и Поиск -->
        <div class="flex items-center gap-3 flex-wrap justify-end">
            @if(!empty($search) || !empty($statusFilter) || !empty($genderFilter) || !empty($premiumFilter))
                <x-ui.button wire:click="resetFilters" variant="ghost" size="sm" class="gap-2 text-muted-foreground hover:text-foreground">
                    <x-lucide-filter-x class="w-4 h-4" />
                    Сбросить
                </x-ui.button>
            @endif           

            <x-ui.select wire:model.live="statusFilter" wire:key="status-filter-{{ $statusFilter }}">
                <x-ui.select-trigger class="w-[9rem]"><x-ui.select-value placeholder="Все статусы" /></x-ui.select-trigger>
                <x-ui.select-content>
                    <x-ui.select-item value="">Все статусы</x-ui.select-item>
                    <x-ui.select-item value="active">Активные</x-ui.select-item>
                    <x-ui.select-item value="banned">Забанены</x-ui.select-item>
                    <x-ui.select-item value="shadowbanned">Теневой бан</x-ui.select-item>
                    <x-ui.select-item value="deactivated">Деактивированы</x-ui.select-item>
                </x-ui.select-content>
            </x-ui.select>

            <x-ui.select wire:model.live="genderFilter" wire:key="gender-filter-{{ $genderFilter }}">
                <x-ui.select-trigger class="w-[9rem]"><x-ui.select-value placeholder="Любой пол" /></x-ui.select-trigger>
                <x-ui.select-content>
                    <x-ui.select-item value="">Любой пол</x-ui.select-item>
                    <x-ui.select-item value="male">Мужчины</x-ui.select-item>
                    <x-ui.select-item value="female">Женщины</x-ui.select-item>
                </x-ui.select-content>
            </x-ui.select>

            <x-ui.select wire:model.live="premiumFilter" wire:key="premium-filter-{{ $premiumFilter }}">
                <x-ui.select-trigger class="w-[9rem]"><x-ui.select-value placeholder="Все (VIP)" /></x-ui.select-trigger>
                <x-ui.select-content>
                    <x-ui.select-item value="">Все (VIP)</x-ui.select-item>
                    <x-ui.select-item value="yes">С VIP</x-ui.select-item>
                    <x-ui.select-item value="no">Без VIP</x-ui.select-item>
                </x-ui.select-content>
            </x-ui.select>

            <div class="relative w-72">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Имя, почта, ID, город..." class="pl-9 pr-8" />
                <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                @if(!empty($search))
                    <button wire:click="$set('search', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Таблица -->
    <x-ui.table>
        <x-ui.table-header>
            <x-ui.table-row>
                <!-- Чекбокс "Выбрать все" (ТОЛЬКО ДЛЯ АДМИНОВ И МОДЕРАТОРОВ) -->
                @if(in_array(auth()->user()->role, ['admin', 'moderator']))
                    <x-ui.table-head class="w-10">
                        <x-checkbox wire:model.live="selectAll" />                    
                    </x-ui.table-head>
                @endif
                
                <x-ui.table-head class="w-12">ID</x-ui.table-head>
                <x-ui.table-head class="w-12">Фото</x-ui.table-head>
                <x-ui.table-head>Имя / Email</x-ui.table-head>
                <x-ui.table-head>Пол</x-ui.table-head>
                <x-ui.table-head>Город</x-ui.table-head>
                <x-ui.table-head>
                    <button wire:click="toggleSort" class="flex items-center gap-1 hover:text-foreground transition-colors">
                        Регистрация
                        @if($sortDirection === 'desc') <x-lucide-chevron-down class="w-3 h-3" /> @else <x-lucide-chevron-up class="w-3 h-3" /> @endif
                    </button>
                </x-ui.table-head>
                <x-ui.table-head>Статус</x-ui.table-head>
                <x-ui.table-head class="w-10 text-right"><span class="sr-only">Действия</span></x-ui.table-head>
            </x-ui.table-row>
        </x-ui.table-header>

        <x-ui.table-body>
            @forelse ($this->users as $user)
                <x-ui.table-row wire:key="user-{{ $user->id }}-{{ $user->status }}" class="{{ $user->status !== 'active' ? 'opacity-60 bg-muted/30' : '' }}">
                    
                    <!-- Чекбокс выбора (ТОЛЬКО ДЛЯ АДМИНОВ И МОДЕРАТОРОВ) -->
                    @if(in_array(auth()->user()->role, ['admin', 'moderator']))
                        <x-ui.table-cell>
                            <x-checkbox value="{{ $user->id }}" wire:model.live="selectedUsers" />                        
                        </x-ui.table-cell>
                    @endif
                    
                    <x-ui.table-cell class="text-muted-foreground text-xs">#{{ $user->id }}</x-ui.table-cell>
                    <x-ui.table-cell>
                        <x-avatar src="{{ $user->avatar_url }}" name="{{ $user->name }}" size="sm" userId="{{ $user->id }}" showStatus="true" :isOnline="$user->is_online"/>
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        <a href="{{ route('admin.users.show', $user->id) }}" class="block group" wire:navigate>
                            <div class="font-medium text-foreground flex items-center gap-2 flex-wrap group-hover:text-primary transition-colors">
                                <x-user-status-sign :user="$user" />
                                {{ $user->name }}                                
                                @if($user->has_active_premium)<x-lucide-crown class="w-3.5 h-3.5 text-yellow-500" />@endif                                                   
                            </div>
                            <div class="text-xs text-muted-foreground">{{ $user->email }}</div>
                        </a>
                    </x-ui.table-cell>
                    <x-ui.table-cell class="text-xs">
                        @if($user->profile?->gender === 'male') <span class="text-blue-500">М</span>
                        @elseif($user->profile?->gender === 'female') <span class="text-pink-500">Ж</span>
                        @else —
                        @endif
                    </x-ui.table-cell>
                    <x-ui.table-cell class="text-xs">{{ $user->profile?->city ?? '—' }}</x-ui.table-cell>
                    <x-ui.table-cell class="text-muted-foreground text-xs whitespace-nowrap">
                        {{ $user->created_at->format('d.m.Y') }}
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        @php 
                            $statusBadge = match($user->status) {
                                'active' => ['variant' => 'success', 'label' => 'Активен'],
                                'banned' => ['variant' => 'destructive', 'label' => 'Бан'],
                                'shadowbanned' => ['variant' => 'warning', 'label' => 'Теневой'],
                                'deactivated' => ['variant' => 'secondary', 'label' => 'Деактивирован'],
                                default => ['variant' => 'secondary', 'label' => $user->status]
                            };
                        @endphp
                        <x-ui.badge variant="{{ $statusBadge['variant'] }}" size="sm">{{ $statusBadge['label'] }}</x-ui.badge>
                    </x-ui.table-cell>
                    <x-ui.table-cell class="text-right">
                        <!-- Выпадающее меню действий (ТОЛЬКО ДЛЯ АДМИНОВ И МОДЕРАТОРОВ) -->
                        @if(in_array(auth()->user()->role, ['admin', 'moderator']))
                            <x-ui.dropdown-menu>
                                <x-ui.dropdown-menu-trigger>
                                    <x-ui.button variant="ghost" size="icon-sm">
                                        <x-lucide-more-horizontal class="w-4 h-4" />
                                    </x-ui.button>
                                </x-ui.dropdown-menu-trigger>
                                <x-ui.dropdown-menu-content align="end">
                                    @if($user->trashed())
                                        {{-- ЕСЛИ ЮЗЕР ДЕАКТИВИРОВАН: Только восстановить --}}
                                        <x-ui.dropdown-menu-label>Аккаунт удален</x-ui.dropdown-menu-label>
                                        <x-ui.dropdown-menu-separator />
                                        <x-ui.dropdown-menu-item wire:click="restoreUser({{ $user->id }})" wire:confirm="Восстановить аккаунт пользователя?">
                                            <x-lucide-rotate-ccw class="w-4 h-4 text-green-500" /> Восстановить
                                        </x-ui.dropdown-menu-item>
                                    @else
                                        {{-- ОБЫЧНОЕ МЕНЮ --}}
                                        <x-ui.dropdown-menu-item href="{{ route('admin.users.show', $user->id) }}" wire:navigate>
                                            <x-lucide-eye class="w-4 h-4" /> Просмотр
                                        </x-ui.dropdown-menu-item>
                                        <x-ui.dropdown-menu-separator />

                                        @if($user->status === 'banned' || $user->status === 'shadowbanned')
                                            <x-ui.dropdown-menu-item wire:click="toggleBan({{ $user->id }})" wire:confirm="Снять бан с пользователя?">
                                                <x-lucide-unlock class="w-4 h-4 text-green-500" /> Разбанить
                                            </x-ui.dropdown-menu-item>
                                        @else
                                            <x-ui.dropdown-menu-item wire:click="openBanModal({{ $user->id }}, 'shadow')">
                                                <x-lucide-eye-off class="w-4 h-4 text-purple-500" /> Теневой бан
                                            </x-ui.dropdown-menu-item>
                                            <x-ui.dropdown-menu-item wire:click="openBanModal({{ $user->id }}, 'temp')">
                                                <x-lucide-clock class="w-4 h-4 text-yellow-500" /> Бан на 3 дня
                                            </x-ui.dropdown-menu-item>
                                            <x-ui.dropdown-menu-item wire:click="openBanModal({{ $user->id }}, 'permanent')">
                                                <x-lucide-lock class="w-4 h-4 text-red-500" /> Вечный бан
                                            </x-ui.dropdown-menu-item>
                                        @endif

                                        <x-ui.dropdown-menu-separator />
                                        <x-ui.dropdown-menu-item wire:click="openDeleteModal({{ $user->id }})" variant="destructive">
                                            <x-lucide-trash-2 class="w-4 h-4" /> Деактивировать
                                        </x-ui.dropdown-menu-item>
                                    @endif
                                </x-ui.dropdown-menu-content>
                            </x-ui.dropdown-menu>
                        @endif
                    </x-ui.table-cell>
                </x-ui.table-row>
            @empty
                <x-ui.table-row>
                    <x-ui.table-cell colspan="9" class="py-12 text-center text-muted-foreground">
                        <div class="flex flex-col items-center gap-2">
                            <x-lucide-users class="w-12 h-12 opacity-30" />
                            <p>Пользователи не найдены</p>
                        </div>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @endforelse
        </x-ui.table-body>
    </x-ui.table>

    <!-- Пагинация -->
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div class="text-xs text-muted-foreground">
            Показано {{ $this->users->firstItem() ?? 0 }} - {{ $this->users->lastItem() ?? 0 }} из {{ $this->users->total() }}
        </div>
        {{ $this->users->links('partials.pagination') }}
    </div>
</div>