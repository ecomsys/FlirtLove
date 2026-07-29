<?php

use App\Actions\Admin\DeleteUserAction;
use App\Actions\Admin\ToggleUserBanAction;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    public int $perPage = 10;
    public string $search = '';
    public string $sortDirection = 'desc';

    private ToggleUserBanAction $toggleBanAction;
    private DeleteUserAction $deleteUserAction;

    public function boot(
        ToggleUserBanAction $toggleBanAction,
        DeleteUserAction $deleteUserAction
    ): void {
        $this->toggleBanAction = $toggleBanAction;
        $this->deleteUserAction = $deleteUserAction;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function toggleSort(): void
    {
        $this->sortDirection = $this->sortDirection === 'desc' ? 'asc' : 'desc';
        $this->resetPage();
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
            ->select(['id', 'name', 'email', 'created_at', 'last_login_at', 'last_login_ip', 'is_banned', 'is_admin', 'has_completed_onboarding', 'is_premium', 'premium_expires_at'])
            ->where('is_admin', false)
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
            // ✅ ФИКС СКАЧКОВ: Добавили вторичную сортировку по id, чтобы порядок строк был 100% стабильным
            ->orderBy('created_at', $this->sortDirection)
            ->orderBy('id', $this->sortDirection) 
            ->paginate($this->perPage);
    }

    // ============================================
    // ДЕЙСТВИЯ
    // ============================================

    public function toggleBan(int $userId): void
    {
        $user = User::find($userId);
        
        if (!$user) {
            $this->dispatch('show-toast', type: 'error', message: 'Пользователь не найден');
            return;
        }

        $result = $this->toggleBanAction->execute($user, 'Нарушение правил через список пользователей');
        
        $this->dispatch('show-toast', 
            type: $result['success'] ? 'success' : 'error',
            message: $result['message']
        );
        
        if ($result['success']) {
            // ✅ Меняем статус в памяти, чтобы не делать запрос в БД и не перестраивать таблицу
            $this->users->getCollection()->transform(function ($u) use ($userId, $result) {
                if ($u->id === $userId) {
                    $u->is_banned = $result['is_banned'];
                }
                return $u;
            });
        }
    }

    public function deleteUser(int $userId): void
    {
        $result = $this->deleteUserAction->execute($userId);
        
        $this->dispatch('show-toast', 
            type: $result['success'] ? 'success' : 'error',
            message: $result['message']
        );
        
        if ($result['success']) {
            // ✅ Удаляем юзера из коллекции в памяти
            $newCollection = $this->users->getCollection()->reject(function ($u) use ($userId) {
                return $u->id === $userId;
            })->values();
            
            $this->users->setCollection($newCollection);
        }
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => User::where('is_admin', false)->count(),
            'banned' => User::where('is_admin', false)->where('is_banned', true)->count(),
            'premium' => User::where('is_admin', false)->where('is_premium', true)->count(),
            'verified' => User::where('is_admin', false)->where('is_verified', true)->count(),
            'onboarding_complete' => User::where('is_admin', false)->where('has_completed_onboarding', true)->count(),
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
        <div class="relative w-72" wire:key="search-wrapper-main">
            <x-ui.input 
                wire:model.live.debounce.300ms="search" 
                type="search" 
                placeholder="Поиск (имя, почта, город)..." 
                class="pl-9 pr-8"
                wire:key="search-input-main"
            />
            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            @if(!empty($search))
                <button 
                    wire:click="$set('search', '')"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    wire:key="clear-search-btn-main"
                >
                    <x-lucide-x class="w-4 h-4" />
                </button>
            @endif
        </div>
    </div>

    <!-- Таблица -->
    <x-ui.table wire:key="users-table-main">
        <x-ui.table-header>
            <x-ui.table-row>
                <x-ui.table-head class="w-12">ID</x-ui.table-head>
                <x-ui.table-head class="w-12">Аватар</x-ui.table-head>
                <x-ui.table-head>Имя</x-ui.table-head>
                <x-ui.table-head>Город</x-ui.table-head>
                <x-ui.table-head>
                    <button 
                        wire:click="toggleSort" 
                        class="flex items-center gap-1 hover:text-foreground transition-colors"
                        wire:key="sort-button-main"
                    >
                        Регистрация
                        @if($sortDirection === 'desc')
                            <x-lucide-chevron-down class="w-3 h-3" />
                        @else
                            <x-lucide-chevron-up class="w-3 h-3" />
                        @endif
                    </button>
                </x-ui.table-head>
                <x-ui.table-head>Последний вход</x-ui.table-head>
                <x-ui.table-head>Статус</x-ui.table-head>
                <x-ui.table-head class="w-10"><span class="sr-only">Действия</span></x-ui.table-head>
            </x-ui.table-row>
        </x-ui.table-header>

        <x-ui.table-body>
            @forelse ($this->users as $user)
                {{-- ✅ Уникальный ключ строки --}}
                <x-ui.table-row wire:key="user-row-{{ $user->id }}">
                    <x-ui.table-cell class="text-muted-foreground text-xs">{{ $user->id }}</x-ui.table-cell>
                    <x-ui.table-cell>
                        <x-avatar 
                            src="{{ $user->avatar_url }}" 
                            name="{{ $user->name }}" 
                            size="sm" 
                            userId="{{ $user->id }}"
                            showStatus="true"
                        />
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        <a href="{{ route('admin.users.show', $user->id) }}" class="block group" wire:navigate>
                            <div class="font-medium text-foreground flex items-center gap-2 flex-wrap group-hover:text-primary transition-colors">
                                {{ $user->name }}                                
                                @if($user->has_active_premium)
                                    <x-ui.badge variant="warning" size="xs" wire:key="premium-{{ $user->id }}" class="p-1 flex items-center gap-1">
                                        <x-lucide-crown class="w-3 h-3" />
                                    </x-ui.badge>
                                @endif                              
                                @if(!$user->has_completed_onboarding || !$user->avatar_url)
                                    <x-ui.badge variant="warning" size="xs" wire:key="onboarding-{{ $user->id }}">Нет фото</x-ui.badge>
                                @endif
                                <span class="text-xs text-muted-foreground font-normal">(ID: {{ $user->id }})</span>
                            </div>
                            <div class="text-xs text-muted-foreground group-hover:text-primary/80 transition-colors">{{ $user->email }}</div>
                        </a>
                    </x-ui.table-cell>
                    <x-ui.table-cell>{{ $user->profile?->city ?? '—' }}</x-ui.table-cell>
                    <x-ui.table-cell class="text-muted-foreground text-xs whitespace-nowrap">
                        {{ $user->created_at->format('d.m.Y') }}
                        <span class="text-[0.65rem] text-muted-foreground/60">
                            {{ $user->created_at->format('H:i') }}
                        </span>
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        @if($user->last_login_at)
                            <div class="text-xs">{{ $user->last_login_at->diffForHumans() }}</div>
                            <div class="text-[0.65rem] text-muted-foreground">{{ $user->last_login_ip }}</div>
                        @else
                            <span class="text-muted-foreground text-xs">Никогда</span>
                        @endif
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        {{-- ✅ Фикс: Один и тот же ключ для бейджа статуса, чтобы Livewire не путался при бане --}}
                        <x-ui.badge 
                            variant="{{ $user->is_banned ? 'destructive' : 'success' }}" 
                            size="sm" 
                            wire:key="status-badge-{{ $user->id }}"
                        >
                            {{ $user->is_banned ? 'Забанен' : 'Активен' }}
                        </x-ui.badge>
                    </x-ui.table-cell>
                    <x-ui.table-cell class="text-right">
                        <x-ui.dropdown-menu wire:key="dropdown-{{ $user->id }}">
                            <x-ui.dropdown-menu-trigger>
                                <x-ui.button variant="ghost" size="icon-sm" aria-label="Действия">
                                    <x-lucide-more-horizontal aria-hidden="true" class="w-4 h-4" />
                                </x-ui.button>
                            </x-ui.dropdown-menu-trigger>
                            <x-ui.dropdown-menu-content align="end">
                                <x-ui.dropdown-menu-item href="{{ route('admin.users.show', $user->id) }}" wire:key="view-{{ $user->id }}" wire:navigate>
                                    <x-lucide-eye class="w-4 h-4" />
                                    Просмотр
                                </x-ui.dropdown-menu-item>
                                
                                <x-ui.dropdown-menu-item 
                                    wire:click="toggleBan({{ $user->id }})"
                                    wire:confirm="Изменить статус блокировки этого пользователя?"
                                    wire:key="toggleBan-{{ $user->id }}"
                                >
                                    <x-lucide-lock class="w-4 h-4" />
                                    Снять/наложить бан
                                </x-ui.dropdown-menu-item>
                                
                                @unless($user->is_admin)
                                    <x-ui.dropdown-menu-separator />
                                    <x-ui.dropdown-menu-item 
                                        wire:click="deleteUser({{ $user->id }})" 
                                        variant="destructive"
                                        wire:confirm="ВНИМАНИЕ! Вы уверены, что хотите удалить этого пользователя? Все его данные будут потеряны навсегда."
                                        wire:key="delete-{{ $user->id }}"
                                    >
                                        <x-lucide-trash-2 class="w-4 h-4" />
                                        Удалить
                                    </x-ui.dropdown-menu-item>
                                @endunless
                            </x-ui.dropdown-menu-content>
                        </x-ui.dropdown-menu>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @empty
                <x-ui.table-row wire:key="empty-state-main">
                    <x-ui.table-cell colspan="8" class="py-12 text-center text-muted-foreground">
                        <div class="flex flex-col items-center gap-2">
                            <x-lucide-users class="w-12 h-12 opacity-30" />
                            <p>Пользователи не найдены</p>
                            @if(!empty($search))
                                <x-ui.button wire:click="$set('search', '')" variant="outline" size="sm" wire:key="clear-search-empty-main">
                                    Очистить поиск
                                </x-ui.button>
                            @endif
                        </div>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @endforelse
        </x-ui.table-body>
    </x-ui.table>

    <!-- Пагинация -->
    <div class="flex items-center justify-between flex-wrap gap-2" wire:key="pagination-wrapper-main">
        <div class="text-xs text-muted-foreground">
            Показано {{ $this->users->firstItem() ?? 0 }} - {{ $this->users->lastItem() ?? 0 }} из {{ $this->users->total() }}
        </div>
        {{ $this->users->links('partials.pagination') }}
    </div>
</div>