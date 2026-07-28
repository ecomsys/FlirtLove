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

/**
 * Компонент модерации жалоб (пользователи и фото).
 * Обрабатывает фильтрацию, массовое удаление, бан пользователей и удаление фото.
 * Уведомления отправаются через очереди (ShouldQueue).
 */
new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    /** @var string Поисковый запрос (имя, email или причина жалобы) */
    public string $search = '';
    
    /** @var string Текущий фильтр статуса (all, pending, resolved, rejected) */
    public string $statusFilter = 'pending';
    
    /** @var string Текущий фильтр типа жалобы (all, user, photo) */
    public string $typeFilter = 'all';
    
    /** @var int Количество элементов на странице */
    public int $perPage = 10;

    /**
     * Инициализация компонента.
     * Восстанавливает сохраненные в сессии фильтры, чтобы админ не терял контекст при перезагрузке.
     */
    public function mount()
    {
        $saved = session('moderate_reports', []);
        
        if (isset($saved['statusFilter'])) {
            $this->statusFilter = $saved['statusFilter'];
        }
        if (isset($saved['typeFilter'])) {
            $this->typeFilter = $saved['typeFilter'];
        }
    }

    /**
     * Проверка прав доступа.
     * Вызывается перед любым действием модератора.
     */
    private function checkAdminAccess(): void
    {
        if (!auth()->user()?->is_admin) {
            abort(403, 'Доступ запрещен. Только для администраторов.');
        }
    }

    /**
     * Хук Livewire: сброс пагинации при вводе поиска.
     */
    public function updatingSearch(): void { $this->resetPage(); }
      
    /**
     * Хук Livewire: срабатывает после изменения типа жалобы в Select.
     * Сохраняет выбор в сессию и сбрасывает пагинацию.
     * 
     * @param string $value Выбранное значение (all, user, photo)
     */
    public function updatedTypeFilter($value): void 
    { 
        session([
            'moderate_reports' => array_merge(
                session('moderate_reports', []),
                ['typeFilter' => $value]
            )
        ]);
        
        $this->resetPage(); 
    }

    /**
     * Устанавливает фильтр статуса (вызывается по клику на кнопки).
     * Сохраняет выбор в сессию и сбрасывает пагинацию.
     * 
     * @param string $status Выбранный статус
     */
    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        
        session([
            'moderate_reports' => array_merge(
                session('moderate_reports', []),
                ['statusFilter' => $status]
            )
        ]);
        
        $this->resetPage();
    }

    /**
     * Полный сброс всех фильтров и очистка сессии.
     * Вызывается при нажатии кнопки "Сбросить фильтры" в пустом состоянии.
     */
    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'typeFilter']);
        $this->statusFilter = 'pending'; 
        $this->typeFilter = 'all';       
        
        session()->forget('moderate_reports');
        $this->resetPage();
    }

    /**
     * Вычисляемое свойство: список жалоб с пагинацией.
     * Исключает жалобы, где замешаны админы (как жалобщики или нарушители).
     */
    #[Computed]
    public function reports()
    {
        return Report::query()
            ->with(['user', 'reportedUser', 'photo'])
            ->excludeAdmins() 
            // ВАЖНО: orWhere обернут в замыкание where(), 
            // чтобы поиск не ломал основные фильтры (статус и тип).
            ->when($this->search, function ($query) {
                $search = $this->search;
                $query->where(function ($q) use ($search) {
                    $q->whereHas('user', function ($q2) use ($search) {
                        $q2->where('name', 'ilike', "%{$search}%")
                           ->orWhere('email', 'ilike', "%{$search}%");
                    })
                    ->orWhereHas('reportedUser', function ($q2) use ($search) {
                        $q2->where('name', 'ilike', "%{$search}%")
                           ->orWhere('email', 'ilike', "%{$search}%");
                    })
                    ->orWhere('reason', 'ilike', "%{$search}%");
                });
            })
            ->when($this->statusFilter !== 'all', fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter !== 'all', fn($q) => $q->where('type', $this->typeFilter))
            ->latest()
            ->paginate($this->perPage);
    }

    /**
     * Вычисляемое свойство: счетчики для бейджей.
     * Оптимизация: один SQL-запрос вместо четырех отдельных COUNT().
     * Также исключает админов.
     */
    #[Computed]
    public function counts()
    {
        $baseQuery = Report::query()->excludeAdmins(); 

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

    /**
     * Одобрить (решить) жалобу.
     */
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

            // Защита от NullPointer: автор жалобы мог быть удален к этому моменту.
            // Уведомление уходит в очередь (ShouldQueue).
            if ($report->user) {
                $report->user->notify(new ReportModerated($report, 'resolved'));
            }

            $this->dispatch('$refresh');
            $this->dispatch('show-toast', type: 'success', message: 'Жалоба отмечена как решенная');
        }
    }

    /**
     * Отклонить жалобу.
     */
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
            
            if ($report->user) {
                $report->user->notify(new ReportModerated($report, 'rejected'));
            }

            $this->dispatch('$refresh');
            $this->dispatch('show-toast', type: 'info', message: 'Жалоба отклонена');
        }
    }

    /**
     * Удаление единичной жалобы (только из архива).
     */
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

    /**
     * Массовая очистка архива (решенные и отклоненные жалобы).
     */
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
            
            // Логируем массовые действия админов для безопасности
            Log::info('Массовое удаление жалоб', [
                'count' => $count,
                'moderator_id' => auth()->id(),
            ]);
        });
        
        // Сбрасываем страницу, т.к. записей могло стать меньше, чем текущая страница пагинации
        $this->resetPage(); 
        $this->dispatch('$refresh');
        $this->dispatch('show-toast', type: 'success', message: "Удалено {$count} жалоб");
    }

    /**
     * Бан/Разбан пользователя напрямую из жалобы.
     * При бане автоматически закрывает все pending жалобы на этого юзера.
     */
    public function toggleBan(int $userId): void
    {
        $this->checkAdminAccess();     

        $user = User::find($userId);
        // Нельзя забанить админа
        if ($user && !$user->is_admin) {
            DB::transaction(function () use ($user) {
                $newStatus = !$user->is_banned;
                $user->update(['is_banned' => $newStatus]);
                
                // Логика срабатывает только при НАЛОЖЕНИИ бана
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
                        
                        // Уведомляем авторов жалоб о том, что нарушитель Бан
                        if ($report->user) {
                            $report->user->notify(new ReportModerated($report, 'user_banned'));
                        }
                    }
                    
                    // Уведомляем самого Банного
                    $user->notify(new ReportModerated(
                        null, 
                        'user_banned',
                        "Вы были Баны на основании жалоб пользователей."
                    ));                   
                }
            });
            
            $this->dispatch('$refresh');
            $this->dispatch('show-toast', 
                type: 'success', 
                message: $user->is_banned ? "Пользователь {$user->name} Бан" : "Пользователь {$user->name} разбанен"
            );
        }
    }

    /**
     * Удаление фото напрямую из жалобы.
     * Автоматически закрывает все pending жалобы на это фото.
     */
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
                    
                    if ($report->user) {
                        $report->user->notify(new ReportModerated($report, 'photo_deleted'));
                    }
                }
                
                // Уведомляем владельца фото об удалении
                if ($photo->user) {
                    $photo->user->notify(new ReportModerated(
                        null, 
                        'photo_deleted',
                        "Ваше фото #{$photo->id} было удалено по жалобе пользователей."
                    ));
                }
                
                // Удаляем файл с диска, только если это локальный файл, а не внешняя ссылка (URL)
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
                                <div class="flex gap-2 items-center">
                                    <span class="text-sm font-medium">{{ $report->user?->name ?? 'Удален' }}</span>
                                    @if($report->user?->has_active_premium)
                                        <x-ui.badge variant="warning" size="xs" wire:key="premium-badge-complainer-{{ $report->id }}" class="p-1 flex items-center gap-1">
                                            <x-lucide-crown class="w-3 h-3" />
                                        </x-ui.badge>
                                    @endif      
                                    @if($report->user?->is_banned)
                                        <x-ui.badge variant="destructive" size="xs">Бан</x-ui.badge>
                                    @endif                                             
                                </div>                                    
                                <div class="text-xs text-muted-foreground">{{ $report->user?->email ?? '-' }}</div>
                            </div>
                        </div>
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        @if($report->type === 'user')
                            <div class="flex items-center gap-2">
                                <x-avatar src="{{ $report->reportedUser?->avatar_url }}" name="{{ $report->reportedUser?->name ?? 'Удален' }}" size="sm" userId="{{ $report->reported_user_id }}" showStatus="true" />
                                <div>
                                    <div class="flex gap-2 items-center">
                                        <span class="text-sm font-medium">{{ $report->reportedUser?->name ?? 'Удален' }}</span>
                                        @if($report->reportedUser?->has_active_premium)
                                            <x-ui.badge variant="warning" size="xs" wire:key="premium-badge-reported-{{ $report->id }}" class="p-1 flex items-center gap-1">
                                                <x-lucide-crown class="w-3 h-3" />
                                            </x-ui.badge>
                                        @endif      
                                        @if($report->reportedUser?->is_banned)
                                            <x-ui.badge variant="destructive" size="xs">Бан</x-ui.badge>
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
                                        <x-ui.dropdown-menu-item wire:key="toggleBan-{{ $report->reported_user_id }}" wire:click="toggleBan({{ $report->reported_user_id }})" wire:confirm="Изменить статус блокировки этого пользователя?">
                                            <x-lucide-lock class="w-4 h-4" />
                                            Снять/наложить бан
                                        </x-ui.dropdown-menu-item>
                                    @endif
                                    
                                    @if($report->type === 'photo' && $report->photo)
                                        <x-ui.dropdown-menu-separator />
                                        <x-ui.dropdown-menu-item wire:key="deletePhoto-{{ $report->photo_id }}" wire:click="deletePhoto({{ $report->photo_id }})" variant="destructive" wire:confirm="Удалить это фото навсегда?">
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
                        @if(!empty($search) || $statusFilter !== 'pending' || $typeFilter !== 'all')
                            <!-- ✅ ИСПРАВЛЕНО: Используем метод resetFilters -->
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