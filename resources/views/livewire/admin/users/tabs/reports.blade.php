<?php

use App\Enums\ReportReason;
use App\Enums\ReportResolution;
use App\Models\Report;
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

    #[Url(as: 'made_page')] 
    public int $madePage = 1;
    
    #[Url(as: 'received_page')] 
    public int $receivedPage = 1;

    public function mount(int $userId): void
    {
        $this->userId = $userId;
    }

    #[Computed]
    public function user(): User
    {
        return User::withTrashed()->findOrFail($this->userId);
    }

    private function getAvatarQuery(): \Closure
    {
        return fn($q) => $q->select('id', 'name', 'email', 'status', 'is_premium', 'premium_expires_at', 'last_seen')
            ->with(['photos' => fn($sq) => $sq->select('id', 'user_id', 'is_primary', 'status', 'path_thumb')->orderByDesc('is_primary')->limit(1)]);
    }

    #[Computed]
    public function reportsMade()
    {
        return Report::where('reporter_id', $this->userId)
            ->with(['reportable', 'reported' => $this->getAvatarQuery(), 'admin:id,name'])
            ->latest()
            ->paginate(5, ['*'], 'madePage');
    }

    #[Computed]
    public function reportsReceived()
    {
        return Report::where('reported_id', $this->userId)
            ->with(['reportable', 'reporter' => $this->getAvatarQuery(), 'admin:id,name'])
            ->latest()
            ->paginate(5, ['*'], 'receivedPage');
    }

    #[On('user-action-performed')] 
    public function refreshUser(): void
    {
        unset($this->reportsMade);
        unset($this->reportsReceived);
    }
}; 
?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    
    {{-- ЛЕВАЯ КОЛОНКА: ЖАЛОБЫ ОТ ЮЗЕРА --}}
    <div class="space-y-4">
        <h3 class="text-sm font-semibold flex items-center gap-2">
            <x-lucide-flag class="w-4 h-4 text-blue-500" /> Пожаловался на ({{ $this->reportsMade->total() }})
        </h3>

        @if($this->reportsMade->isEmpty())
            <div class="p-4 bg-muted/20 rounded-lg border border-border text-center text-xs text-muted-foreground">
                Пользователь не подавал жалоб.
            </div>
        @else
            <x-ui.table>
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-ui.table-head class="w-16">ID</x-ui.table-head>
                        <x-ui.table-head>Пользователь</x-ui.table-head>
                        <x-ui.table-head>Причина / Статус</x-ui.table-head>
                        <x-ui.table-head>Дата</x-ui.table-head>
                    </x-ui.table-row>
                </x-ui.table-header>
                <x-ui.table-body>
                    @foreach($this->reportsMade as $report)
                        @php $targetUser = $report->reported; @endphp
                        <x-ui.table-row wire:key="made-{{ $report->id }}">
                            <x-ui.table-cell class="text-xs font-mono whitespace-nowrap">
                                <a href="{{ route('admin.moderation.reports', ['q' => $report->id]) }}" wire:navigate class="text-blue-500 hover:underline" title="Найти в общей очереди">
                                    #{{ $report->id }}
                                </a>
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
                            <x-ui.table-cell>
                                <div class="flex flex-col gap-1">
                                    @php $reasonEnum = ReportReason::tryFrom($report->reason ?? ''); @endphp
                                    @if($reasonEnum)
                                    <div class="block">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $reasonEnum->color() }}" title="{{ $report->description }}">
                                            {{ $reasonEnum->label() }}
                                        </span>
                                    </div>
                                    @endif
                                    @if($report->status === 'pending')
                                        <x-ui.badge variant="warning" size="xs">Ожидает</x-ui.badge>
                                    @elseif($report->status === 'resolved')
                                        <x-ui.badge variant="success" size="xs">Решена</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="secondary" size="xs">Отклонена</x-ui.badge>
                                    @endif
                                    @php $resolutionEnum = ReportResolution::tryFrom($report->resolution ?? ''); @endphp
                                    @if($resolutionEnum)
                                    <div class="block text-[10px] text-muted-foreground ">
                                        <span>Решение: </span>
                                        <span class="{{ $resolutionEnum->color() }}">{{ $resolutionEnum->label() }}</span>
                                    </div>
                                    @endif
                                </div>
                            </x-ui.table-cell>
                            <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap">
                                {{ $report->created_at->diffForHumans() }}
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @endforeach
                </x-ui.table-body>
            </x-ui.table>
            <div class="mt-2">{{ $this->reportsMade->links('partials.pagination') }}</div>
        @endif
    </div>

    {{-- ПРАВАЯ КОЛОНКА: ЖАЛОБЫ НА ЮЗЕРА --}}
    <div class="space-y-4">
        <h3 class="text-sm font-semibold flex items-center gap-2">
            <x-lucide-alert-octagon class="w-4 h-4 text-destructive" /> Жалобы на пользователя ({{ $this->reportsReceived->total() }})
        </h3>

        @if($this->reportsReceived->isEmpty())
            <div class="p-4 bg-muted/20 rounded-lg border border-border text-center text-xs text-muted-foreground">
                Жалоб на пользователя не поступало.
            </div>
        @else
            <x-ui.table>
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-ui.table-head class="w-16">ID</x-ui.table-head>
                        <x-ui.table-head>Жалобщик</x-ui.table-head>
                        <x-ui.table-head>Причина / Статус</x-ui.table-head>
                        <x-ui.table-head>Дата</x-ui.table-head>
                    </x-ui.table-row>
                </x-ui.table-header>
                <x-ui.table-body>
                    @foreach($this->reportsReceived as $report)
                        @php $targetUser = $report->reporter; @endphp
                        <x-ui.table-row wire:key="received-{{ $report->id }}">
                            <x-ui.table-cell class="text-xs font-mono whitespace-nowrap">
                                <a href="{{ route('admin.moderation.reports', ['q' => $report->id]) }}" wire:navigate class="text-blue-500 hover:underline" title="Найти в общей очереди">
                                    #{{ $report->id }}
                                </a>
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
                                        <span class="text-sm text-muted-foreground italic">Аноним/Удален</span>
                                    </div>
                                @endif
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                <div class="flex flex-col gap-1.5">
                                    @php $reasonEnum = ReportReason::tryFrom($report->reason ?? ''); @endphp
                                    @if($reasonEnum)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $reasonEnum->color() }}" title="{{ $report->description }}">
                                            {{ $reasonEnum->label() }}
                                        </span>
                                    @endif
                                    @if($report->status === 'pending')
                                        <x-ui.badge variant="warning" size="xs">Ожидает</x-ui.badge>
                                    @elseif($report->status === 'resolved')
                                        <x-ui.badge variant="success" size="xs">Решена</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="secondary" size="xs">Отклонена</x-ui.badge>
                                    @endif
                                    @php $resolutionEnum = ReportResolution::tryFrom($report->resolution ?? ''); @endphp
                                    @if($resolutionEnum)
                                        <span class="text-[10px] text-muted-foreground">{{ $resolutionEnum->label() }}</span>
                                    @endif
                                </div>
                            </x-ui.table-cell>
                            <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap">
                                {{ $report->created_at->diffForHumans() }}
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @endforeach
                </x-ui.table-body>
            </x-ui.table>
            <div class="mt-2">{{ $this->reportsReceived->links('partials.pagination') }}</div>
        @endif
    </div>

</div>