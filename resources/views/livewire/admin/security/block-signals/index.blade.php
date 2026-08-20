<?php

use App\Actions\Admin\ToggleUserBanAction;
use App\Enums\UserBlockReason;
use App\Models\User;
use App\Models\UserBlock;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Session;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    // ФИКС: Изменили тип на int|string и дефолтное значение на строку '3', чтобы Alpine-селект сразу его подхватил
    #[Session] 
    public string $threshold = '3'; // Строго строка!
    #[Session] 
    public int $perPage = 20;

    public bool $showBlockersModal = false;
    public ?int $viewingUserId = null;
   

    #[Computed]
    public function suspiciousUsers()
    {
        return User::query()
            ->where('role', 'user')
            ->with('photos:id,user_id,is_primary,status,path_thumb')
            ->leftJoin('user_blocks', function ($join) {
                $join->on('users.id', '=', 'user_blocks.blocked_id');
            })
            ->select('users.*')
            ->selectRaw('COUNT(user_blocks.id) as total_blocks_count')
            ->selectRaw('COUNT(CASE WHEN user_blocks.created_at >= ? THEN 1 END) as recent_blocks_count', [now()->subDays(7)])
            ->groupBy('users.id')
            ->havingRaw('COUNT(user_blocks.id) >= ?', [(int) $this->threshold])
            ->orderByRaw('recent_blocks_count DESC')
            ->orderByRaw('total_blocks_count DESC')
            ->paginate(min(max($this->perPage, 10), 100));
    }

    #[Computed]
    public function blockersList()
    {
        if (!$this->viewingUserId) return collect();

        return UserBlock::where('blocked_id', $this->viewingUserId)
            ->with(['blocker' => fn($q) => $q->withTrashed()
                ->select('id', 'name', 'email', 'status', 'is_premium', 'last_seen')
                ->with('photos:id,user_id,is_primary,status,path_thumb')
            ])
            ->latest()
            ->get();
    }

    public function openBlockersModal(int $userId): void
    {
        $this->viewingUserId = $userId;
        $this->showBlockersModal = true;
    }

    public function closeBlockersModal(): void
    {
        $this->showBlockersModal = false;
        $this->viewingUserId = null;
    }

    public function banUser(int $id, string $type): void
    {
        $user = User::findOrFail($id);
        $action = app(ToggleUserBanAction::class);
        
        $reason = "Массовые блокировки от пользователей ({$user->total_blocks_count} раз)";
        $result = $action->execute($user, $reason, $type, true);

        $this->dispatch('show-toast', type: $result['success'] ? 'success' : 'error', message: $result['message']);
    }
}; 
?>

