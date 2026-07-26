<?php

use App\Models\User;
use App\Notifications\UserBanned;
use App\Notifications\UserDeleted;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

/**
 * Компонент списка пользователей админки.
 * Отвечает за просмотр, поиск, сортировку, бан и удаление пользователей.
 */
new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    /** @var int Количество пользователей на странице */
    public int $perPage = 10;
    
    /** @var string Поисковый запрос (имя, почта, город) */
    public string $search = '';
    
    /** @var string Направление сортировки (asc или desc) */
    public string $sortDirection = 'desc';

    /**
     * Хук Livewire: сброс пагинации при вводе поиска.
     */
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Переключение направления сортировки.
     */
    public function toggleSort(): void
    {
        $this->sortDirection = $this->sortDirection === 'desc' ? 'asc' : 'desc';
        $this->resetPage();
    }

    /**
     * Вычисляемое свойство: подготовка данных для страницы.
     * Оптимизация: тянем только нужные поля, чтобы не грузить память.
     */
    public function with(): array
    {
        $columns = [
            'id', 'name', 'email', 'city', 'created_at', 
            'last_login_at', 'last_login_ip', 'is_banned', 
            'is_admin', 'has_completed_onboarding'
        ];

        $users = User::query()
            ->select($columns)
            //  Изолировали поиск в замыкание, чтобы не ломать другие фильтры
            ->when($this->search, function ($query) {
                $search = $this->search;
                //  Динамический оператор для совместимости с MySQL и PostgreSQL
                $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
                
                $query->where(function ($q) use ($search, $operator) {
                    $q->where('name', $operator, '%' . $search . '%')
                      ->orWhere('email', $operator, '%' . $search . '%')
                      ->orWhere('city', $operator, '%' . $search . '%');
                });
            })
            ->orderBy('created_at', $this->sortDirection)
            ->paginate($this->perPage);

        return [
            'users' => $users,
            'sortDirection' => $this->sortDirection,
        ];
    }

    /**
     * Забанить / Разбанить пользователя.
     * 
     * @param int $userId
     */
    public function toggleBan(int $userId): void
    {
        $user = User::find($userId);
        if (!$user) return;

        if ($user->is_admin) {
            $this->dispatch('show-toast', type: 'error', message: 'Нельзя забанить администратора');
            return;
        }

        $newStatus = !$user->is_banned;
        $user->update(['is_banned' => $newStatus]);
        
        // Уведомляем пользователя (убедись, что UserBanned реализует ShouldQueue)
        $user->notify(new UserBanned($newStatus));
        
        $this->dispatch('show-toast', 
            type: 'success', 
            message: $newStatus ? "Пользователь {$user->name} забанен" : "Пользователь {$user->name} разбанен"
        );
        
        $this->dispatch('$refresh');
    }

    /**
     * Удаление пользователя.
     *  Добавлена транзакция.
     * ВАЖНО: Убедись, что в миграциях внешние ключи (photos, swipes и т.д.) 
     * имеют метод onDelete('cascade'), иначе база выдаст ошибку Constraint violation.
     * 
     * @param int $userId
     */
    public function deleteUser(int $userId): void
    {
        $user = User::find($userId);
        if (!$user) return;

        if ($user->is_admin) {
            $this->dispatch('show-toast', type: 'error', message: 'Нельзя удалить администратора');
            return;
        }
        
        $userName = $user->name;

        DB::transaction(function () use ($user) {
            // 1. Отправляем уведомление (пока модель существует)
            $user->notify(new UserDeleted());

            // 2. Удаляем пользователя (связанные данные удалятся автоматически, если настроено cascade)
            $user->delete();
        });
        
        $this->dispatch('show-toast', type: 'success', message: "Пользователь {$userName} удален");
        $this->dispatch('$refresh');
    }
}; 
?>

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
                        <!-- Обернули ссылкой -->
                        <a href="{{ route('admin.users.show', $user) }}" class="block group" wire:navigate>
                            <div class="font-medium text-foreground flex items-center gap-2 flex-wrap group-hover:text-primary transition-colors">
                                {{ $user->name }}
                                @if($user->is_admin)
                                    <x-ui.badge variant="default" size="xs" wire:key="admin-badge-{{ $user->id }}">Admin</x-ui.badge>
                                @endif
                                @if(!$user->has_completed_onboarding || !$user->avatar_url)
                                    <x-ui.badge variant="warning" size="xs" wire:key="onboarding-badge-{{ $user->id }}">Нет фото</x-ui.badge>
                                @endif
                            </div>
                            <div class="text-xs text-muted-foreground group-hover:text-primary/80 transition-colors">{{ $user->email }}</div>
                        </a>
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
                                <x-ui.dropdown-menu-item href="{{ route('admin.users.show', $user) }}" wire:key="view-{{ $user->id }}" wire:navigate>
                                    <x-lucide-eye class="w-4 h-4" />
                                    Просмотр
                                </x-ui.dropdown-menu-item>
                                
                                <!--  Добавлен wire:confirm -->
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
                                    <!--  Добавлен wire:confirm с предупреждением -->
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