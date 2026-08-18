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
        
        $avatarQuery = fn($q) => $q->select(['id', 'user_id', 'is_primary', 'status', 'path_thumb', 'path_medium', 'path_large', 'path_original'])->orderByDesc('is_primary')->limit(1);

        return User::query()
            ->excludeStaff()
            ->with(['photos' => $avatarQuery])
            ->where(function ($q) use ($operator, $search) {
                $q->where('name', $operator, $search)
                  ->orWhere('email', $operator, $search);
                  
                if (is_numeric($this->search)) {
                    $q->orWhere('id', (int) $this->search);
                }
            })
            ->limit(10) // <--- ПОМЕНЯЛИ С 15 НА 10
            ->get(['id', 'name', 'email', 'is_premium', 'premium_expires_at', 'status', 'last_seen']);
    }

    public function selectUser(int $userId): void
    {
        $this->search = '';
        $this->dispatch('user-selected', id: $userId);
    }
}; 
?>

<div 
    x-data="{
        highlightedIndex: -1,
        moveDown() {
            let container = this.$el.querySelector('.user-search-container');
            let items = container ? container.querySelectorAll('.user-search-item') : [];
            if (items.length > 0) {
                this.highlightedIndex = Math.min(this.highlightedIndex + 1, items.length - 1);
                this.scrollToHighlighted();
            }
        },
        moveUp() {
            let container = this.$el.querySelector('.user-search-container');
            let items = container ? container.querySelectorAll('.user-search-item') : [];
            if (items.length > 0) {
                this.highlightedIndex = Math.max(this.highlightedIndex - 1, 0);
                this.scrollToHighlighted();
            }
        },
        selectHighlighted() {
            if (this.highlightedIndex >= 0) {
                let container = this.$el.querySelector('.user-search-container');
                let items = container ? container.querySelectorAll('.user-search-item') : [];
                if (items[this.highlightedIndex]) {
                    items[this.highlightedIndex].click();
                }
            }
        },
        scrollToHighlighted() {
            this.$nextTick(() => {
                let container = this.$el.querySelector('.user-search-container');
                if (!container) return;
                let items = container.querySelectorAll('.user-search-item');
                if (!items[this.highlightedIndex]) return;
                
                let item = items[this.highlightedIndex];
                let containerRect = container.getBoundingClientRect();
                let itemRect = item.getBoundingClientRect();

                if (itemRect.bottom > containerRect.bottom) {
                    container.scrollTop += (itemRect.bottom - containerRect.bottom);
                } else if (itemRect.top < containerRect.top) {
                    container.scrollTop -= (containerRect.top - itemRect.top);
                }
            });
        }
    }" 
    @keydown.arrow-down.prevent="moveDown()" 
    @keydown.arrow-up.prevent="moveUp()" 
    @keydown.enter.prevent="selectHighlighted()"
    @input="highlightedIndex = -1"
    class="relative"
>
    <div class="relative">
        <x-ui.input 
            wire:model.live.debounce.300ms="search" 
            type="text" 
            placeholder="Имя, Email или ID..." 
            class="w-full pl-9 pr-8" 
        />
        <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground z-10" />
        
        @if(!empty($search))
            <button wire:click="$set('search', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground z-10">
                <x-lucide-x class="w-4 h-4" />
            </button>
        @endif
    </div>

    @if(strlen($search) >= 2)
        <div class="user-search-container absolute z-50 mt-1 w-full border border-border rounded-lg max-h-60 overflow-y-auto little-scroll divide-y divide-border bg-card shadow-lg">
            @forelse ($this->searchedUsers as $u)
                <button 
                    type="button"
                    wire:key="user-{{ $u->id }}"
                    wire:click="selectUser({{ $u->id }})" 
                    class="user-search-item w-full p-3 flex items-center gap-3 hover:bg-muted/50 text-left transition-colors border-l-4 border-transparent"
                    :class="highlightedIndex === {{ $loop->index }} ? 'bg-blue-500/10 border-blue-500' : ''"
                >
                    <x-avatar src="{{ $u->avatar_url }}" name="{{ $u->name }}" size="sm" userId="{{ $u->id }}" showStatus="true" :isOnline="$u->is_online" />
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1">
                            <x-user-status-sign :user="$u" />
                            <span class="font-medium text-sm truncate">{{ $u->name }}</span>
                            @if($u->has_active_premium)
                                <x-lucide-crown class="w-3 h-3 text-yellow-500 shrink-0" />
                            @endif
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