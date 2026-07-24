<?php

use App\Models\Report;
use App\Models\User;
use App\Models\Photo;
use App\Notifications\ReportModerated;

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'pending';
    public string $typeFilter = 'all';
    public int $perPage = 10;

    private function checkAdminAccess(): void
    {
        if (!auth()->user()?->is_admin) {
            abort(403, 'Доступ запрещен. Только для администраторов.');
        }
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingTypeFilter(): void { $this->resetPage(); }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function setTypeFilter(string $type): void
    {
        $this->typeFilter = $type;
        $this->resetPage();
    }

    #[Computed]
    public function reports()
    {
        return Report::query()
            ->with(['user', 'reportedUser', 'photo'])
            ->when($this->search, function ($query) {
                $search = $this->search;
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                      ->orWhere('email', 'ilike', "%{$search}%");
                })->orWhereHas('reportedUser', function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                      ->orWhere('email', 'ilike', "%{$search}%");
                })->orWhere('reason', 'ilike', "%{$search}%");
            })
            ->when($this->statusFilter !== 'all', function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->typeFilter !== 'all', function ($query) {
                $query->where('type', $this->typeFilter);
            })
            ->latest()
            ->paginate($this->perPage);
    }

    #[Computed]
    public function counts()
    {
        //  Один запрос вместо четырех
        $stats = Report::selectRaw("
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            COUNT(*) as total
        ")->first();

        return [
            'pending' => (int) ($stats->pending ?? 0),
            'resolved' => (int) ($stats->resolved ?? 0),
            'rejected' => (int) ($stats->rejected ?? 0),
            'total' => (int) ($stats->total ?? 0),
        ];
    }

    public function resolve(int $reportId): void
    {
        $this->checkAdminAccess();
        
        $report = Report::find($reportId);
        if ($report && $report->status === 'pending') {
            DB::transaction(function () use ($report) {
                $report->update([
                    'status' => 'resolved',
                    'resolved_at' => now(),
                    'moderator_id' => auth()->id(),
                ]);
            });

            // Уведомляем только автора жалобы (согласно таблице)
            $report->user->notify(new ReportModerated($report, 'resolved'));

            $this->dispatch('$refresh');
            $this->dispatch('show-toast', type: 'success', message: 'Жалоба отмечена как решенная');
        }
    }

    public function reject(int $reportId): void
    {
        $this->checkAdminAccess();
        
        $report = Report::find($reportId);
        if ($report && $report->status === 'pending') {
            DB::transaction(function () use ($report) {
                $report->update([
                    'status' => 'rejected',
                    'resolved_at' => now(),
                    'moderator_id' => auth()->id(),
                ]);
            });
            
            $report->user->notify(new ReportModerated($report, 'rejected'));

            $this->dispatch('$refresh');
            $this->dispatch('show-toast', type: 'info', message: 'Жалоба отклонена');
        }
    }

    public function deleteReport(int $reportId): void
    {
        $this->checkAdminAccess();

        $report = Report::find($reportId);
        if ($report) {
            if ($report->status === 'pending') {
                $this->dispatch('show-toast', type: 'error', message: 'Нельзя удалить необработанную жалобу.');
                return;
            }
            
             DB::transaction(function () use ($report) {
                $report->delete();
            });
            $this->dispatch('show-toast', type: 'success', message: 'Жалоба удалена');
        }
    }

    public function deleteResolvedReports(): void
    {
        $this->checkAdminAccess();
        
        $count = Report::whereIn('status', ['resolved', 'rejected'])->count();
        
        if ($count === 0) {
            $this->dispatch('show-toast', type: 'info', message: 'Нет жалоб для удаления');
            return;
        }
        
        DB::transaction(function () use ($count) {
            Report::whereIn('status', ['resolved', 'rejected'])->delete();
            
            Log::info('Массовое удаление жалоб', [
                'count' => $count,
                'moderator_id' => auth()->id(),
            ]);
        });
        
        $this->dispatch('$refresh');
        $this->dispatch('show-toast', type: 'success', message: "Удалено {$count} жалоб");
    }

    public function toggleBan(int $userId): void
    {
        $this->checkAdminAccess();     

        $user = User::find($userId);
        if ($user && !$user->is_admin) {
            DB::transaction(function () use ($user) {
                $newStatus = !$user->is_banned;
                $user->update(['is_banned' => $newStatus]);
                
                if ($newStatus) {
                    $reports = Report::where('reported_user_id', $user->id)
                        ->where('status', 'pending')
                        ->get();
                    
                    foreach ($reports as $report) {
                        $report->update([
                            'status' => 'resolved',
                            'resolved_at' => now(),
                            'moderator_id' => auth()->id(),
                        ]);
                        
                        $report->user->notify(new ReportModerated($report, 'user_banned'));
                    }
                    
                    //  передаем null вместо фейковой модели Report
                    if ($photo->user) {
                    $user->notify(new ReportModerated(
                        null, 
                        'user_banned',
                        "Вы были забанены на основании жалоб пользователей."
                    ));
                    }
                }
            });
            
            $this->dispatch('$refresh');
            $this->dispatch('show-toast', 
                type: 'success', 
                message: $user->is_banned ? "Пользователь {$user->name} забанен" : "Пользователь {$user->name} разбанен"
            );
        }
    }

    public function deletePhoto(int $photoId): void
    {
        $this->checkAdminAccess();
        
        $photo = Photo::find($photoId);
        if ($photo) {
            DB::transaction(function () use ($photo, $photoId) {
                $reports = Report::where('photo_id', $photoId)
                    ->where('status', 'pending')
                    ->get();
                
                foreach ($reports as $report) {
                    $report->update([
                        'status' => 'resolved',
                        'resolved_at' => now(),
                        'moderator_id' => auth()->id(),
                    ]);
                    
                    $report->user->notify(new ReportModerated($report, 'photo_deleted'));
                }
                
                //  передаем null вместо фейковой модели Report
                if ($photo->user) {
                $photo->user->notify(new ReportModerated(
                    null, 
                    'photo_deleted',
                    "Ваше фото #{$photo->id} было удалено по жалобе пользователей."
                ));
                }
                
                // Физическое удаление (если нужно, как в фото-модерации)
                if (!filter_var($photo->path, FILTER_VALIDATE_URL)) {
                    Storage::disk('public')->delete($photo->path);
                }
                $photo->delete();
            });
            
            $this->dispatch('$refresh');
            $this->dispatch('show-toast', type: 'success', message: 'Фото удалено. Все жалобы на него решены.');
        }
    }
}; 
?>
<div class="space-y-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <h1 class="text-2xl font-semibold flex items-center gap-2">
            Жалобы и поддержка
            @if($this->counts['pending'] > 0)
                <x-ui.badge variant="destructive" size="sm" wire:key="badge-pending">
                    {{ $this->counts['pending'] }} новых
                </x-ui.badge>
            @endif
        </h1>
        
        @if($this->counts['resolved'] > 0 || $this->counts['rejected'] > 0)
          <x-ui.alert-dialog wire:key="alert-clear-archive">
            <x-ui.alert-dialog-trigger>
                <x-ui.button 
                    variant="destructive" 
                    size="sm"
                    wire:loading.attr="disabled"
                    wire:target="deleteResolvedReports"
                    wire:key="btn-clear-archive"
                >
                    <span wire:loading.remove wire:target="deleteResolvedReports">
                        <x-lucide-trash-2 class="w-4 h-4 inline" />
                        Очистить архив ({{ $this->counts['resolved'] + $this->counts['rejected'] }})
                    </span>
                    <span wire:loading wire:target="deleteResolvedReports">
                        <x-ui.spinner class="w-4 h-4 inline" />
                        Удаление...
                    </span>
                </x-ui.button>
            </x-ui.alert-dialog-trigger>
            
            <x-ui.alert-dialog-content>
                <x-ui.alert-dialog-header>
                    <x-ui.alert-dialog-title>⚠️ Очистка архива жалоб</x-ui.alert-dialog-title>
                    <x-ui.alert-dialog-description>
                        Вы уверены? Будут удалены все решенные и отклоненные жалобы ({{ $this->counts['resolved'] + $this->counts['rejected'] }} шт.).
                        Это действие <strong class="text-destructive">нельзя отменить</strong>.
                    </x-ui.alert-dialog-description>
                </x-ui.alert-dialog-header>
                <x-ui.alert-dialog-footer>
                    <x-ui.alert-dialog-cancel>Отмена</x-ui.alert-dialog-cancel>
                    <x-ui.alert-dialog-action 
                        wire:click="deleteResolvedReports"
                        wire:loading.attr="disabled"
                        wire:target="deleteResolvedReports"
                        wire:key="action-clear-archive"
                    >
                        <span wire:loading.remove wire:target="deleteResolvedReports">
                            <x-lucide-trash-2 class="w-4 h-4 inline" />
                            Очистить архив
                        </span>
                        <span wire:loading wire:target="deleteResolvedReports">
                            <x-ui.spinner class="w-4 h-4 inline" />
                            Удаление...
                        </span>
                    </x-ui.alert-dialog-action>
                </x-ui.alert-dialog-footer>
            </x-ui.alert-dialog-content>
        </x-ui.alert-dialog>
        @endif
    </div>

    <!-- Фильтры -->
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex flex-wrap gap-1.5">
            <x-ui.button wire:click="setStatusFilter('pending')" variant="{{ $statusFilter === 'pending' ? 'default' : 'secondary' }}" size="sm" wire:key="filter-pending">
                Ожидают <x-ui.badge size="xs" variant="destructive">{{ $this->counts['pending'] }}</x-ui.badge>
            </x-ui.button>
            
            <x-ui.button wire:click="setStatusFilter('all')" variant="{{ $statusFilter === 'all' ? 'default' : 'secondary' }}" size="sm" wire:key="filter-all">
                Все <x-ui.badge size="xs">{{ $this->counts['total'] }}</x-ui.badge>
            </x-ui.button>
            
            <x-ui.button wire:click="setStatusFilter('resolved')" variant="{{ $statusFilter === 'resolved' ? 'default' : 'secondary' }}" size="sm" wire:key="filter-resolved">
                Решены <x-ui.badge size="xs" variant="success">{{ $this->counts['resolved'] }}</x-ui.badge>
            </x-ui.button>
            
            <x-ui.button wire:click="setStatusFilter('rejected')" variant="{{ $statusFilter === 'rejected' ? 'default' : 'secondary' }}" size="sm" wire:key="filter-rejected">
                Отклонены <x-ui.badge size="xs" variant="warning">{{ $this->counts['rejected'] }}</x-ui.badge>
            </x-ui.button>
        </div>

        <div class="flex items-center gap-2 ml-auto">
            <x-ui.select wire:model.live="typeFilter" class="w-40" wire:key="select-type">
                <x-ui.select-trigger>
                    <x-ui.select-value placeholder="Тип жалобы" />
                </x-ui.select-trigger>
                <x-ui.select-content>
                    <x-ui.select-item value="all" wire:key="type-all">Все типы</x-ui.select-item>
                    <x-ui.select-item value="user" wire:key="type-user">На пользователя</x-ui.select-item>
                    <x-ui.select-item value="photo" wire:key="type-photo">На фото</x-ui.select-item>
                </x-ui.select-content>
            </x-ui.select>

            <div class="relative w-64" wire:key="search-wrapper">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск по имени или причине..." class="pl-9 pr-8" wire:key="search-input" />
                <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                @if(!empty($search))
                    <button wire:click="$set('search', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground" wire:key="clear-search">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Таблица жалоб -->
    <x-ui.table wire:key="reports-table">
        <x-ui.table-header>
            <x-ui.table-row>
                <x-ui.table-head class="w-12">ID</x-ui.table-head>
                <x-ui.table-head>Жалобщик</x-ui.table-head>
                <x-ui.table-head>На кого/что</x-ui.table-head>
                <x-ui.table-head>Причина</x-ui.table-head>
                <x-ui.table-head>Тип</x-ui.table-head>
                <x-ui.table-head>Статус</x-ui.table-head>
                <x-ui.table-head>Дата</x-ui.table-head>
                <x-ui.table-head class="text-right">Действия</x-ui.table-head>
            </x-ui.table-row>
        </x-ui.table-header>

        <x-ui.table-body>
            @forelse ($this->reports as $report)                
                <x-ui.table-row wire:key="report-{{ $report->id }}">
                    <x-ui.table-cell class="text-muted-foreground text-xs">{{ $report->id }}</x-ui.table-cell>
                    <x-ui.table-cell>
                        <div class="flex items-center gap-2">
                            <x-avatar src="{{ $report->user?->avatar_url }}" name="{{ $report->user?->name ?? 'Удален' }}" size="sm" userId="{{ $report->user_id }}" showStatus="true" />
                            <div>
                                <div class="font-medium text-sm">{{ $report->user?->name ?? 'Удален' }}</div>
                                <div class="text-xs text-muted-foreground">{{ $report->user?->email ?? '-' }}</div>
                            </div>
                        </div>
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        @if($report->type === 'user')
                            <div class="flex items-center gap-2">
                                <x-avatar src="{{ $report->reportedUser?->avatar_url }}" name="{{ $report->reportedUser?->name ?? 'Удален' }}" size="sm" userId="{{ $report->reported_user_id }}" showStatus="true" />
                                <div>
                                    <div class="font-medium text-sm flex items-center gap-1">
                                        {{ $report->reportedUser?->name ?? 'Удален' }}
                                        @if($report->reportedUser?->is_banned)
                                            <x-ui.badge variant="destructive" size="xs">Забанен</x-ui.badge>
                                        @endif
                                    </div>
                                    <div class="text-xs text-muted-foreground">{{ $report->reportedUser?->email ?? '-' }}</div>
                                </div>
                            </div>
                        @else
                            <div class="flex items-center gap-2">
                                @if($report->photo)
                                    <img src="{{ $report->photo->thumb_url ?? $report->photo->url }}" class="w-10 h-10 object-cover rounded" alt="photo">
                                    <span class="text-sm">Фото #{{ $report->photo_id }}</span>
                                @else
                                    <span class="text-sm text-muted-foreground">Фото удалено</span>
                                @endif
                            </div>
                        @endif
                    </x-ui.table-cell>
                    <x-ui.table-cell class="max-w-[25rem] whitespace-normal">
                        <div class="min-w-0">
                            <p class="text-sm line-clamp-2">{{ $report->reason }}</p>
                        </div>
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        @if($report->type === 'user')
                            <x-ui.badge variant="warning" size="xs">Пользователь</x-ui.badge>
                        @else
                            <x-ui.badge variant="secondary" size="xs">Фото</x-ui.badge>
                        @endif
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        @if($report->status === 'pending')
                            <x-ui.badge variant="destructive" size="sm">Ожидает</x-ui.badge>
                        @elseif($report->status === 'resolved')
                            <x-ui.badge variant="success" size="sm">Решена</x-ui.badge>
                        @else
                            <x-ui.badge variant="warning" size="sm">Отклонена</x-ui.badge>
                        @endif
                    </x-ui.table-cell>
                    <x-ui.table-cell class="text-muted-foreground text-xs">
                        {{ $report->created_at->format('d.m.Y') }}
                        <div class="text-[10px]">{{ $report->created_at->diffForHumans() }}</div>
                    </x-ui.table-cell>
                    <x-ui.table-cell class="text-right">
                        @if($report->status === 'pending')
                            <x-ui.dropdown-menu wire:key="dropdown-pending-{{ $report->id }}">
                                <x-ui.dropdown-menu-trigger>
                                    <x-ui.button variant="ghost" size="icon-sm">
                                        <x-lucide-more-horizontal class="w-4 h-4" />
                                    </x-ui.button>
                                </x-ui.dropdown-menu-trigger>
                                <x-ui.dropdown-menu-content align="end">
                                    <x-ui.dropdown-menu-item wire:key="resolve-{{ $report->id }}" wire:click="resolve({{ $report->id }})">
                                        <x-lucide-check-circle class="w-4 h-4 text-green-500" />
                                        Отметить как решенную
                                    </x-ui.dropdown-menu-item>
                                    <x-ui.dropdown-menu-item wire:key="reject-{{ $report->id }}" wire:click="reject({{ $report->id }})">
                                        <x-lucide-x-circle class="w-4 h-4 text-yellow-500" />
                                        Отклонить жалобу
                                    </x-ui.dropdown-menu-item>
                                    
                                    @if($report->type === 'user' && $report->reportedUser && !$report->reportedUser->is_admin)
                                        <x-ui.dropdown-menu-separator />
                                        <x-ui.dropdown-menu-item wire:key="toggleBan-{{ $report->reported_user_id }}" wire:click="toggleBan({{ $report->reported_user_id }})">
                                            <x-lucide-lock class="w-4 h-4" />
                                            Снять/наложить бан
                                        </x-ui.dropdown-menu-item>
                                    @endif
                                    
                                    @if($report->type === 'photo' && $report->photo)
                                        <x-ui.dropdown-menu-separator />
                                        <x-ui.dropdown-menu-item wire:key="deletePhoto-{{ $report->photo_id }}" wire:click="deletePhoto({{ $report->photo_id }})" variant="destructive">
                                            <x-lucide-trash-2 class="w-4 h-4" />
                                            Удалить фото
                                        </x-ui.dropdown-menu-item>
                                    @endif
                                </x-ui.dropdown-menu-content>
                            </x-ui.dropdown-menu>
                        @else
                            <x-ui.dropdown-menu wire:key="dropdown-resolved-{{ $report->id }}">
                                <x-ui.dropdown-menu-trigger>
                                    <x-ui.button variant="ghost" size="icon-sm">
                                        <x-lucide-more-horizontal class="w-4 h-4" />
                                    </x-ui.button>
                                </x-ui.dropdown-menu-trigger>
                                <x-ui.dropdown-menu-content align="end">
                                    <x-ui.dropdown-menu-item wire:key="deleteReport-{{ $report->id }}" wire:click="deleteReport({{ $report->id }})" variant="destructive">
                                        <x-lucide-trash-2 class="w-4 h-4" />
                                        Удалить жалобу
                                    </x-ui.dropdown-menu-item>
                                </x-ui.dropdown-menu-content>
                            </x-ui.dropdown-menu>
                        @endif
                    </x-ui.table-cell>
                </x-ui.table-row>
            @empty
                <x-ui.table-row wire:key="empty-state">
                    <x-ui.table-cell colspan="8" class="py-12 text-center text-muted-foreground">
                        <div class="flex flex-col items-center gap-2">
                            <x-lucide-inbox class="w-12 h-12 opacity-30" />
                            <p>Нет жалоб</p>
                            @if(!empty($search) || $statusFilter !== 'all' || $typeFilter !== 'all')
                                <x-ui.button wire:click="$set('search', ''); $set('statusFilter', 'all'); $set('typeFilter', 'all')" variant="outline" size="sm" wire:key="reset-filters">
                                    Сбросить фильтры
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
            Показано {{ $this->reports->firstItem() ?? 0 }} - {{ $this->reports->lastItem() ?? 0 }} из {{ $this->reports->total() }}
        </div>
        {{ $this->reports->links('partials.pagination') }}
    </div>
</div>