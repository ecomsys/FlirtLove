<?php

use App\Actions\Admin\DeleteUserAction;
use App\Actions\Admin\ToggleUserBanAction;
use App\Models\AdminLog;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    /** @var int Кол-во юзеров на страницу */
    public int $perPage = 15;
    
    /** @var string Строка поиска (имя, почта, ID, город) */
    #[Url(as: 'q', except: '')]
    public string $search = '';
    
    /** @var string Фильтр по статусу */
    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';
    
    /** @var string Фильтр по полу */
    #[Url(as: 'gender', except: '')]
    public string $genderFilter = '';
    
    /** @var string Фильтр по VIP-статусу */
    #[Url(as: 'vip', except: '')]
    public string $premiumFilter = '';

    /** @var string Направление сортировки (asc/desc) */
    public string $sortDirection = 'desc';
    
    /** @var array ID выбранных юзеров для массовых действий */
    public array $selectedUsers = [];
    
    /** @var bool Состояние чекбокса "Выбрать все" */
    public bool $selectAll = false;

    // Инъекция зависимостей через boot (избегает багов парсинга Volt)
    private ToggleUserBanAction $toggleUserBanAction;
    private DeleteUserAction $deleteUserAction;

    /**
     * Внедряем Action-классы через boot (Livewire аналог конструктора).
     */
    public function boot(ToggleUserBanAction $toggleUserBanAction, DeleteUserAction $deleteUserAction): void
    {
        $this->toggleUserBanAction = $toggleUserBanAction;
        $this->deleteUserAction = $deleteUserAction;
    }

    /**
     * Хуки Livewire: сброс пагинации при изменении фильтров.
     */
    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingGenderFilter(): void { $this->resetPage(); }
    public function updatingPremiumFilter(): void { $this->resetPage(); }

    /**
     * Переключение направления сортировки по дате регистрации.
     */
    public function toggleSort(): void
    {
        $this->sortDirection = $this->sortDirection === 'desc' ? 'asc' : 'desc';
        $this->resetPage();
    }

    /**
     * Обработчик чекбокса "Выбрать все". Берет ID только текущей страницы.
     * @param mixed $value
     */
    public function updatedSelectAll($value): void
    {
        if ($value) {
            $this->selectedUsers = $this->users->pluck('id')->map(fn($id) => (string) $id)->toArray();
        } else {
            $this->selectedUsers = [];
        }
    }

    // ============================================
    // ВЫВОД ДАННЫХ (ОПТИМИЗИРОВАННЫЕ ЗАПРОСЫ)
    // ============================================

    /**
     * Получение списка пользователей с жадной загрузкой (Eager Loading).
     */
    #[Computed]
    public function users()
    {
        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
        $search = $this->search;

        return User::query()
            ->select(['id', 'name', 'email', 'created_at', 'last_seen', 'last_login_ip', 'status', 'is_premium', 'premium_expires_at', 'is_verified', 'has_completed_onboarding'])
            ->excludeStaff()
            ->with([
                'profile',
                'photos' => fn($q) => $q->where('status', 'approved')->orderBy('is_primary', 'desc')->orderBy('position', 'asc')->limit(1)
            ])
            ->when($search, function ($query) use ($search, $operator) {
                $query->where(function ($q) use ($search, $operator) {
                    $q->where('name', $operator, "%{$search}%")
                      ->orWhere('email', $operator, "%{$search}%")
                      // Приводим id к TEXT для безопасного и гибкого поиска (ilike) в Postgres
                      ->orWhereRaw("CAST(id AS TEXT) {$operator} ?", ["%{$search}%"])
                      ->orWhereHas('profile', function ($sub) use ($search, $operator) {
                          $sub->where('city', $operator, "%{$search}%");
                      });
                });
            })
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->genderFilter, fn($q) => $q->whereHas('profile', fn($sub) => $sub->where('gender', $this->genderFilter)))
            ->when($this->premiumFilter === 'yes', fn($q) => $q->where('is_premium', true))
            ->when($this->premiumFilter === 'no', fn($q) => $q->where('is_premium', false))
            // Стабильная сортировка (тай-брейкер по ID предотвращает "прыжки" строк)
            ->latest('created_at')
            ->latest('id')
            ->paginate($this->perPage);
    }

    /**
     * Подсчет метрик для бейджей (кеширование не используем, т.к. счетчики быстрые).
     */
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

    // ============================================
    // ДЕЙСТВИЯ (ИСПОЛЬЗУЕМ ИНЪЕКТИРОВАННЫЕ ЭКШЕНЫ)
    // ============================================

    /**
     * Бан/Разбан пользователя (делегирует логику и логирование в ToggleUserBanAction).
     * @param int $userId
     * @param string $type Тип бана: 'shadow', 'temp', 'permanent'
     */
    public function toggleBan(int $userId, string $type = 'permanent'): void
    {
        $user = User::find($userId);
        if (!$user) return;

        $result = $this->toggleUserBanAction->execute($user, 'Нарушение из списка пользователей', $type);
        $this->dispatch('show-toast', type: $result['success'] ? 'success' : 'error', message: $result['message']);
    }

    /**
     * Массовое удаление (деактивация) выбранных юзеров.
     */
    public function massDelete(): void
    {
        if (empty($this->selectedUsers)) {
            $this->dispatch('show-toast', type: 'error', message: 'Выберите хотя бы одного юзера');
            return;
        }

        $users = User::whereIn('id', $this->selectedUsers)->get();
        $count = 0;
        
        foreach ($users as $user) {
            if (!$user->isStaff()) {
                $this->deleteUserAction->execute($user, auth()->user());
                $count++;
            }
        }

        if ($count > 0) {
            AdminLog::record('user.mass_delete', new User(), auth()->user(), null, ['count' => $count, 'ids' => $this->selectedUsers]);
        }

        $this->selectedUsers = [];
        $this->selectAll = false;
        $this->dispatch('show-toast', type: 'success', message: "Удалено (деактивировано) {$count} юзеров");
    }

    /**
     * Удаление (деактивация) одного пользователя.
     * @param int $userId
     */
    public function deleteUser(int $userId): void
    {
        $user = User::find($userId);
        if (!$user) return;

        $this->deleteUserAction->execute($user, auth()->user());
        $this->dispatch('show-toast', type: 'success', message: "Юзер ID {$userId} удален");
    }
}; 
?>

