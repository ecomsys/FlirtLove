<?php

use App\Models\User;
use Livewire\Volt\Component;
use Livewire\Attributes\Computed;

/**
 * Переиспользуемый компонент: Живой поиск пользователей.
 * Отправляет событие 'user-selected' с ID выбранного юзера.
 */
new class extends Component 
{
    /** @var string Текст поискового запроса */
    public string $search = '';

    /**
     * Вычисляемое свойство: Живой поиск юзеров по БД.
     */
    #[Computed]
    public function searchedUsers()
    {
        if (strlen($this->search) < 2) {
            return collect();
        }

        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
        $search = '%' . $this->search . '%';

        return User::query()
            ->excludeAdmins()
            ->where(function ($q) use ($operator, $search) {
                $q->where('name', $operator, $search)
                  ->orWhere('email', $operator, $search);
            })
            ->orWhere('id', (int) $this->search)
            ->limit(15)
            ->get(['id', 'name', 'email', 'is_premium', 'is_banned']);
    }

    /**
     * Выбор юзера. Отправляем событие родительскому компоненту.
     */
    public function selectUser(int $userId): void
    {
        $this->search = ''; // Очищаем поиск
        $this->dispatch('user-selected', id: $userId);
    }
}; 
?>

<div class="relative">
    <x-ui.input wire:model.live.debounce.300ms="search" type="text" placeholder="Имя, Email или ID..." class="w-full pl-9 pr-8" />
    <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
    
    @if(!empty($search))
        <button wire:click="$set('search', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
            <x-lucide-x class="w-4 h-4" />
        </button>
    @endif

    @if(strlen($search) >= 2)
        <div class="absolute z-50 mt-1 w-full border border-border rounded-lg max-h-60 overflow-y-auto little-scroll divide-y divide-border bg-card shadow-lg">
            @forelse ($this->searchedUsers as $u)
                <button wire:click="selectUser({{ $u->id }})" class="w-full p-3 flex items-center gap-3 hover:bg-muted/50 text-left transition-colors">
                    <x-avatar src="{{ $u->avatar_url }}" name="{{ $u->name }}" size="sm" userId="{{ $u->id }}" showStatus="true" />
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1">
                            <span class="font-medium text-sm truncate">{{ $u->name }}</span>
                            @if($u->has_active_premium)
                                <span class="text-yellow-500 shrink-0"><x-lucide-crown class="w-3 h-3 inline" /></span>
                            @endif
                            @if($u->is_banned)
                                <x-ui.badge variant="destructive" size="xs" class="shrink-0">Бан</x-ui.badge>
                            @endif
                        </div>
                        <div class="text-xs text-muted-foreground truncate">{{ $u->email }} (ID: {{ $u->id }})</div>
                    </div>
                </button>
            @empty
                <div class="px-4 py-8 text-center text-sm text-muted-foreground">Пользователи не найдены</div>
            @endforelse
        </div>
    @endif
</div>