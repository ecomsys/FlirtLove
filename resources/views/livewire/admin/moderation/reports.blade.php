<?php

use App\Actions\Admin\ModeratePhotoAction;
use App\Actions\Admin\ModerateReportAction;
use App\Actions\Admin\ToggleUserBanAction;
use App\Models\Photo;
use App\Models\Report;
use App\Models\User;
use App\Models\AdminLog;
use App\Notifications\ReportModerated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\Volt\Component;
use App\Enums\ReportResolution;
use Livewire\Attributes\Url;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public string $statusFilter = 'pending';
    public string $typeFilter = 'all';
    public int $perPage = 10;

    private ToggleUserBanAction $toggleUserBanAction;
    private ModeratePhotoAction $moderatePhotoAction;
    private ModerateReportAction $moderateReportAction;

    public function boot(
        ToggleUserBanAction $toggleUserBanAction,
        ModeratePhotoAction $moderatePhotoAction,
        ModerateReportAction $moderateReportAction
    ): void {
        $this->toggleUserBanAction = $toggleUserBanAction;
        $this->moderatePhotoAction = $moderatePhotoAction;
        $this->moderateReportAction = $moderateReportAction;
    }

        /**
     * Восстанавливаем фильтры из сессии при загрузке страницы.
     */
    public function mount(): void
    {
        $saved = session('moderate_reports', []);
        if (isset($saved['statusFilter'])) $this->statusFilter = $saved['statusFilter'];
        if (isset($saved['typeFilter'])) $this->typeFilter = $saved['typeFilter'];

        // ФИКС: Если мы перешли по ссылке с поиском (например, кликнули ID жалобы в профиле),
        // принудительно включаем фильтр "Все", чтобы жалоба отобразилась, даже если она уже решена.
        if (!empty($this->search)) {
            $this->statusFilter = 'all';
            // Сохраняем это в сессию, чтобы при перезагрузке фильтр не сбросился обратно на "pending"
            session(['moderate_reports' => array_merge($saved, ['statusFilter' => 'all'])]);
        }
    }

    public function updatingSearch(): void { $this->resetPage(); }
        public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->search = ''; // Очищаем поиск при смене вкладки
        
        session(['moderate_reports' => array_merge(session('moderate_reports', []), ['statusFilter' => $status])]);
        $this->resetPage();
    }

    public function updatedTypeFilter(string $value): void 
    { 
        $this->search = ''; // Очищаем поиск при смене типа жалобы
        
        session(['moderate_reports' => array_merge(session('moderate_reports', []), ['typeFilter' => $value])]);
        $this->resetPage(); 
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'typeFilter']);
        $this->statusFilter = 'pending'; 
        $this->typeFilter = 'all';       
        session()->forget('moderate_reports');
        $this->resetPage();
    }

    // ============================================
    // ДЕЙСТВИЯ МОДЕРАТОРА
    // ============================================

       public function resolve(int $reportId, string $resolution = 'warn'): void
    {
        $report = Report::find($reportId);
        if (!$report || $report->status !== 'pending') return;

        $resolutionEnum = ReportResolution::tryFrom($resolution) ?? ReportResolution::Warn;
        
        // Экшен сам закроет жалобу и отправит жалобщику ReportModerated
        $this->moderateReportAction->resolve($report, auth()->user(), $resolutionEnum, 'Решено модератором');

        // Если это предупреждение — отправляем НАРУШИТЕЛЮ нотификацию (т.к. экшен этого не делает)
        if ($resolutionEnum === ReportResolution::Warn && $report->reported) {
            $reasonText = 'Нарушение правил сервиса';
            $reportReasonEnum = \App\Enums\ReportReason::tryFrom($report->reason ?? '');
            if ($reportReasonEnum) {
                $reasonText = $reportReasonEnum->label();
            }
            $report->reported->notify(new \App\Notifications\UserWarned($reasonText));
        }

        $this->dispatch('show-toast', type: 'success', message: 'Жалоба решена. Нарушитель оповещен.');
    }

    public function toggleBan(int $userId, string $type = 'permanent', ?int $reportId = null): void
    {
        $user = User::withTrashed()->find($userId);
        if (!$user) return;

        $reasonText = 'Нарушение по жалобе пользователей';

        if ($reportId) {
            $report = Report::find($reportId);
            if ($report) {
                $reportReasonEnum = \App\Enums\ReportReason::tryFrom($report->reason ?? '');
                if ($reportReasonEnum) {
                    $reasonText = $reportReasonEnum->label();
                }
            }
        }

        $result = $this->toggleUserBanAction->execute($user, $reasonText, $type);

        if (!$result['success']) {
            $this->dispatch('show-toast', type: 'error', message: $result['message'] ?? 'Не удалось выполнить действие.');
            return;
        }

        if ($result['is_banned']) {
            $reports = Report::where('reported_id', $user->id)
                ->where('status', 'pending')
                ->with('reporter')
                ->get();

            $resolution = match($type) {
                'shadow' => ReportResolution::Shadowban,
                'temp' => ReportResolution::TempBan,
                default => ReportResolution::Ban
            };

            // Экшен сам закроет все жалобы и отправит жалобщикам ReportModerated
            $this->moderateReportAction->bulkResolveReports($reports, auth()->user(), $resolution);
        }
        
        $this->dispatch('show-toast', type: 'success', message: $result['message']);
    }

    public function rejectPhoto(int $photoId): void
    {
        $photo = Photo::withTrashed()->find($photoId);
        if (!$photo) return;

        DB::transaction(function () use ($photo) {
            $reports = Report::where('reportable_type', Photo::class)
                ->where('reportable_id', $photo->id)
                ->where('status', 'pending')
                ->with('reporter')
                ->get();

            if ($reports->isNotEmpty()) {
                // Экшен сам закроет жалобы и отправит жалобщикам уведомления
                $this->moderateReportAction->bulkResolveReports($reports, auth()->user(), ReportResolution::PhotoDeleted);
            }

            $this->moderatePhotoAction->reject($photo, auth()->user(), 'report_violation');
        });
        
        $this->dispatch('show-toast', type: 'success', message: 'Фото отклонено. Жалобщики оповещены.');
    }

    public function reject(int $reportId): void
    {
        $report = Report::find($reportId);
        if (!$report || $report->status !== 'pending') return;

        $this->moderateReportAction->reject($report, auth()->user(), 'Нет нарушения');
        $this->dispatch('show-toast', type: 'info', message: 'Жалоба отклонена');
    }   
    
    // ============================================
    // ВЫВОД ДАННЫХ (ОПТИМИЗИРОВАННЫЕ ЗАПРОСЫ)
    // ============================================

    #[Computed]
    public function reports()
    {
        $searchOperator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
        
        $avatarQuery = fn($q) => $q->select(['id', 'user_id', 'is_primary', 'status', 'path_thumb', 'path_medium', 'path_large', 'path_original'])->orderByDesc('is_primary')->limit(1);

        $reports = Report::query()
            ->with([
                // ФИКС: withTrashed() чтобы видеть имена/аватарки удаленных юзеров
                'reporter' => fn($q) => $q->withTrashed()->select('id', 'name', 'email', 'role', 'status', 'is_premium', 'premium_expires_at', 'last_seen', 'deleted_at')->with(['photos' => $avatarQuery]),
                'reported' => fn($q) => $q->withTrashed()->select('id', 'name', 'email', 'role', 'status', 'is_premium', 'premium_expires_at', 'last_seen', 'deleted_at')->with(['photos' => $avatarQuery]),
                'reportable'
            ])
            // ФИКС: withTrashed() внутри whereHas, чтобы жалобы не выпадали из выборки
            ->where(function ($q) {
                $q->whereNull('reporter_id')
                  ->orWhereHas('reporter', fn($q2) => $q2->withTrashed()->excludeStaff());
            })
            ->where(function ($q) {
                $q->whereNull('reported_id')
                  ->orWhereHas('reported', fn($q2) => $q2->withTrashed()->excludeStaff());
            })
            ->when($this->search, function ($query) use ($searchOperator) {
                $search = $this->search;
                $query->where(function ($q) use ($search, $searchOperator) {
                    $q->whereHas('reporter', function ($q2) use ($search, $searchOperator) {
                        $q2->withTrashed()->where('name', $searchOperator, "%{$search}%")
                           ->orWhere('email', $searchOperator, "%{$search}%");
                    })
                    ->orWhereHas('reported', function ($q2) use ($search, $searchOperator) {
                        $q2->withTrashed()->where('name', $searchOperator, "%{$search}%")
                           ->orWhere('email', $searchOperator, "%{$search}%");
                    })
                    ->orWhere('reason', $searchOperator, "%{$search}%")
                    ->orWhere('description', $searchOperator, "%{$search}%")
                    ->orWhereRaw("CAST(id AS TEXT) {$searchOperator} ?", ["%{$search}%"]);
                });
            })
            ->when($this->statusFilter !== 'all', fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter !== 'all', function ($q) {
                $type = $this->typeFilter === 'user' ? User::class : Photo::class;
                $q->where('reportable_type', $type);
            })
            ->latest('created_at')
            ->latest('id') 
            ->paginate($this->perPage);

        $reports->loadMorph('reportable', [
            Photo::class => ['user:id,name']
        ]);

        return $reports;
    }

    #[Computed]
    public function counts(): array
    {
        $baseQuery = Report::query()
            ->where(function ($q) {
                $q->whereNull('reporter_id')
                  ->orWhereHas('reporter', fn($q2) => $q2->withTrashed()->excludeStaff());
            })
            ->where(function ($q) {
                $q->whereNull('reported_id')
                  ->orWhereHas('reported', fn($q2) => $q2->withTrashed()->excludeStaff());
            })
            ->when($this->typeFilter !== 'all', function ($q) {
                $type = $this->typeFilter === 'user' ? User::class : Photo::class;
                $q->where('reportable_type', $type);
            });

        $stats = $baseQuery->selectRaw("
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
}; 
?>

<div class="space-y-6">
    <!-- Заголовок -->
    <div class="flex items-center gap-4">
        @php
            // Защита от зацикливания кнопки "Назад"
            $previousUrl = url()->previous();
            $backUrl = ($previousUrl && $previousUrl !== url()->current()) 
                ? $previousUrl 
                : route('admin.dashboard'); // Фоллбэк на главную админки
        @endphp

        <a href="{{ $backUrl }}" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors">
            <x-lucide-arrow-left class="w-5 h-5" />
        </a>
        
        <h1 class="text-2xl font-semibold flex items-center gap-2">
            Жалобы и поддержка
            @if($this->counts['pending'] > 0)
                <x-ui.badge variant="destructive" size="sm" wire:key="badge-pending">
                    {{ $this->counts['pending'] }} новых
                </x-ui.badge>
            @endif
        </h1> 
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
            <x-ui.select wire:model.live="typeFilter" wire:key="select-type">
                <x-ui.select-trigger class="w-40">
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
                <!-- ФИКС: Ключ содержит статус жалобы и статус юзера. При любом измененииLivewire перерисовывает строку целиком, сбрасывая состояние Alpine.js (Dropdown) -->
                <x-ui.table-row wire:key="report-{{ $report->id }}-{{ $report->status }}-{{ $report->reported?->status }}">
                    <x-ui.table-cell class="text-muted-foreground text-xs">#{{ $report->id }}</x-ui.table-cell>
                    
                    <!-- Жалобщик -->
                    <x-ui.table-cell>
                       <a href="{{ route('admin.users.show', $report->reporter?->id) }}" class="flex items-center gap-2 block group" wire:navigate>                                                 
                            <x-avatar src="{{ $report->reporter?->avatar_url }}" name="{{ $report->reporter?->name ?? 'Удален' }}" size="sm" userId="{{ $report->reporter_id }}" showStatus="true" :isOnline="$report->reporter?->is_online" />
                            <div>
                                <div class="flex gap-2 items-center group-hover:text-primary transition-colors">
                                    <x-user-status-sign :user="$report->reporter" />
                                    <span class="text-sm font-medium">{{ $report->reporter?->name ?? 'Удален' }}</span>
                                    @if($report->reporter?->has_active_premium)
                                        <x-lucide-crown class="w-3 h-3 text-yellow-500" />
                                    @endif      
                                    @if($report->reporter?->status === 'banned')
                                        <x-ui.badge variant="destructive" size="xs">Бан</x-ui.badge>
                                    @endif                                                                          
                                </div>                                    
                                <div class="text-xs text-muted-foreground">{{ $report->reporter?->email ?? '-' }}</div>
                            </div>
                        </a>
                    </x-ui.table-cell>

                    <!-- Объект жалобы -->
                    <x-ui.table-cell>
                        @if($report->reportable_type === \App\Models\User::class)
                            <a href="{{ route('admin.users.show', $report->reported?->id) }}" class="flex items-center gap-2 block group" wire:navigate>                           
                                <x-avatar src="{{ $report->reported?->avatar_url }}" name="{{ $report->reported?->name ?? 'Удален' }}" size="sm" userId="{{ $report->reported_id }}" showStatus="true" :isOnline="$report->reported?->is_online" />
                                <div>
                                    <div class="flex gap-2 items-center group-hover:text-primary transition-colors">
                                        <x-user-status-sign :user="$report->reported" />
                                        <span class="text-sm font-medium">{{ $report->reported?->name ?? 'Удален' }}</span>
                                        @if($report->reported?->has_active_premium)
                                            <x-lucide-crown class="w-3 h-3 text-yellow-500" />
                                        @endif      
                                        @if($report->reported?->status === 'banned')
                                            <x-ui.badge variant="destructive" size="xs">Бан</x-ui.badge>
                                        @endif                                                                                 
                                    </div>                                        
                                    <div class="text-xs text-muted-foreground">{{ $report->reported?->email ?? '-' }}</div>
                                </div>
                            </a>
                        @elseif($report->reportable_type === \App\Models\Photo::class)
                            <div class="flex items-center gap-2">
                                @if($report->reportable)
                                    @php $imgSrc = $report->reportable->thumb_url ?: $report->reportable->medium_url ?: $report->reportable->original_url; @endphp
                                    <img src="{{ $imgSrc ?: asset('images/no-image-placeholder.png') }}" class="w-10 h-10 object-cover rounded bg-muted" alt="photo">
                                    <div class="text-sm">
                                        <a href="{{ route('admin.moderation.photos', ['q' => $report->reportable_id]) }}" wire:navigate class="text-xs fomt-medium text-muted-foreground hover:text-primary" title="Найти в модерации">
                                            Фото #{{ $report->reportable_id }}
                                        </a>                                        
                                         <a href="{{ route('admin.users.show', $report->reportable->user_id) }}" wire:navigate class="block hover:text-primary transition-colors text-xs text-muted-foreground">
                                            <x-user-status-sign :user="$report->reported" />
                                            {{ $report->reportable->user?->name ?? 'Удален' }}
                                        </a>                                        
                                    </div>
                                @else
                                    <span class="text-sm text-muted-foreground">Фото удалено</span>
                                @endif
                            </div>
                        @else
                            <span class="text-sm text-muted-foreground">{{ class_basename($report->reportable_type) }} #{{ $report->reportable_id }}</span>
                        @endif
                    </x-ui.table-cell>

                    <!-- Причина -->
                    <x-ui.table-cell class="max-w-[25rem] whitespace-normal">
                        <div class="min-w-0">
                            @php 
                                $reasonEnum = \App\Enums\ReportReason::tryFrom($report->reason ?? '');
                            @endphp
                            @if($reasonEnum)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $reasonEnum->color() }}">
                                    {{ $reasonEnum->label() }}
                                </span>
                            @else
                                <p class="text-sm font-medium line-clamp-1">{{ ucfirst($report->reason) }}</p>
                            @endif
                            
                            @if($report->description)
                                <p class="text-xs text-muted-foreground line-clamp-2 mt-1">{{ $report->description }}</p>
                            @endif

                            @if($report->status !== 'pending' && $report->resolution)
                                @php 
                                    $resEnum = \App\Enums\ReportResolution::tryFrom($report->resolution);
                                @endphp
                                @if($resEnum)
                                    <div class="mt-1 text-[10px] text-muted-foreground">
                                        Решение: <span class="font-medium {{ $resEnum->color() }}">{{ $resEnum->label() }}</span>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </x-ui.table-cell>

                    <!-- Тип -->
                    <x-ui.table-cell>
                        @if($report->reportable_type === \App\Models\User::class)
                            <x-ui.badge variant="warning" size="xs">Пользователь</x-ui.badge>
                        @elseif($report->reportable_type === \App\Models\Photo::class)
                            <x-ui.badge variant="secondary" size="xs">Фото</x-ui.badge>
                        @else
                            <x-ui.badge variant="secondary" size="xs">{{ class_basename($report->reportable_type) }}</x-ui.badge>
                        @endif
                    </x-ui.table-cell>

                    <!-- Статус -->
                    <x-ui.table-cell>
                        @if($report->status === 'pending')
                            <x-ui.badge variant="destructive" size="sm">Ожидает</x-ui.badge>
                        @elseif($report->status === 'resolved')
                            <x-ui.badge variant="success" size="sm">Решена</x-ui.badge>
                        @else
                            <x-ui.badge variant="warning" size="sm">Отклонена</x-ui.badge>
                        @endif
                    </x-ui.table-cell>

                    <!-- Дата -->
                    <x-ui.table-cell class="text-muted-foreground text-xs">
                        {{ $report->created_at->format('d.m.Y') }}
                        <div class="text-[10px]">{{ $report->created_at->diffForHumans() }}</div>
                    </x-ui.table-cell>

                    <!-- Действия -->
                    <x-ui.table-cell class="text-right">
                        @if($report->status === 'pending')
                            <x-ui.dropdown-menu wire:key="dropdown-pending-{{ $report->id }}">
                                <x-ui.dropdown-menu-trigger>
                                    <x-ui.button variant="ghost" size="icon-sm">
                                        <x-lucide-more-horizontal class="w-4 h-4" />
                                    </x-ui.button>
                                </x-ui.dropdown-menu-trigger>
                                <x-ui.dropdown-menu-content align="end">
                                    
                                    <x-ui.dropdown-menu-label>Принять меры (Закрыть жалобу)</x-ui.dropdown-menu-label>
                                    
                                    @if($report->reported && $report->reported->role === 'user')
                                        @if($report->reported->status === 'banned' || $report->reported->status === 'shadowbanned')
                                            <x-ui.dropdown-menu-item wire:key="unban-{{ $report->id }}" wire:click="toggleBan({{ $report->reported->id }}, 'permanent', {{ $report->id }})" wire:confirm="Снять бан с пользователя?">
                                                <x-lucide-unlock class="w-4 h-4 text-green-500" />
                                                Разбанить пользователя
                                            </x-ui.dropdown-menu-item>
                                        @else
                                            <x-ui.dropdown-menu-item wire:key="ban-shadow-{{ $report->id }}" wire:click="toggleBan({{ $report->reported->id }}, 'shadow', {{ $report->id }})" wire:confirm="Применить теневой бан?">
                                                <x-lucide-eye-off class="w-4 h-4 text-purple-500" />
                                                Теневой бан
                                            </x-ui.dropdown-menu-item>
                                            <x-ui.dropdown-menu-item wire:key="ban-temp-{{ $report->id }}" wire:click="toggleBan({{ $report->reported->id }}, 'temp', {{ $report->id }})" wire:confirm="Забанить на 3 дня?">
                                                <x-lucide-clock class="w-4 h-4 text-yellow-500" />
                                                Бан на 3 дня
                                            </x-ui.dropdown-menu-item>
                                            <x-ui.dropdown-menu-item wire:key="ban-perm-{{ $report->id }}" wire:click="toggleBan({{ $report->reported->id }}, 'permanent', {{ $report->id }})" variant="destructive" wire:confirm="Забанить навсегда?">
                                                <x-lucide-lock class="w-4 h-4 text-red-500" />
                                                Вечный бан
                                            </x-ui.dropdown-menu-item>
                                        @endif
                                    @endif
                                    
                                    @if($report->reportable_type === \App\Models\Photo::class && $report->reportable)
                                        <x-ui.dropdown-menu-item wire:key="reject-photo-{{ $report->id }}" wire:click="rejectPhoto({{ $report->reportable_id }})" variant="destructive" wire:confirm="Отклонить фото (отправить в карантин) и закрыть жалобу?">
                                            <x-lucide-x-circle class="w-4 h-4" />
                                            Отклонить фото
                                        </x-ui.dropdown-menu-item>
                                    @endif

                                    <x-ui.dropdown-menu-item wire:key="warn-{{ $report->id }}" wire:click="resolve({{ $report->id }}, 'warn')">
                                        <x-lucide-alert-triangle class="w-4 h-4 text-yellow-500" />
                                        Вынести предупреждение
                                    </x-ui.dropdown-menu-item>

                                    <x-ui.dropdown-menu-separator />

                                    <x-ui.dropdown-menu-item wire:key="reject-{{ $report->id }}" wire:click="reject({{ $report->id }})">
                                        <x-lucide-x-circle class="w-4 h-4 text-muted-foreground" />
                                        Отклонить жалобу(все ок)
                                    </x-ui.dropdown-menu-item>
                                    
                                </x-ui.dropdown-menu-content>
                            </x-ui.dropdown-menu>               
                        @else
                            <span class="text-xs text-muted-foreground">—</span>
                        @endif
                    </x-ui.table-cell>
                </x-ui.table-row>
           @empty
            <x-ui.table-row wire:key="empty-state">
                <x-ui.table-cell colspan="8" class="py-12 text-center text-muted-foreground">
                    <div class="flex flex-col items-center gap-2">
                        <x-lucide-inbox class="w-12 h-12 opacity-30" />
                        <p>Нет жалоб</p>
                        @if(!empty($search) || $statusFilter !== 'pending' || $typeFilter !== 'all')
                            <x-ui.button wire:click="resetFilters" variant="outline" size="sm" wire:key="reset-filters">
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