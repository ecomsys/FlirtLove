<?php

use App\Models\User;
use Livewire\Volt\Component;
use Livewire\Attributes\Computed;

new class extends Component 
{
    public string $search = '';

    #[Computed]
    public function searchedUsers()
    {
        if (strlen($this->search) < 2) {
            return collect();
        }

        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
        $search = '%' . $this->search . '%';
        
        // Оптимизированный запрос для аватарок
        $avatarQuery = fn($q) => $q->select(['id', 'user_id', 'is_primary', 'status', 'path_thumb', 'path_medium', 'path_large', 'path_original'])->orderByDesc('is_primary')->limit(1);

        return User::query()
            ->excludeStaff() // ФИКС: Используем правильный скоуп
            ->with(['photos' => $avatarQuery]) // ФИКС: Грузим аватарки
            ->where(function ($q) use ($operator, $search) {
                $q->where('name', $operator, $search)
                  ->orWhere('email', $operator, $search);
                  
                // ФИКС: Поиск по ID только если ввели цифры
                if (is_numeric($this->search)) {
                    $q->orWhere('id', (int) $this->search);
                }
            })
            ->limit(15)
            ->get(['id', 'name', 'email', 'is_premium', 'premium_expires_at', 'status', 'last_seen']); // ФИКС: Выбираем нужные поля
    }

    public function selectUser(int $userId): void
    {
        $this->search = '';
        $this->dispatch('user-selected', id: $userId);
    }
}; 
?>

<div class="relative">
    <x-ui.input wire:model.live.debounce.300ms="search" type="text" placeholder="Имя, Email или ID..." class="w-full pl-9 pr-8" />
    <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground z-10" />
    
    @if(!empty($search))
        <button wire:click="$set('search', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground z-10">
            <x-lucide-x class="w-4 h-4" />
        </button>
    @endif

    @if(strlen($search) >= 2)
        <div class="absolute z-50 mt-1 w-full border border-border rounded-lg max-h-60 overflow-y-auto little-scroll divide-y divide-border bg-card shadow-lg">
            @forelse ($this->searchedUsers as $u)
                <button wire:click="selectUser({{ $u->id }})" class="w-full p-3 flex items-center gap-3 hover:bg-muted/50 text-left transition-colors">
                    <!-- Добавлен showStatus и isOnline -->
                    <x-avatar src="{{ $u->avatar_url }}" name="{{ $u->name }}" size="sm" userId="{{ $u->id }}" showStatus="true" :isOnline="$u->is_online" />
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1">
                            <x-user-status-sign :user="$u" />
                            <span class="font-medium text-sm truncate">{{ $u->name }}</span>
                            @if($u->has_active_premium)
                                <x-lucide-crown class="w-3 h-3 text-yellow-500 shrink-0" />
                            @endif
                            <!-- ФИКС: Проверяем status вместо is_banned -->
                            @if($u->status === 'banned')
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