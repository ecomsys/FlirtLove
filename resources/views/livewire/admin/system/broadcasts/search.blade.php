<?php

use App\Models\User;
use Livewire\Volt\Component;
use Livewire\Attributes\Computed;

new class extends Component 
{
    public string $search = '';
    public string $dropdownDirection = 'down';

    #[Computed]
    public function searchedUsers()
    {
        if (empty($this->search)) {
            return collect();
        }

        // ФИКС: Строгая проверка целого числа (ctype_digit) для защиты PostgreSQL
        $isIdSearch = ctype_digit($this->search);
        
        // ФИКС: Разрешаем поиск по ID даже из 1 символа (например ID "5")
        if (!$isIdSearch && strlen($this->search) < 2) {
            return collect();
        }

        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
        $searchTerm = '%' . $this->search . '%';

        return User::query()
            ->where('role', User::ROLE_USER)
            ->where(function ($q) use ($operator, $searchTerm, $isIdSearch) {
                // Если ввели цифры, ищем строго по ID
                if ($isIdSearch) {
                    $q->where('id', (int) $this->search);
                } else {
                    // Иначе ищем по имени или email
                    $q->where('name', $operator, $searchTerm)
                      ->orWhere('email', $operator, $searchTerm);
                }
            })
            ->with(['photos' => fn($q) => $q->select(['id', 'user_id', 'is_primary', 'status', 'path_thumb'])->orderBy('is_primary', 'desc')->limit(1)])
            ->limit(15)
            ->get(['id', 'name', 'email', 'status', 'premium_expires_at', 'vip_expires_at', 'last_seen']);
    }

    public function selectUser(int $userId, string $userName): void
    {
        $this->reset('search');
        $this->dispatch('user-selected', id: $userId, name: $userName);
    }
}; 
?>
<div x-data="{ activeIndex: -1 }" x-init="$watch(() => $wire.search, () => activeIndex = -1)" class="relative">
    
    <x-ui.input 
        wire:model.live.debounce.300ms="search" 
        type="text" 
        placeholder="Имя, Email или ID..." 
        class="w-full pl-9 pr-8" 
        x-on:keydown.arrow-down.prevent="activeIndex = Math.min(activeIndex + 1, {{ $this->searchedUsers->count() - 1 }}); $refs['item-'+activeIndex]?.scrollIntoView({ block: 'nearest', behavior: 'smooth' })"
        x-on:keydown.arrow-up.prevent="activeIndex = Math.max(activeIndex - 1, 0); $refs['item-'+activeIndex]?.scrollIntoView({ block: 'nearest', behavior: 'smooth' })"
        x-on:keydown.enter.prevent="if(activeIndex >= 0) { $refs['item-' + activeIndex].click() }"
        x-on:keydown.space.prevent="if(activeIndex >= 0) { $refs['item-' + activeIndex].click() }"
    />
    
    <!-- ФИКС: Нативный Livewire лоадер для инпута (надежнее Alpine) -->
    <span wire:loading.remove wire:target="search" class="absolute left-3 top-1/2 -translate-y-1/2">
        <x-lucide-search class="w-4 h-4 text-muted-foreground" />
    </span>
    
    <span wire:loading wire:target="search" class="absolute left-3 top-1/2 -translate-y-1/2">
        <x-lucide-loader-2 class="w-4 h-4 animate-spin text-primary" />
    </span>
    
    @if(!empty($search))
        <button wire:click="$set('search', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
            <x-lucide-x class="w-4 h-4" />
        </button>
    @endif

    @if(strlen($search) >= 2 || ctype_digit($search))
        @php
            $dropdownClasses = $dropdownDirection === 'up' 
                ? 'absolute z-50 bottom-full mb-1 w-full border border-border rounded-lg max-h-60 overflow-y-auto little-scroll divide-y divide-border bg-card shadow-lg'
                : 'absolute z-50 top-full mt-1 w-full border border-border rounded-lg max-h-60 overflow-y-auto little-scroll divide-y divide-border bg-card shadow-lg';
        @endphp

        <div x-transition class="{{ $dropdownClasses }}">
            @forelse ($this->searchedUsers as $index => $u)
                <a 
                    href="{{ route('admin.users.show', $u->id) }}" 
                    wire:click.prevent="selectUser({{ $u->id }}, {{ json_encode($u->name) }})" 
                    wire:key="user-search-{{ $u->id }}"
                    x-ref="item-{{ $index }}"
                    class="w-full p-3 flex items-center gap-3 text-left transition-colors border-l-4"
                    :class="activeIndex === {{ $index }} ? 'bg-muted/50 border-primary' : 'border-transparent hover:bg-muted/30'"
                >
                    <x-avatar src="{{ $u->avatar_url }}" name="{{ $u->name }}" size="sm" userId="{{ $u->id }}" showStatus="true" />
                    
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="font-medium text-sm truncate">{{ $u->name }}</span>
                            <span class="text-xs text-muted-foreground/70 font-mono shrink-0">(#{{ $u->id }})</span>
                            @if($u->has_active_premium)
                                <span class="text-yellow-500 shrink-0"><x-lucide-crown class="w-3 h-3 inline" /></span>
                            @endif
                            @if($u->status === 'banned')
                                <x-ui.badge variant="destructive" size="xs" class="shrink-0">Бан</x-ui.badge>
                            @endif
                        </div>
                        <div class="text-xs text-muted-foreground truncate">{{ $u->email }}</div>
                    </div>
                </a>
            @empty
                <div class="px-4 py-8 text-center text-sm text-muted-foreground">Пользователи не найдены</div>
            @endforelse
        </div>
    @endif
</div>