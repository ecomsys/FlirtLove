<?php

use App\Models\Swipe;
use App\Models\UserMatch;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component 
{
    use WithPagination;

    public int $userId;

    #[Url(as: 'swipe_page')] 
    public int $swipePage = 1;
    
    #[Url(as: 'match_page')] 
    public int $matchPage = 1;

    public function mount(int $userId): void
    {
        $this->userId = $userId;
    }

    // Хелпер для жадной загрузки аватарок
    private function getAvatarQuery(): \Closure
    {
        return fn($q) => $q->withTrashed()->select('id', 'name', 'email', 'status', 'is_premium', 'premium_expires_at', 'last_seen')
            ->with(['photos' => fn($sq) => $sq->select('id', 'user_id', 'is_primary', 'status', 'path_thumb')->orderByDesc('is_primary')->limit(1)]);
    }

    // Свайпы, которые совершил этот юзер (исходящие)
    #[Computed]
    public function swipesMade()
    {
        return Swipe::where('user_id', $this->userId)
            ->with(['targetUser' => $this->getAvatarQuery()])
            ->latest()
            ->paginate(5, ['*'], 'swipePage');
    }

    // Мэтчи, в которых участвует этот юзер
    #[Computed]
    public function matches()
    {
        return UserMatch::where(function ($q) {
            $q->where('user1_id', $this->userId)
              ->orWhere('user2_id', $this->userId);
        })
        ->with([
            'user1' => $this->getAvatarQuery(),
            'user2' => $this->getAvatarQuery()
        ])
        ->latest()
        ->paginate(5, ['*'], 'matchPage');
    }

    #[On('user-action-performed')] 
    public function refreshSocial(): void
    {
        unset($this->swipesMade);
        unset($this->matches);
    }
}; 
?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    
    {{-- ЛЕВАЯ КОЛОНКА: СВАЙПЫ (ИСХОДЯЩИЕ) --}}
    <div class="space-y-4">
        <h3 class="text-sm font-semibold flex items-center gap-2">
            <x-lucide-heart class="w-4 h-4 text-blue-500" /> Свайпы ({{ $this->swipesMade->total() }})
        </h3>

        @if($this->swipesMade->isEmpty())
            <div class="p-4 bg-muted/20 rounded-lg border border-border text-center text-xs text-muted-foreground">
                Пользователь еще никого не оценивал.
            </div>
        @else
            <x-ui.table>
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-ui.table-head class="w-16">ID</x-ui.table-head>
                        <x-ui.table-head>Тип</x-ui.table-head>
                        <x-ui.table-head>Кого оценил</x-ui.table-head>
                        <x-ui.table-head>Дата</x-ui.table-head>
                    </x-ui.table-row>
                </x-ui.table-header>
                <x-ui.table-body>
                    @foreach($this->swipesMade as $swipe)
                        @php $targetUser = $swipe->targetUser; @endphp
                        <x-ui.table-row wire:key="swipe-{{ $swipe->id }}">
                            <x-ui.table-cell class="text-xs font-mono whitespace-nowrap">
                                <a href="{{ route('admin.moderation.dating', ['q' => $swipe->id, 'mode' => 'swipes']) }}" wire:navigate class="text-blue-500 hover:underline" title="Найти в модерации">
                                    #{{ $swipe->id }}
                                </a>
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                @php $swipeType = \App\Enums\SwipeType::tryFrom($swipe->type); @endphp
                                @if($swipeType)
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium {{ $swipeType->color() }}">
                                        @if($swipeType === \App\Enums\SwipeType::Like)<x-lucide-heart class="w-3 h-3 fill-current" />@endif
                                        @if($swipeType === \App\Enums\SwipeType::Superlike)<x-lucide-star class="w-3 h-3 fill-current" />@endif
                                        @if($swipeType === \App\Enums\SwipeType::Dislike)<x-lucide-thumbs-down class="w-3 h-3" />@endif
                                        {{ $swipeType->label() }}
                                    </span>
                                @endif
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                @if($targetUser)
                                    <a href="{{ route('admin.users.show', $targetUser->id) }}" wire:navigate class="flex items-center gap-2 group">
                                        <x-avatar src="{{ $targetUser->avatar_url }}" name="{{ $targetUser->name }}" size="sm" userId="{{ $targetUser->id }}" showStatus="true" :isOnline="$targetUser->is_online"/>
                                        <div class="flex flex-col min-w-0">
                                            <span class="text-sm font-medium group-hover:text-primary flex items-center gap-1.5">
                                                <x-user-status-sign :user="$targetUser" />
                                                <span class="truncate">{{ $targetUser->name }}</span>
                                                @if($targetUser->has_active_premium)
                                                    <x-lucide-crown class="w-3 h-3 text-yellow-500" />
                                                @endif
                                            </span>
                                            <span class="text-xs text-muted-foreground truncate">{{ $targetUser->email }}</span>
                                        </div>
                                    </a>
                                @else
                                    <div class="flex items-center gap-2">
                                        <x-avatar name="Del" size="sm" />
                                        <span class="text-sm text-muted-foreground italic">Удален</span>
                                    </div>
                                @endif
                            </x-ui.table-cell>
                            <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap">
                                {{ $swipe->created_at->diffForHumans() }}
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @endforeach
                </x-ui.table-body>
            </x-ui.table>
            <div class="mt-2">{{ $this->swipesMade->links('partials.pagination') }}</div>
        @endif
    </div>

    {{-- ПРАВАЯ КОЛОНКА: МЭТЧИ --}}
    <div class="space-y-4">
        <h3 class="text-sm font-semibold flex items-center gap-2">
            <x-lucide-link class="w-4 h-4 text-destructive" /> Мэтчи ({{ $this->matches->total() }})
        </h3>

        @if($this->matches->isEmpty())
            <div class="p-4 bg-muted/20 rounded-lg border border-border text-center text-xs text-muted-foreground">
                У пользователя нет мэтчей.
            </div>
        @else
            <x-ui.table>
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-ui.table-head class="w-16">ID</x-ui.table-head>
                        <x-ui.table-head>Партнер</x-ui.table-head>
                        <x-ui.table-head>Статус</x-ui.table-head>
                        <x-ui.table-head>Дата</x-ui.table-head>
                    </x-ui.table-row>
                </x-ui.table-header>
                <x-ui.table-body>
                    @foreach($this->matches as $match)
                        @php 
                            // Определяем, кто из участников не является текущим юзером
                            $partner = $match->user1_id === $this->userId ? $match->user2 : $match->user1; 
                        @endphp
                        <x-ui.table-row wire:key="match-{{ $match->id }}">
                            <x-ui.table-cell class="text-xs font-mono whitespace-nowrap">
                                <a href="{{ route('admin.moderation.dating', ['q' => $match->id, 'mode' => 'matches']) }}" wire:navigate class="text-blue-500 hover:underline" title="Найти в модерации">
                                    #{{ $match->id }}
                                </a>
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                @if($partner)
                                    <a href="{{ route('admin.users.show', $partner->id) }}" wire:navigate class="flex items-center gap-2 group">
                                        <x-avatar src="{{ $partner->avatar_url }}" name="{{ $partner->name }}" size="sm" userId="{{ $partner->id }}" showStatus="true" :isOnline="$partner->is_online"/>
                                        <div class="flex flex-col min-w-0">
                                            <span class="text-sm font-medium group-hover:text-primary flex items-center gap-1.5">
                                                <x-user-status-sign :user="$partner" />
                                                <span class="truncate">{{ $partner->name }}</span>
                                                @if($partner->has_active_premium)
                                                    <x-lucide-crown class="w-3 h-3 text-yellow-500" />
                                                @endif
                                            </span>
                                            <span class="text-xs text-muted-foreground truncate">{{ $partner->email }}</span>
                                        </div>
                                    </a>
                                @else
                                    <div class="flex items-center gap-2">
                                        <x-avatar name="Del" size="sm" />
                                        <span class="text-sm text-muted-foreground italic">Удален</span>
                                    </div>
                                @endif
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                @php $matchStatus = \App\Enums\MatchStatus::tryFrom($match->status); @endphp
                                @if($matchStatus)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $matchStatus->color() }}">
                                        {{ $matchStatus->label() }}
                                    </span>
                                @endif
                            </x-ui.table-cell>
                            <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap">
                                {{ $match->created_at->diffForHumans() }}
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @endforeach
                </x-ui.table-body>
            </x-ui.table>
            <div class="mt-2">{{ $this->matches->links('partials.pagination') }}</div>
        @endif
    </div>

</div>