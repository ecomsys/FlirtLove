<?php

use App\Enums\UserBlockReason;
use App\Models\AdminLog;
use App\Models\UserBlock;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component 
{
    use WithPagination;

    public int $userId;

    #[Url(as: 'blocked_page')] 
    public int $blockedPage = 1;
    
    #[Url(as: 'blockers_page')] 
    public int $blockersPage = 1;

    public function mount(int $userId): void
    {
        $this->userId = $userId;
    }

    private function getAvatarQuery(): \Closure
    {
        return fn($q) => $q->withTrashed()
            ->select('id', 'name', 'email', 'status', 'is_premium', 'premium_expires_at', 'last_seen')
            ->with(['photos' => fn($sq) => $sq->select('id', 'user_id', 'is_primary', 'status', 'path_thumb')->orderByDesc('is_primary')->limit(1)]);
    }

    #[Computed]
    public function blockedUsers()
    {
        return UserBlock::where('blocker_id', $this->userId)
            ->with(['blocked' => $this->getAvatarQuery()])
            ->latest()
            ->paginate(10, ['*'], 'blockedPage');
    }

    #[Computed]
    public function blockers()
    {
        return UserBlock::where('blocked_id', $this->userId)
            ->with(['blocker' => $this->getAvatarQuery()])
            ->latest()
            ->paginate(10, ['*'], 'blockersPage');
    }

    // НОВЫЙ МЕТОД: Снятие блокировки админом
    public function unblockUser(int $blockId): void
    {
        $block = UserBlock::find($blockId);
        if ($block) {
            $logData = $block->toArray();
            $block->delete();

            // Записываем в журнал админа
            AdminLog::record('user_block.delete', $this->user, auth()->user(), $logData, ['deleted' => true]);
            $this->dispatch('show-toast', type: 'success', message: 'Блокировка снята администратором.');
            $this->refreshUser();
        }
    }

    #[On('user-action-performed')] 
    public function refreshUser(): void
    {
        unset($this->blockedUsers);
        unset($this->blockers);
    }
}; 
?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    
    {{-- ЛЕВАЯ КОЛОНКА: КОГО ОН ЗАБЛОКИРОВАЛ --}}
    <div class="space-y-4">
        <h3 class="text-sm font-semibold flex items-center gap-2">
            <x-lucide-user-x class="w-4 h-4 text-blue-500" /> Заблокировал ({{ $this->blockedUsers->total() }})
        </h3>

        @if($this->blockedUsers->isEmpty())
            <div class="p-4 bg-muted/20 rounded-lg border border-border text-center text-xs text-muted-foreground">
                Пользователь никого не блокировал.
            </div>
        @else
            <x-ui.table>
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-ui.table-head>Пользователь</x-ui.table-head>
                        <x-ui.table-head>Причина</x-ui.table-head>
                        <x-ui.table-head>Дата</x-ui.table-head>
                        <x-ui.table-head class="text-right">Действие</x-ui.table-head> 
                    </x-ui.table-row>
                </x-ui.table-header>
                <x-ui.table-body>
                    @foreach($this->blockedUsers as $block)
                        @php 
                            $targetUser = $block->blocked; 
                            $reasonEnum = \App\Enums\UserBlockReason::tryFrom($block->reason ?? ''); 
                        @endphp
                        <x-ui.table-row wire:key="blocked-{{ $block->id }}">
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
                            <x-ui.table-cell>
                                @if($reasonEnum)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-muted {{ $reasonEnum->color() }}">
                                        {{ $reasonEnum->label() }}
                                    </span>
                                @else
                                    <span class="text-xs text-muted-foreground italic">Не указана</span>
                                @endif
                            </x-ui.table-cell>
                            <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap">
                                {{ $block->created_at->diffForHumans() }}
                            </x-ui.table-cell>
                            <!-- НОВАЯ КОЛОНКА ДЕЙСТВИЙ -->
                            <x-ui.table-cell class="text-right">
                                <x-ui.button variant="ghost" size="icon-sm" wire:click="unblockUser({{ $block->id }})" wire:confirm="Принудительно снять блокировку?">
                                    <x-lucide-trash-2 class="w-4 h-4 text-destructive" />
                                </x-ui.button>
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @endforeach
                </x-ui.table-body>
                  </x-ui.table>
            <div class="mt-2">{{ $this->blockedUsers->links('partials.pagination') }}</div>
        @endif
    </div>

    {{-- ПРАВАЯ КОЛОНКА: КТО ЗАБЛОКИРОВАЛ ЕГО --}}
    <div class="space-y-4">
        <h3 class="text-sm font-semibold flex items-center gap-2">
            <x-lucide-shield-x class="w-4 h-4 text-destructive" /> Заблокировали его ({{ $this->blockers->total() }})
        </h3>

        @if($this->blockers->isEmpty())
            <div class="p-4 bg-muted/20 rounded-lg border border-border text-center text-xs text-muted-foreground">
                Пользователя никто не блокировал.
            </div>
        @else
            <x-ui.table>
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-ui.table-head>Жалобщик</x-ui.table-head>
                        <x-ui.table-head>Причина</x-ui.table-head>
                        <x-ui.table-head>Дата</x-ui.table-head>
                        <x-ui.table-head class="text-right">Действие</x-ui.table-head> 
                    </x-ui.table-row>
                </x-ui.table-header>
                <x-ui.table-body>
                    @foreach($this->blockers as $block)
                        @php 
                            $targetUser = $block->blocker; 
                            $reasonEnum = \App\Enums\UserBlockReason::tryFrom($block->reason ?? ''); 
                        @endphp
                        <x-ui.table-row wire:key="blocker-{{ $block->id }}">
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
                            <x-ui.table-cell>
                                @if($reasonEnum)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-muted {{ $reasonEnum->color() }}">
                                        {{ $reasonEnum->label() }}
                                    </span>
                                @else
                                    <span class="text-xs text-muted-foreground italic">Не указана</span>
                                @endif
                            </x-ui.table-cell>
                            <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap">
                                {{ $block->created_at->diffForHumans() }}
                            </x-ui.table-cell>
                            <!-- НОВАЯ КОЛОНКА ДЕЙСТВИЙ -->
                            <x-ui.table-cell class="text-right">
                                <x-ui.button variant="ghost" size="icon-sm" wire:click="unblockUser({{ $block->id }})" wire:confirm="Принудительно снять блокировку?">
                                    <x-lucide-trash-2 class="w-4 h-4 text-destructive" />
                                </x-ui.button>
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @endforeach
                </x-ui.table-body>
                  </x-ui.table>
            <div class="mt-2">{{ $this->blockers->links('partials.pagination') }}</div>
        @endif
    </div>

</div>