<div class="space-y-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <h1 class="text-2xl font-semibold">
            Пользователи 
            <span class="text-sm font-normal text-muted-foreground">(всего: {{ $this->users->total() }})</span>
        </h1>
        
        <!-- Поиск -->
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

    <!-- Фильтры (Кастомные UI Select) -->
    <div class="flex items-center gap-3 flex-wrap">
        <x-ui.select wire:model.live="statusFilter" class="w-[160px]">
            <x-ui.select-trigger><x-ui.select-value placeholder="Все статусы" /></x-ui.select-trigger>
            <x-ui.select-content>
                <x-ui.select-item value="">Все статусы</x-ui.select-item>
                <x-ui.select-item value="active">Активные</x-ui.select-item>
                <x-ui.select-item value="banned">Забанены</x-ui.select-item>
                <x-ui.select-item value="shadowbanned">Теневой бан</x-ui.select-item>
                <x-ui.select-item value="deactivated">Деактивированы</x-ui.select-item>
            </x-ui.select-content>
        </x-ui.select>

        <x-ui.select wire:model.live="genderFilter" class="w-[140px]">
            <x-ui.select-trigger><x-ui.select-value placeholder="Любой пол" /></x-ui.select-trigger>
            <x-ui.select-content>
                <x-ui.select-item value="">Любой пол</x-ui.select-item>
                <x-ui.select-item value="male">Мужчины</x-ui.select-item>
                <x-ui.select-item value="female">Женщины</x-ui.select-item>
            </x-ui.select-content>
        </x-ui.select>

        <x-ui.select wire:model.live="premiumFilter" class="w-[140px]">
            <x-ui.select-trigger><x-ui.select-value placeholder="Все (VIP)" /></x-ui.select-trigger>
            <x-ui.select-content>
                <x-ui.select-item value="">Все (VIP)</x-ui.select-item>
                <x-ui.select-item value="yes">С VIP</x-ui.select-item>
                <x-ui.select-item value="no">Без VIP</x-ui.select-item>
            </x-ui.select-content>
        </x-ui.select>

        @if(!empty($this->selectedUsers))        
            <x-ui.button wire:click="massDelete" variant="destructive" size="sm" class="gap-2 ml-auto" wire:confirm="Удалить (деактивировать) выбранных юзеров?">
                <x-lucide-trash-2 class="w-4 h-4" />
                Удалить выбранных ({{ count($this->selectedUsers) }})
            </x-ui.button>        
        @endif
    </div>

    <!-- Таблица -->
    <x-ui.table>
        <x-ui.table-header>
            <x-ui.table-row>
                <x-ui.table-head class="w-10">
                    <x-checkbox wire:model.live="selectAll" />                    
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
                <x-ui.table-head class="w-10 text-right"><span class="sr-only">Действия</span></x-ui.table-head>
            </x-ui.table-row>
        </x-ui.table-header>

        <x-ui.table-body>
            @forelse ($this->users as $user)
                <!-- ФИКС: Ключ содержит статус юзера для 100% реактивности дропдауна -->
                <x-ui.table-row wire:key="user-{{ $user->id }}-{{ $user->status }}" class="{{ $user->status !== 'active' ? 'opacity-60 bg-muted/30' : '' }}">
                    <x-ui.table-cell>
                        <x-checkbox value="{{ $user->id }}" wire:model.live="selectedUsers" />                        
                    </x-ui.table-cell>
                    <x-ui.table-cell class="text-muted-foreground text-xs">{{ $user->id }}</x-ui.table-cell>
                    <x-ui.table-cell>
                        <x-avatar src="{{ $user->avatar_url }}" name="{{ $user->name }}" size="sm" userId="{{ $user->id }}" showStatus="true" :isOnline="$user->is_online"/>
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        <a href="{{ route('admin.users.show', $user->id) }}" class="block group" wire:navigate>
                            <div class="font-medium text-foreground flex items-center gap-2 flex-wrap group-hover:text-primary transition-colors">
                                <x-user-status-sign :user="$user" />
                                {{ $user->name }}                                
                                @if($user->has_active_premium)<x-lucide-crown class="w-3.5 h-3.5 text-yellow-500" />@endif                              
                                @if($user->is_verified)<x-lucide-badge-check class="w-3.5 h-3.5 text-blue-500" />@endif
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
                                'deactivated' => ['variant' => 'secondary', 'label' => 'Удален'],
                                default => ['variant' => 'secondary', 'label' => $user->status]
                            };
                        @endphp
                        <x-ui.badge variant="{{ $statusBadge['variant'] }}" size="sm">{{ $statusBadge['label'] }}</x-ui.badge>
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
                                    <x-lucide-eye class="w-4 h-4" /> Просмотр
                                </x-ui.dropdown-menu-item>
                                <x-ui.dropdown-menu-separator />

                                @if($user->status === 'banned' || $user->status === 'shadowbanned')
                                    <x-ui.dropdown-menu-item wire:click="toggleBan({{ $user->id }})" wire:confirm="Снять бан с пользователя?">
                                        <x-lucide-unlock class="w-4 h-4 text-green-500" /> Разбанить
                                    </x-ui.dropdown-menu-item>
                                @else
                                    <x-ui.dropdown-menu-item wire:click="toggleBan({{ $user->id }}, 'shadow')" wire:confirm="Применить теневой бан?">
                                        <x-lucide-eye-off class="w-4 h-4 text-purple-500" /> Теневой бан
                                    </x-ui.dropdown-menu-item>
                                    <x-ui.dropdown-menu-item wire:click="toggleBan({{ $user->id }}, 'temp')" wire:confirm="Забанить на 3 дня?">
                                        <x-lucide-clock class="w-4 h-4 text-yellow-500" /> Бан на 3 дня
                                    </x-ui.dropdown-menu-item>
                                    <x-ui.dropdown-menu-item wire:click="toggleBan({{ $user->id }}, 'permanent')" wire:confirm="Забанить навсегда?">
                                        <x-lucide-lock class="w-4 h-4 text-red-500" /> Вечный бан
                                    </x-ui.dropdown-menu-item>
                                @endif

                                <x-ui.dropdown-menu-separator />
                                <x-ui.dropdown-menu-item wire:click="deleteUser({{ $user->id }})" variant="destructive" wire:confirm="Удалить пользователя (деактивировать)?">
                                    <x-lucide-trash-2 class="w-4 h-4" /> Удалить
                                </x-ui.dropdown-menu-item>
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