<div class="space-y-6 pb-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.dashboard') }}" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-semibold flex items-center gap-2">
                    <x-lucide-users class="w-6 h-6 text-destructive" />
                    Сигналы блокировок
                    <!-- НОВЫЙ БЕЙДЖ: Общее количество подозрительных -->
                    <x-ui.badge variant="destructive" size="sm">{{ $this->suspiciousUsers->total() }}</x-ui.badge>
                </h1>
                <p class="text-sm text-muted-foreground mt-1">
                    Пользователи, получающие больше всего блокировок от других юзеров.
                </p>
            </div>
        </div>

                <!-- Фильтр порога -->
        <div class="flex items-center gap-2">
            <span class="text-sm text-muted-foreground">Показывать заблокированных:</span>
            
            <!-- ФИКС: Статичный wire:key -->
            <x-ui.select wire:key="threshold-select" wire:model.live="threshold" wire:change="$set('page', 1)">
                <x-ui.select-trigger class="w-20"><x-ui.select-value /></x-ui.select-trigger>
                <x-ui.select-content>
                    <x-ui.select-item value="2">2+</x-ui.select-item>
                    <x-ui.select-item value="3">3+</x-ui.select-item>
                    <x-ui.select-item value="5">5+</x-ui.select-item>
                    <x-ui.select-item value="10">10+</x-ui.select-item>
                </x-ui.select-content>
            </x-ui.select>
            
            <span class="text-sm text-muted-foreground">раз</span>
        </div>
    </div>

    <!-- ТАБЛИЦА -->
    <x-ui.table>
        <x-ui.table-header>
            <x-ui.table-row>
                <x-ui.table-head>Пользователь</x-ui.table-head>
                <x-ui.table-head class="text-center">Всего блокировок</x-ui.table-head>
                <x-ui.table-head class="text-center">За 7 дней</x-ui.table-head>
                <x-ui.table-head>Статус</x-ui.table-head>
                <x-ui.table-head class="text-right">Действия</x-ui.table-head>
            </x-ui.table-row>
        </x-ui.table-header>

        <x-ui.table-body>
            @forelse ($this->suspiciousUsers as $user)
                @php 
                    $isDanger = $user->recent_blocks_count >= 5;
                @endphp
                <x-ui.table-row wire:key="signal-{{ $user->id }}-status-{{ $user->status }}" class="{{ $isDanger ? 'bg-destructive/5' : '' }}">
                    <x-ui.table-cell>
                        <a href="{{ route('admin.users.show', $user->id) }}" wire:navigate class="flex gap-2 items-center group">
                            <x-avatar src="{{ $user->avatar_url }}" name="{{ $user->name }}" size="sm" userId="{{ $user->id }}" showStatus="true" :isOnline="$user->is_online"/>                            
                            <div class="block min-w-0">
                                <div class="font-medium text-sm text-foreground flex items-center gap-1.5 group-hover:text-primary transition-colors">
                                    <x-user-status-sign :user="$user" />
                                    <span class="truncate">{{ $user->name }}</span>                                
                                    @if($user->has_active_premium)<x-lucide-crown class="w-3.5 h-3.5 text-yellow-500 shrink-0" />@endif                              
                                </div>
                                <div class="text-xs text-muted-foreground truncate">{{ $user->email }}</div>
                            </div>
                        </a>
                    </x-ui.table-cell>
                    
                    <x-ui.table-cell class="text-center">
                        <span class="font-bold text-lg {{ $user->total_blocks_count >= 10 ? 'text-destructive' : 'text-foreground' }}">{{ $user->total_blocks_count }}</span>
                    </x-ui.table-cell>
                    
                    <x-ui.table-cell class="text-center">
                        @if($isDanger)
                            <x-ui.badge variant="destructive" size="sm">{{ $user->recent_blocks_count }} 🔥</x-ui.badge>
                        @else
                            <span class="text-sm text-muted-foreground">{{ $user->recent_blocks_count }}</span>
                        @endif
                    </x-ui.table-cell>
                    
                    <x-ui.table-cell>
                        @if($user->status === 'active')
                            <x-ui.badge variant="success" size="sm">Активен</x-ui.badge>
                        @elseif($user->status === 'banned')
                            <x-ui.badge variant="destructive" size="sm">В бане</x-ui.badge>
                        @elseif($user->status === 'shadowbanned')
                            <x-ui.badge variant="secondary" size="sm">Теневой бан</x-ui.badge>
                        @endif
                    </x-ui.table-cell>
                    
                    <x-ui.table-cell class="text-right">
                        <div class="flex justify-end items-center gap-1">
                            <x-ui.button variant="ghost" size="icon-sm" wire:click="openBlockersModal({{ $user->id }})" title="Кто его заблокировал">
                                <x-lucide-eye class="w-4 h-4 text-blue-500" />
                            </x-ui.button>

                            <x-ui.dropdown-menu>
                                <x-ui.dropdown-menu-trigger>
                                    <x-ui.button variant="ghost" size="icon-sm">
                                        <x-lucide-more-horizontal class="w-4 h-4" />
                                    </x-ui.button>
                                </x-ui.dropdown-menu-trigger>
                                <x-ui.dropdown-menu-content align="end">
                                    @if($user->status !== 'banned' && $user->status !== 'shadowbanned')
                                        <x-ui.dropdown-menu-label>Забанить</x-ui.dropdown-menu-label>
                                        <x-ui.dropdown-menu-separator />
                                        
                                        <x-ui.dropdown-menu-item wire:click="banUser({{ $user->id }}, 'shadow')" wire:confirm="Наложить теневой бан?">
                                            <x-lucide-eye-off class="w-4 h-4 text-purple-500" /> Теневой бан
                                        </x-ui.dropdown-menu-item>
                                        <x-ui.dropdown-menu-item wire:click="banUser({{ $user->id }}, 'temp')" wire:confirm="Забанить на 3 дня?">
                                            <x-lucide-clock class="w-4 h-4 text-yellow-500" /> Бан на 3 дня
                                        </x-ui.dropdown-menu-item>
                                        <x-ui.dropdown-menu-item wire:click="banUser({{ $user->id }}, 'permanent')" wire:confirm="Забанить навсегда?">
                                            <x-lucide-gavel class="w-4 h-4 text-red-500" /> Вечный бан
                                        </x-ui.dropdown-menu-item>
                                    @else
                                        <x-ui.dropdown-menu-label>Уже забанен</x-ui.dropdown-menu-label>
                                    @endif
                                </x-ui.dropdown-menu-content>
                            </x-ui.dropdown-menu>
                        </div>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @empty
                <x-ui.table-row wire:key="empty-blocks-signal">
                    <x-ui.table-cell colspan="5" class="py-12 text-center text-muted-foreground">
                        <x-lucide-users class="w-12 h-12 opacity-30 mx-auto mb-2" />
                        Нет подозрительных пользователей по заданному фильтру.
                    </x-ui.table-cell>
                </x-ui.table-row>
            @endforelse
        </x-ui.table-body>
    </x-ui.table>

    <!-- Пагинация -->
    <div class="flex items-center justify-end flex-wrap gap-2">
        <div class="text-xs text-muted-foreground">
            Показано {{ $this->suspiciousUsers->firstItem() ?? 0 }} - {{ $this->suspiciousUsers->lastItem() ?? 0 }} из {{ $this->suspiciousUsers->total() }}
        </div>
        {{ $this->suspiciousUsers->links('partials.pagination') }}
    </div>

    <!-- МОДАЛКА ПРОСМОТРА БЛОКИРОВОК -->
    <div wire:key="blockers-modal-{{ $viewingUserId }}" x-data="{ open: false }" x-init="open = $wire.showBlockersModal" x-show="open" x-cloak
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         @click.self="$wire.closeBlockersModal()"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        
        <div class="relative bg-card border border-border rounded-lg shadow-2xl max-w-lg w-full mx-4 overflow-hidden flex flex-col max-h-[80vh]">
            <div class="flex items-center justify-between p-4 border-b border-border shrink-0">
                <h2 class="text-lg font-semibold">Жалобы и блокировки</h2>
                <x-ui.button variant="ghost" size="icon-sm" @click="$wire.closeBlockersModal()">
                    <x-lucide-x class="w-5 h-5" />
                </x-ui.button>
            </div>

            <div class="p-4 overflow-y-auto space-y-3 little-scroll">
                @forelse($this->blockersList as $block)
                    @php 
                        $blocker = $block->blocker; 
                        $reasonEnum = \App\Enums\UserBlockReason::tryFrom($block->reason ?? '');
                    @endphp
                    <div wire:key="modal-blocker-{{ $block->id }}" class="flex items-center justify-between gap-2 p-2 rounded-md bg-muted/30 border border-border">                       

                          <a href="{{ route('admin.users.show', $blocker->id) }}" wire:navigate class="flex gap-2 items-center group">
                            <x-avatar src="{{ $blocker->avatar_url }}" name="{{ $blocker->name }}" size="sm" userId="{{ $blocker->id }}" showStatus="true" :isOnline="$blocker->is_online"/>                            
                            <div class="block min-w-0">
                                <div class="font-medium text-sm text-foreground flex items-center gap-1.5 group-hover:text-primary transition-colors">
                                    <x-user-status-sign :user="$blocker" />
                                    <span class="truncate">{{ $blocker->name }}</span>                                
                                    @if($blocker->has_active_premium)<x-lucide-crown class="w-3.5 h-3.5 text-yellow-500 shrink-0" />@endif                              
                                </div>
                                <div class="text-xs text-muted-foreground truncate">{{ $blocker->email }}</div>
                            </div>
                        </a>
                        @if($reasonEnum)
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-background {{ $reasonEnum->color() }}">
                                {{ $reasonEnum->label() }}
                            </span>
                        @endif
                    </div>
                @empty
                    <p class="text-center text-sm text-muted-foreground py-4">Блокировок не найдено.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>