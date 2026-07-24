<?php
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Notifications\UserBanned;
use App\Notifications\UserDeleted;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    public int $perPage = 10;
    public string $search = '';
    public string $sortDirection = 'desc'; // asc или desc

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function toggleSort()
    {
        $this->sortDirection = $this->sortDirection === 'desc' ? 'asc' : 'desc';
        $this->resetPage();
    }

    public function with(): array
    {
        $users = User::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%')
                      ->orWhere('city', 'like', '%' . $this->search . '%');
            })
            ->orderBy('created_at', $this->sortDirection)
            ->paginate($this->perPage);

        return [
            'users' => $users,
            'sortDirection' => $this->sortDirection,
        ];
    }

    public function toggleBan(int $userId): void
    {
        $user = User::find($userId);
        if (!$user) return;

        if ($user->is_admin) {
            $this->dispatch('show-toast', 
                type: 'error', 
                message: 'Нельзя забанить администратора'
            );
            return;
        }

        // Определяем новый статус
        $newStatus = !$user->is_banned;
        
        // Обновляем в БД
        $user->update(['is_banned' => $newStatus]);
                
        //  ОТПРАВЛЯЕМ УВЕДОМЛЕНИЕ ПОЛЬЗОВАТЕЛЮ
        $user->notify(new UserBanned($newStatus));
        
        $this->dispatch('show-toast', 
            type: 'success', 
            message: $newStatus ? "Пользователь {$user->name} забанен" : "Пользователь {$user->name} разбанен"
        );
        
        $this->dispatch('$refresh');
    }

    public function deleteUser(int $userId): void
    {
        $user = User::find($userId);
        if (!$user) return;

        if ($user->is_admin) {
            $this->dispatch('show-toast', 
                type: 'error', 
                message: 'Нельзя удалить администратора'
            );
            return;
        }
        
         $userName = $user->name;

        //  1.отправляем уведомление (пока модель еще существует в памяти и БД)
        $user->notify(new UserDeleted());

        //  2.удаляем пользователя
        $user->delete();
        
        $this->dispatch('show-toast', 
            type: 'success', 
            message: "Пользователь {$userName} удален"
        );
        
        $this->dispatch('$refresh');
    }
}; ?>


<div class="space-y-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <h1 class="text-2xl font-semibold">
            Пользователи 
            <span class="text-sm font-normal text-muted-foreground">
                (всего: {{ $users->total() }})
            </span>
        </h1>
        
        <!-- Поиск -->
        <div class="relative w-72" wire:key="search-wrapper">
            <x-ui.input 
                wire:model.live.debounce.300ms="search" 
                type="search" 
                placeholder="Поиск (имя, почта, город)..." 
                class="pl-9 pr-8"
                wire:key="search-input"
            />
            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            @if(!empty($search))
                <button 
                    wire:click="$set('search', '')"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    wire:key="clear-search"
                >
                    <x-lucide-x class="w-4 h-4" />
                </button>
            @endif
        </div>
    </div>

    <!-- Таблица -->
    <x-ui.table wire:key="users-table">
        <x-ui.table-header>
            <x-ui.table-row>
                <x-ui.table-head class="w-12">ID</x-ui.table-head>
                <x-ui.table-head class="w-12">Аватар</x-ui.table-head>
                <x-ui.table-head>Имя</x-ui.table-head>
                <x-ui.table-head>Город</x-ui.table-head>
                
                {{-- Сортировка по дате регистрации --}}
                <x-ui.table-head>
                    <button 
                        wire:click="toggleSort" 
                        class="flex items-center gap-1 hover:text-foreground transition-colors"
                        wire:key="sort-button"
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
            @forelse ($users as $user)
                <x-ui.table-row wire:key="user-{{ $user->id }}">
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
                        <div class="font-medium text-foreground flex items-center gap-2 flex-wrap">
                            {{ $user->name }}
                            @if($user->is_admin)
                                <x-ui.badge variant="default" size="xs" wire:key="admin-badge-{{ $user->id }}">Admin</x-ui.badge>
                            @endif
                            @if(!$user->has_completed_onboarding)
                                <x-ui.badge variant="warning" size="xs" wire:key="onboarding-badge-{{ $user->id }}">Нет фото</x-ui.badge>
                            @endif
                        </div>
                        <div class="text-xs text-muted-foreground">{{ $user->email }}</div>
                    </x-ui.table-cell>
                    <x-ui.table-cell>{{ $user->city ?? '—' }}</x-ui.table-cell>
                    <x-ui.table-cell class="text-muted-foreground text-xs whitespace-nowrap">
                        {{ $user->created_at->format('d.m.Y') }}
                        <span class="text-[10px] text-muted-foreground/60">
                            {{ $user->created_at->format('H:i') }}
                        </span>
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        @if($user->last_login_at)
                            <div class="text-xs">{{ $user->last_login_at->diffForHumans() }}</div>
                            <div class="text-[10px] text-muted-foreground">{{ $user->last_login_ip }}</div>
                        @else
                            <span class="text-muted-foreground text-xs">Никогда</span>
                        @endif
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        @if($user->is_banned)
                            <x-ui.badge variant="destructive" size="sm" wire:key="banned-badge-{{ $user->id }}">Забанен</x-ui.badge>
                        @else
                            <x-ui.badge variant="success" size="sm" wire:key="active-badge-{{ $user->id }}">Активен</x-ui.badge>
                        @endif
                    </x-ui.table-cell>
                    <x-ui.table-cell class="text-right">
                        <x-ui.dropdown-menu wire:key="dropdown-{{ $user->id }}">
                            <x-ui.dropdown-menu-trigger>
                                <x-ui.button variant="ghost" size="icon-sm" aria-label="Действия">
                                    <x-lucide-more-horizontal aria-hidden="true" class="w-4 h-4" />
                                </x-ui.button>
                            </x-ui.dropdown-menu-trigger>
                            <x-ui.dropdown-menu-content align="end">
                                <x-ui.dropdown-menu-item href="{{ route('admin.users.show', $user) }}" wire:key="view-{{ $user->id }}">
                                    <x-lucide-eye class="w-4 h-4" />
                                    Просмотр
                                </x-ui.dropdown-menu-item>
                                <x-ui.dropdown-menu-item 
                                    wire:click="toggleBan({{ $user->id }})"
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
                <x-ui.table-row wire:key="empty-state">
                    <x-ui.table-cell colspan="8" class="py-12 text-center text-muted-foreground">
                        <div class="flex flex-col items-center gap-2">
                            <x-lucide-users class="w-12 h-12 opacity-30" />
                            <p>Пользователи не найдены</p>
                            @if(!empty($search))
                                <x-ui.button wire:click="$set('search', '')" variant="outline" size="sm" wire:key="clear-search-btn">
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
    <div class="flex items-center justify-between flex-wrap gap-2" wire:key="pagination-wrapper">
        <div class="text-xs text-muted-foreground">
            Показано {{ $users->firstItem() ?? 0 }} - {{ $users->lastItem() ?? 0 }} из {{ $users->total() }}
        </div>
        {{ $users->links('partials.pagination') }}
    </div>
</div>