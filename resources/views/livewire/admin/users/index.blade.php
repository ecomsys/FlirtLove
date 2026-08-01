<?php

use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
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

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingGenderFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPremiumFilter(): void
    {
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
            // Берем ID только текущей страницы
            $this->selectedUsers = $this->users->pluck('id')->map(fn($id) => (string) $id)->toArray();
        } else {
            $this->selectedUsers = [];
        }
    }

    // ============================================
    // ВЫВОД ДАННЫХ (Computed)
    // ============================================

    #[Computed]
    public function users()
    {
        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
        $search = $this->search;

        return User::query()
            ->select(['id', 'name', 'email', 'created_at', 'last_seen', 'last_login_ip', 'status', 'is_premium', 'premium_expires_at', 'is_verified', 'has_completed_onboarding'])
            ->excludeStaff() // Наш новый скоп
            ->with([
                'profile',
                'photos' => fn($q) => $q->where('status', 'approved')->orderBy('is_primary', 'desc')->orderBy('position', 'asc')->limit(1)
            ])
            ->when($search, function ($query) use ($search, $operator) {
                $query->where(function ($q) use ($search, $operator) {
                    $q->where('name', $operator, "%{$search}%")
                      ->orWhere('email', $operator, "%{$search}%")
                      ->orWhereHas('profile', function ($sub) use ($search, $operator) {
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

    // ============================================
    // ДЕЙСТВИЯ (Базовые)
    // ============================================

    public function quickBan(int $userId): void
    {
        $user = User::find($userId);
        if (!$user) return;

        $user->update([
            'status' => 'banned',
            'ban_reason' => 'Бан из списка пользователей (быстрый)',
            'banned_until' => null // Вечный
        ]);

        $this->dispatch('show-toast', type: 'success', message: "Юзер ID {$userId} забанен");
    }

    public function quickUnban(int $userId): void
    {
        $user = User::find($userId);
        if (!$user) return;

        $user->update([
            'status' => 'active',
            'ban_reason' => null,
            'banned_until' => null
        ]);

        $this->dispatch('show-toast', type: 'success', message: "Юзер ID {$userId} разбанен");
    }

    public function massBan(): void
    {
        if (empty($this->selectedUsers)) {
            $this->dispatch('show-toast', type: 'error', message: 'Выберите хотя бы одного юзера');
            return;
        }

        User::whereIn('id', $this->selectedUsers)->update([
            'status' => 'banned',
            'ban_reason' => 'Массовый бан из списка'
        ]);

        $this->selectedUsers = [];
        $this->selectAll = false;

        $this->dispatch('show-toast', type: 'success', message: 'Выбранные юзеры забанены');
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => User::excludeStaff()->count(),
            'banned' => User::excludeStaff()->where('status', 'banned')->count(),
            'shadowbanned' => User::excludeStaff()->where('status', 'shadowbanned')->count(),
            'premium' => User::excludeStaff()->where('is_premium', true)->count(),
            'verified' => User::excludeStaff()->where('is_verified', true)->count(),
        ];
    }
}; 
?>

<div class="space-y-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <h1 class="text-2xl font-semibold">
            Пользователи 
            <span class="text-sm font-normal text-muted-foreground">
                (всего: {{ $this->users->total() }})
            </span>
        </h1>
        
        <!-- Поиск -->
        <div class="relative w-72">
            <x-ui.input 
                wire:model.live.debounce.300ms="search" 
                type="search" 
                placeholder="Имя, почта, город..." 
                class="pl-9 pr-8"
            />
            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            @if(!empty($search))
                <button wire:click="$set('search', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
                    <x-lucide-x class="w-4 h-4" />
                </button>
            @endif
        </div>
    </div>

    <!-- Фильтры -->
    <div class="flex items-center gap-3 flex-wrap">
        <select wire:model.live="statusFilter" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring md:w-[160px]">
            <option value="">Все статусы</option>
            <option value="active">Активные</option>
            <option value="banned">Забанены</option>
            <option value="shadowbanned">Теневой бан</option>
            <option value="deactivated">Деактивированы</option>
        </select>

        <select wire:model.live="genderFilter" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring md:w-[140px]">
            <option value="">Любой пол</option>
            <option value="male">Мужчины</option>
            <option value="female">Женщины</option>
        </select>

        <select wire:model.live="premiumFilter" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring md:w-[140px]">
            <option value="">Все (VIP)</option>
            <option value="yes">С VIP</option>
            <option value="no">Без VIP</option>
        </select>

        @if(!empty($this->selectedUsers))
            <x-ui.button wire:click="massBan" variant="destructive" size="sm" class="gap-2 ml-auto">
                <x-lucide-ban class="w-4 h-4" />
                Забанить выбранных ({{ count($this->selectedUsers) }})
            </x-ui.button>
        @endif
    </div>

    <!-- Таблица -->
    <x-ui.table>
        <x-ui.table-header>
            <x-ui.table-row>
                <x-ui.table-head class="w-10">
                    <input type="checkbox" wire:model.live="selectAll" class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
                </x-ui.table-head>
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
                <x-ui.table-head class="w-10"><span class="sr-only">Действия</span></x-ui.table-head>
            </x-ui.table-row>
        </x-ui.table-header>

        <x-ui.table-body>
            @forelse ($this->users as $user)
                <x-ui.table-row wire:key="user-row-{{ $user->id }}" class="{{ $user->status !== 'active' ? 'opacity-60 bg-muted/30' : '' }}">
                    <x-ui.table-cell>
                        <input type="checkbox" value="{{ $user->id }}" wire:model.live="selectedUsers" class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
                    </x-ui.table-cell>
                    <x-ui.table-cell class="text-muted-foreground text-xs">{{ $user->id }}</x-ui.table-cell>
                    <x-ui.table-cell>
                        <x-avatar src="{{ $user->avatar_url }}" name="{{ $user->name }}" size="sm" />
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        <a href="{{ route('admin.users.show', $user->id) }}" class="block group" wire:navigate>
                            <div class="font-medium text-foreground flex items-center gap-2 flex-wrap group-hover:text-primary transition-colors">
                                {{ $user->name }}                                
                                @if($user->has_active_premium)
                                    <x-lucide-crown class="w-3.5 h-3.5 text-yellow-500" />
                                @endif                              
                                @if($user->is_verified)
                                    <x-lucide-badge-check class="w-3.5 h-3.5 text-blue-500" />
                                @endif
                            </div>
                            <div class="text-xs text-muted-foreground group-hover:text-primary/80 transition-colors">{{ $user->email }}</div>
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
                                'deactivated' => ['variant' => 'secondary', 'label' => 'Удален'],
                                default => ['variant' => 'secondary', 'label' => $user->status]
                            };
                        @endphp
                        <x-ui.badge variant="{{ $statusBadge['variant'] }}" size="sm">
                            {{ $statusBadge['label'] }}
                        </x-ui.badge>
                    </x-ui.table-cell>
                    <x-ui.table-cell class="text-right">
                        <x-ui.dropdown-menu>
                            <x-ui.dropdown-menu-trigger>
                                <x-ui.button variant="ghost" size="icon-sm">
                                    <x-lucide-more-horizontal class="w-4 h-4" />
                                </x-ui.button>
                            </x-ui.dropdown-menu-trigger>
                            <x-ui.dropdown-menu-content align="end">
                                <x-ui.dropdown-menu-item href="{{ route('admin.users.show', $user->id) }}" wire:navigate>
                                    <x-lucide-eye class="w-4 h-4" />
                                    Просмотр
                                </x-ui.dropdown-menu-item>
                                
                                @if($user->status === 'active')
                                    <x-ui.dropdown-menu-item wire:click="quickBan({{ $user->id }})" wire:confirm="Забанить?">
                                        <x-lucide-ban class="w-4 h-4" />
                                        Забанить
                                    </x-ui.dropdown-menu-item>
                                @elseif($user->status === 'banned')
                                    <x-ui.dropdown-menu-item wire:click="quickUnban({{ $user->id }})" wire:confirm="Разбанить?">
                                        <x-lucide-unlock class="w-4 h-4" />
                                        Снять бан
                                    </x-ui.dropdown-menu-item>
                                @endif
                            </x-ui.dropdown-menu-content>
                        </x-ui.dropdown-menu>
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