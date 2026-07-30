<?php

use App\Actions\Admin\ModeratePhotoAction;
use App\Actions\Admin\ToggleUserBanAction;
use App\Models\Photo;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportModerated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\Volt\Component;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    /** @var string Строка поиска по имени, email или причине жалобы */
    public string $search = '';
    
    /** @var string Текущий фильтр статуса (pending, resolved, rejected, all) */
    public string $statusFilter = 'pending';
    
    /** @var string Текущий фильтр типа жалобы (user, photo, all) */
    public string $typeFilter = 'all';
    
    /** @var int Количество элементов на странице */
    public int $perPage = 10;

    // Внедряем только глобальные переиспользуемые экшены
    private ToggleUserBanAction $toggleUserBanAction;
    private ModeratePhotoAction $moderatePhotoAction; 

    /**
     * Инициализация компонента и внедрение зависимостей.
     * 
     * @param ToggleUserBanAction $toggleUserBanAction
     * @param ModeratePhotoAction $moderatePhotoAction
     * @return void
     */
    public function boot(
        ToggleUserBanAction $toggleUserBanAction,
        ModeratePhotoAction $moderatePhotoAction
    ): void {
        $this->toggleUserBanAction = $toggleUserBanAction;
        $this->moderatePhotoAction = $moderatePhotoAction;
    }

    /**
     * Загрузка сохраненных фильтров из сессии при открытии страницы.
     * 
     * @return void
     */
    public function mount(): void
    {
        $saved = session('moderate_reports', []);
        if (isset($saved['statusFilter'])) $this->statusFilter = $saved['statusFilter'];
        if (isset($saved['typeFilter'])) $this->typeFilter = $saved['typeFilter'];
    }

    /**
     * Сброс пагинации при изменении строки поиска.
     * 
     * @return void
     */
    public function updatingSearch(): void 
    { 
        $this->resetPage(); 
    }
      
    /**
     * Сохранение фильтра типа в сессию и сброс пагинации.
     * 
     * @param string $value
     * @return void
     */
    public function updatedTypeFilter(string $value): void 
    { 
        session(['moderate_reports' => array_merge(session('moderate_reports', []), ['typeFilter' => $value])]);
        $this->resetPage(); 
    }

    /**
     * Установка фильтра статуса и его сохранение в сессию.
     * 
     * @param string $status
     * @return void
     */
    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        session(['moderate_reports' => array_merge(session('moderate_reports', []), ['statusFilter' => $status])]);
        $this->resetPage();
    }

    /**
     * Полный сброс всех фильтров к значениям по умолчанию.
     * 
     * @return void
     */
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

    /**
     * Отметить жалобу как решенную.
     * Обновляет статус и отправляет уведомление жалобщику.
     * 
     * @param int $reportId
     * @return void
     */
    public function resolve(int $reportId): void
    {
        $report = Report::find($reportId);
        if ($report && $report->status === 'pending') {
            
            $report->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'moderator_id' => auth()->id(),
            ]);

            if ($report->user) {
                $report->user->notify(new ReportModerated($report, 'resolved'));
            }

            $this->dispatch('show-toast', type: 'success', message: 'Жалоба отмечена как решенная');
        }
    }

    /**
     * Отклонить жалобу.
     * Обновляет статус и отправляет уведомление жалобщику.
     * 
     * @param int $reportId
     * @return void
     */
    public function reject(int $reportId): void
    {
        $report = Report::find($reportId);
        if ($report && $report->status === 'pending') {
            
            $report->update([
                'status' => 'rejected',
                'resolved_at' => now(),
                'moderator_id' => auth()->id(),
            ]);

            if ($report->user) {
                $report->user->notify(new ReportModerated($report, 'rejected'));
            }

            $this->dispatch('show-toast', type: 'info', message: 'Жалоба отклонена');
        }
    }

    /**
     * Удалить жалобу из базы (только если она не в статусе pending).
     * 
     * @param int $reportId
     * @return void
     */
    public function deleteReport(int $reportId): void
    {
        $report = Report::find($reportId);
        if (!$report) return;

        try {
            if ($report->status === 'pending') {
                throw new \Exception('Нельзя удалить необработанную жалобу.');
            }
            $report->delete();
            $this->dispatch('show-toast', type: 'success', message: 'Жалоба удалена');
        } catch (\Exception $e) {
            $this->dispatch('show-toast', type: 'error', message: $e->getMessage());
        }
    }

    /**
     * Массовая очистка архива (удаление всех решенных и отклоненных жалоб).
     * 
     * @return void
     */
    public function deleteResolvedReports(): void
    {
        $count = Report::whereIn('status', ['resolved', 'rejected'])->count();

        if ($count === 0) {
            $this->dispatch('show-toast', type: 'info', message: 'Нет жалоб для удаления');
            return;
        }

        DB::transaction(function () use ($count) {
            Report::whereIn('status', ['resolved', 'rejected'])->delete();
            Log::info('Массовое удаление жалоб', ['count' => $count, 'moderator_id' => auth()->id()]);
        });

        $this->resetPage(); 
        $this->dispatch('show-toast', type: 'success', message: "Удалено {$count} жалоб");
    }

    /**
     * Бан/Разбан пользователя из карточки жалобы.
     * При бане автоматически закрывает все активные жалобы на этого юзера.
     * 
     * @param int $userId
     * @return void
     */
    public function toggleBan(int $userId): void
    {
        $user = User::find($userId);
        if (!$user) return;

        // Вызываем глобальный экшен бана
        $result = $this->toggleUserBanAction->execute($user, 'Нарушение по жалобе пользователей');

        if (!$result['success']) {
            $this->dispatch('show-toast', type: 'error', message: 'Не удалось забанить (возможно, это админ).');
            return;
        }

        // Если пользователя забанили — закрываем его жалобы
        if ($result['is_banned']) {
            $reports = Report::where('reported_user_id', $user->id)
                ->where('status', 'pending')
                ->get();

            foreach ($reports as $report) {
                $report->update([
                    'status' => 'resolved',
                    'resolved_at' => now(),
                    'moderator_id' => auth()->id(),
                ]);

                if ($report->user) {
                    $report->user->notify(new ReportModerated($report, 'user_banned'));
                }
            }
        }
        
        $isUnbanned = !$result['is_banned'];
        $this->dispatch('show-toast', type: 'success', message: $isUnbanned ? "Пользователь {$user->name} разбанен" : "Пользователь {$user->name} забанен");
    }
        /**
     * Удаление фото из карточки жалобы.
     * Автоматически закрывает все жалобы на это фото и удаляет файлы.
     * 
     * @param int $photoId
     * @return void
     */
    public function deletePhoto(int $photoId): void
    {
        $photo = Photo::find($photoId);
        if (!$photo) return;

        DB::transaction(function () use ($photo) {
            // 1. Закрываем жалобы на это фото
            $reports = Report::where('photo_id', $photo->id)
                ->where('status', 'pending')
                ->get();

            foreach ($reports as $report) {
                $report->update([
                    'status' => 'resolved',
                    'resolved_at' => now(),
                    'moderator_id' => auth()->id(),
                ]);
            }

            // 2. Удаляем само фото (экшен сам отправит уведомление PhotoModerated владельцу)
            $this->moderatePhotoAction->destroy($photo);

            // 3. Уведомляем жалобщиков ТОЛЬКО после успешного коммита транзакции
            DB::afterCommit(function () use ($reports) {
                foreach ($reports as $report) {
                    if ($report->user) {
                        $report->user->notify(new ReportModerated(
                            report: null,
                            action: 'photo_deleted',
                            additionalInfo: "Ваша жалоба на фото #{$report->photo_id} решена. Фото удалено модератором."
                        ));
                    }
                }
            });
        });
        
        $this->dispatch('show-toast', type: 'success', message: 'Фото удалено. Все жалобы на него решены.');
    }

    // ============================================
    // ВЫВОД ДАННЫХ (Computed)
    // ============================================

    /**
     * Получение списка жалоб с фильтрацией, пагинацией и оптимизацией запросов.
     * 
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    #[Computed]
    public function reports()
    {
        // Кросс-БД совместимый поиск (ilike для PostgreSQL, like для остальных)
        $searchOperator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

        $reports = Report::query()
            ->with([
                'user.photos' => function ($q) {
                    $q->where('status', 'approved')
                      ->orderBy('is_primary', 'desc')
                      ->orderBy('position', 'asc')
                      ->limit(1);
                },
                'reportedUser.photos' => function ($q) {
                    $q->where('status', 'approved')
                      ->orderBy('is_primary', 'desc')
                      ->orderBy('position', 'asc')
                      ->limit(1);
                },
                'photo'
            ])
            ->whereHas('user', fn($q) => $q->where('is_admin', false))
            ->where(function ($q) {
                $q->whereNull('reported_user_id')
                  ->orWhereHas('reportedUser', fn($q2) => $q2->where('is_admin', false));
            })
            ->when($this->search, function ($query) use ($searchOperator) {
                $search = $this->search;
                $query->where(function ($q) use ($search, $searchOperator) {
                    $q->whereHas('user', function ($q2) use ($search, $searchOperator) {
                        $q2->where('name', $searchOperator, "%{$search}%")
                           ->orWhere('email', $searchOperator, "%{$search}%");
                    })
                    ->orWhereHas('reportedUser', function ($q2) use ($search, $searchOperator) {
                        $q2->where('name', $searchOperator, "%{$search}%")
                           ->orWhere('email', $searchOperator, "%{$search}%");
                    })
                    ->orWhere('reason', $searchOperator, "%{$search}%");
                });
            })
            ->when($this->statusFilter !== 'all', fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter !== 'all', fn($q) => $q->where('type', $this->typeFilter))
            ->latest()
            ->paginate($this->perPage);

        // Оптимизация онлайн-статуса (один запрос к таблице сессий)
        $onlineUserIds = \DB::table('sessions')
            ->where('last_activity', '>=', now()->subMinutes(5)->timestamp)
            ->pluck('user_id')
            ->filter()
            ->toArray();

        // Назначаем is_online без вызова save() в базу
        $reports->getCollection()->transform(function ($report) use ($onlineUserIds) {
            if ($report->user) {
                $report->user->setAttribute('is_online', in_array($report->user->id, $onlineUserIds));
            }
            
            if ($report->reportedUser) {
                $report->reportedUser->setAttribute('is_online', in_array($report->reportedUser->id, $onlineUserIds));
            }
            
            return $report;
        });

        return $reports;
    }

    /**
     * Подсчет количества жалоб для бейджей в фильтрах.
     * Учитывает текущий фильтр типов.
     * 
     * @return array
     */
    #[Computed]
    public function counts(): array
    {
        $baseQuery = Report::query()
            ->whereHas('user', fn($q) => $q->where('is_admin', false))
            ->where(function ($q) {
                $q->whereNull('reported_user_id')
                  ->orWhereHas('reportedUser', fn($q2) => $q2->where('is_admin', false));
            })
            ->when($this->typeFilter !== 'all', fn($q) => $q->where('type', $this->typeFilter));

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
                       <a href="{{ route('admin.users.show', $report->user?->id) }}" class="flex items-center gap-2 block group" wire:navigate>                                                 
                            <x-avatar src="{{ $report->user?->avatar_url }}" name="{{ $report->user?->name ?? 'Удален' }}" size="sm" userId="{{ $report->user_id }}" showStatus="true"  isOnline="{{ $report->user?->is_online ?? false }}"/>
                            <div>
                                <div class="flex gap-2 items-center group-hover:text-primary transition-colors">
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
                        </a>
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        @if($report->type === 'user')
                        <a href="{{ route('admin.users.show', $report->reportedUser?->id) }}" class="flex items-center gap-2 block group" wire:navigate>                           
                                <x-avatar src="{{ $report->reportedUser?->avatar_url }}" name="{{ $report->reportedUser?->name ?? 'Удален' }}" size="sm" userId="{{ $report->reported_user_id }}" showStatus="true" />
                                <div>
                                    <div class="flex gap-2 items-center group-hover:text-primary transition-colors">
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
                            </a>
                        @else
                            <div class="flex items-center gap-2">
                                @if($report->photo)
                                    <img src="{{ $report->photo->thumb_url ?: $report->photo->url }}" class="w-10 h-10 object-cover rounded" alt="photo">
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
                                        <x-ui.dropdown-menu-item variant="destructive" wire:key="toggleBan-{{ $report->reported_user_id }}" wire:click="toggleBan({{ $report->reported_user_id }})" wire:confirm="Забанить этого пользователя?">
                                            <x-lucide-lock class="w-4 h-4" />
                                            Забанить
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