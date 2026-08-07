<?php

use App\Enums\ReportReason;
use App\Enums\ReportResolution;
use App\Models\Report;
use App\Models\User;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component 
{
    use WithPagination;

    public User $user;

    // Раздельные страницы для независимой пагинации
    #[Url(as: 'made_page')] 
    public int $madePage = 1;
    
    #[Url(as: 'received_page')] 
    public int $receivedPage = 1;

    public function with(): array
    {
        $avatarQuery = fn($q) => $q->select('id', 'name', 'email', 'status', 'is_premium', 'premium_expires_at', 'last_seen')
            ->with(['photos' => fn($sq) => $sq->select('id', 'user_id', 'is_primary', 'status', 'path_thumb', 'path_medium', 'path_large', 'path_original')->orderByDesc('is_primary')->limit(1)]);

        // Жалобы, которые подал этот юзер (грузим аватарку цели)
        $reportsMade = Report::where('reporter_id', $this->user->id)
            ->with(['reportable', 'reported' => $avatarQuery, 'admin:id,name'])
            ->latest()
            ->paginate(5, ['*'], 'madePage');

        // Жалобы, которые подали на этого юзера (грузим аватарку жалобщика)
        $reportsReceived = Report::where('reported_id', $this->user->id)
            ->with(['reportable', 'reporter' => $avatarQuery, 'admin:id,name'])
            ->latest()
            ->paginate(5, ['*'], 'receivedPage');

        return [
            'reportsMade' => $reportsMade,
            'reportsReceived' => $reportsReceived
        ];
    }
}; 
?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    
    {{-- ЛЕВАЯ КОЛОНКА: ЖАЛОБЫ ОТ ЮЗЕРА --}}
    <div class="space-y-4">
        <h3 class="text-sm font-semibold flex items-center gap-2">
            <x-lucide-flag class="w-4 h-4 text-blue-500" /> Пожаловался на ({{ $reportsMade->total() }})
        </h3>

        @if($reportsMade->isEmpty())
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
                    @foreach($reportsMade as $report)
                        @php 
                            $reasonEnum = ReportReason::tryFrom($report->reason ?? '');
                            $resolutionEnum = ReportResolution::tryFrom($report->resolution ?? '');
                        @endphp
                        <x-ui.table-row wire:key="made-{{ $report->id }}">
                            <x-ui.table-cell class="text-muted-foreground text-xs font-mono">
                                <a href="{{ route('admin.moderation.reports', ['q' => $report->id]) }}" wire:navigate class="hover:text-primary" title="Найти в общей очереди">
                                    #{{ $report->id }}
                                </a>
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                @if($report->reported)
                                    <a href="{{ route('admin.users.show', $report->reported->id) }}" wire:navigate class="flex items-center gap-2 group">
                                        <x-avatar src="{{ $report->reported->avatar_url }}" name="{{ $report->reported->name }}" size="sm" userId="{{ $report->reported->id }}" showStatus="true" :isOnline="$report->reported->is_online"/>
                                        <div class="flex flex-col min-w-0">
                                            <span class="text-sm font-medium group-hover:text-primary flex items-center gap-1.5">
                                                <x-user-status-sign :user="$report->reported" />
                                                {{ $report->reported->name }}
                                            </span>
                                            <span class="text-xs text-muted-foreground truncate">{{ $report->reported->email }}</span>
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
                                <div class="flex flex-col gap-1.5">
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
            <div class="mt-2">{{ $reportsMade->links('partials.pagination') }}</div>
        @endif
    </div>

    {{-- ПРАВАЯ КОЛОНКА: ЖАЛОБЫ НА ЮЗЕРА --}}
    <div class="space-y-4">
        <h3 class="text-sm font-semibold flex items-center gap-2">
            <x-lucide-alert-octagon class="w-4 h-4 text-destructive" /> Жалобы на пользователя ({{ $reportsReceived->total() }})
        </h3>

        @if($reportsReceived->isEmpty())
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
                    @foreach($reportsReceived as $report)
                        @php 
                            $reasonEnum = ReportReason::tryFrom($report->reason ?? '');
                            $resolutionEnum = ReportResolution::tryFrom($report->resolution ?? '');
                        @endphp
                        <x-ui.table-row wire:key="received-{{ $report->id }}">
                            <x-ui.table-cell class="text-muted-foreground text-xs font-mono">
                                <a href="{{ route('admin.moderation.reports', ['q' => $report->id]) }}" wire:navigate class="hover:text-primary" title="Найти в общей очереди">
                                    #{{ $report->id }}
                                </a>
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                @if($report->reporter)
                                    <a href="{{ route('admin.users.show', $report->reporter->id) }}" wire:navigate class="flex items-center gap-2 group">
                                        <x-avatar src="{{ $report->reporter->avatar_url }}" name="{{ $report->reporter->name }}" size="sm" userId="{{ $report->reporter->id }}" showStatus="true" :isOnline="$report->reporter->is_online"/>
                                        <div class="flex flex-col min-w-0">
                                            <span class="text-sm font-medium group-hover:text-primary flex items-center gap-1.5">
                                                <x-user-status-sign :user="$report->reporter" />
                                                {{ $report->reporter->name }}
                                            </span>
                                            <span class="text-xs text-muted-foreground truncate">{{ $report->reporter->email }}</span>
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
            <div class="mt-2">{{ $reportsReceived->links('partials.pagination') }}</div>
        @endif
    </div>

</div>