<?php

use App\Models\AdminLog;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    public string $search = '';
    public string $categoryFilter = 'all';
    public string $actionFilter = 'all';
    public string $adminFilter = '';
    public string $dateFilter = '';
    public int $perPage = 15;

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        // Восстанавливаем сохраненные фильтры из сессии
        $this->categoryFilter = session('admin_audit_category', 'all');
        $this->actionFilter = session('admin_audit_action', 'all');
        $this->adminFilter = session('admin_audit_admin', '');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDateFilter(): void
    {
        $this->resetPage();
    }

    public function updatedAdminFilter(): void
    {
        session(['admin_audit_admin' => $this->adminFilter]);
        $this->resetPage();
    }

    public function setCategoryFilter(string $category): void
    {
        $this->categoryFilter = $category;
        session(['admin_audit_category' => $category]);
        
        // При смене категории сбрасываем конкретное действие
        $this->actionFilter = 'all';
        session()->forget('admin_audit_action');
        
        $this->resetPage();
    }

    public function setActionFilter(string $action): void
    {
        $this->actionFilter = $action;
        session(['admin_audit_action' => $action]);
        $this->resetPage();
    }

    // Сбрасываем ТОЛЬКО поиск и дату
    public function clearSearchFilters(): void
    {
        $this->reset(['search', 'dateFilter']);
        $this->resetPage();
    }

    // ============================================
    // ХЕЛПЕРЫ ДЛЯ ПРЕДСТАВЛЕНИЯ (UI)
    // ============================================

    public function getObjectUrl(AdminLog $log): ?string
    {
        if (!$log->loggable_id || !$log->loggable_type) return null;

        $modelClass = class_basename($log->loggable_type);
        $routeMap = [
            'User' => 'admin.users.show',
            'Photo' => 'admin.photos.show',
            'Transaction' => 'admin.transactions.show',
            'Page' => 'admin.pages.show',
        ];

        $routeName = $routeMap[$modelClass] ?? null;

        if ($routeName && Route::has($routeName)) {
            return route($routeName, $log->loggable_id);
        }

        return null;
    }

    // ============================================
    // ВЫВОД ДАННЫХ (Computed)
    // ============================================

    #[Computed]
    public function logs(): LengthAwarePaginator
    {
        $avatarQuery = fn($q) => $q->select(['user_id', 'is_primary', 'path_thumb', 'path_medium'])
                                  ->orderByDesc('is_primary')
                                  ->limit(1);

        $query = AdminLog::with([
            'admin' => fn($q) => $q->with(['photos' => $avatarQuery]),
            'loggable'
        ])->latest();

        if ($this->categoryFilter !== 'all') {
            $query->where('action', 'like', $this->categoryFilter . '.%');
        }

        if ($this->actionFilter !== 'all') {
            $query->where('action', $this->actionFilter);
        }

        if (!empty($this->adminFilter)) {
            $query->where('admin_id', (int) $this->adminFilter);
        }

        if ($this->dateFilter) {
            $query->whereDate('created_at', $this->dateFilter);
        }

        if (!empty($this->search)) {
            $search = strtolower($this->search);
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhere('loggable_type', 'like', "%{$search}%");
                  
                if (is_numeric($search)) {
                    $q->orWhere('loggable_id', $search);
                }
            });
        }

        $logs = $query->paginate($this->perPage)->withQueryString();

        $logs->loadMorph('loggable', [
            \App\Models\User::class => ['photos' => $avatarQuery], 
            \App\Models\Photo::class => [
                'album', 
                'user' => fn($q) => $q->with(['photos' => $avatarQuery])
            ], 
            \App\Models\PhotoComment::class => [
                'user' => fn($q) => $q->with(['photos' => $avatarQuery])
            ],
            \App\Models\Report::class => [
                'reporter' => fn($q) => $q->with(['photos' => $avatarQuery]),
                'reported' => fn($q) => $q->with(['photos' => $avatarQuery]),
            ],
            \App\Models\Swipe::class => [
                'user' => fn($q) => $q->with(['photos' => $avatarQuery]),
                'targetUser' => fn($q) => $q->with(['photos' => $avatarQuery]),
            ],
            \App\Models\UserMatch::class => [
                'user1' => fn($q) => $q->with(['photos' => $avatarQuery]),
                'user2' => fn($q) => $q->with(['photos' => $avatarQuery]),
            ],
            \App\Models\Page::class => [],
        ]);

        return $logs;
    }

    // Кэшируем статистику категорий на 1 минуту
    #[Computed]
    public function categoryStats(): array
    {
        return Cache::remember('admin_audit_category_stats', 60, function () {
            $driver = config('database.default');
            $expression = $driver === 'pgsql' 
                ? "SPLIT_PART(action, '.', 1)" 
                : "SUBSTRING_INDEX(action, '.', 1)";

            $stats = AdminLog::selectRaw("{$expression} as category, COUNT(*) as count")
                ->groupBy('category')
                ->pluck('count', 'category')
                ->toArray();

            $stats['all'] = array_sum($stats);
            
            return $stats;
        });
    }

    #[Computed]
    public function actions(): \Illuminate\Support\Collection
    {
        $query = AdminLog::selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderBy('action');

        if ($this->categoryFilter !== 'all') {
            $query->where('action', 'like', $this->categoryFilter . '.%');
        }

        return $query->pluck('count', 'action');
    }

    // Кэшируем список админов на 1 минуту
       #[Computed]
    public function admins(): array
    {
        // Кэшируем простой массив [id => name], чтобы избежать проблем с десериализацией объектов
        return Cache::remember('admin_audit_admins_list', 60, function () {
            return User::whereHas('adminLogs')
                ->select('id', 'name')
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();
        });
    }

    // Кэшируем общее количество на 1 минуту
    #[Computed]
    public function totalEntries(): int
    {
        return Cache::remember('admin_audit_total_entries', 60, function () {
            return AdminLog::count();
        });
    }
}; 
?>

<div class="space-y-6 pb-6">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h1 class="text-2xl font-semibold flex items-center gap-2">
                <x-lucide-shield-check class="w-6 h-6" />
                Журнал действий 
            </h1>
            <p class="text-sm text-muted-foreground">
                Всего записей: {{ $this->totalEntries }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <x-ui.button wire:click="$refresh" variant="outline" size="sm">
                <span wire:loading.remove.delay wire:target="$refresh">
                    <x-lucide-refresh-ccw class="w-4 h-4" />
                </span>
                <span wire:loading wire:target="$refresh">
                    <x-lucide-loader-2 class="w-4 h-4 animate-spin" />
                </span>
            </x-ui.button>
        </div>
    </div>

    <!-- Filters Panel -->
    <div class="bg-card border border-border rounded-lg p-4 space-y-4">
        
        <!-- УРОВЕНЬ 1: Категории (Модули) -->
        <div class="flex flex-wrap items-center gap-2 border-b border-border pb-4">
            <x-ui.button wire:click="setCategoryFilter('all')" variant="{{ $categoryFilter === 'all' ? 'default' : 'outline' }}" size="sm" class="flex items-center gap-1.5">
                <x-lucide-layers class="w-4 h-4" /> Все модули <x-ui.badge size="xs">{{ $this->categoryStats['all'] ?? 0 }}</x-ui.badge>
            </x-ui.button>
            
            <x-ui.button wire:click="setCategoryFilter('user')" variant="{{ $categoryFilter === 'user' ? 'default' : 'outline' }}" size="sm" class="flex items-center gap-1.5">
                <x-lucide-users class="w-4 h-4" /> Пользователи <x-ui.badge variant="outline" size="xs">{{ $this->categoryStats['user'] ?? 0 }}</x-ui.badge>
            </x-ui.button>

             <x-ui.button wire:click="setCategoryFilter('page')" variant="{{ $categoryFilter === 'page' ? 'default' : 'outline' }}" size="sm" class="flex items-center gap-1.5">
                <x-lucide-file-text class="w-4 h-4" /> Страницы <x-ui.badge variant="outline" size="xs">{{ $this->categoryStats['page'] ?? 0 }}</x-ui.badge>
            </x-ui.button>

            <x-ui.button wire:click="setCategoryFilter('photo')" variant="{{ $categoryFilter === 'photo' ? 'default' : 'outline' }}" size="sm" class="flex items-center gap-1.5">
                <x-lucide-image class="w-4 h-4" /> Фотографии <x-ui.badge variant="outline" size="xs">{{ $this->categoryStats['photo'] ?? 0 }}</x-ui.badge>
            </x-ui.button>

            <x-ui.button wire:click="setCategoryFilter('comment')" variant="{{ $categoryFilter === 'comment' ? 'default' : 'outline' }}" size="sm" class="flex items-center gap-1.5">
                <x-lucide-message-square class="w-4 h-4" /> Комментарии <x-ui.badge variant="outline" size="xs">{{ $this->categoryStats['comment'] ?? 0 }}</x-ui.badge>
            </x-ui.button>

            <x-ui.button wire:click="setCategoryFilter('dating')" variant="{{ $categoryFilter === 'dating' ? 'default' : 'outline' }}" size="sm" class="flex items-center gap-1.5">
                <x-lucide-heart class="w-4 h-4" /> Анкеты <x-ui.badge variant="outline" size="xs">{{ $this->categoryStats['dating'] ?? 0 }}</x-ui.badge>
            </x-ui.button>

            <x-ui.button wire:click="setCategoryFilter('report')" variant="{{ $categoryFilter === 'report' ? 'default' : 'outline' }}" size="sm" class="flex items-center gap-1.5">
                <x-lucide-flag class="w-4 h-4" /> Жалобы <x-ui.badge variant="outline" size="xs">{{ $this->categoryStats['report'] ?? 0 }}</x-ui.badge>
            </x-ui.button>
           
            <x-ui.button wire:click="setCategoryFilter('transaction')" variant="{{ $categoryFilter === 'transaction' ? 'default' : 'outline' }}" size="sm" class="flex items-center gap-1.5">
                <x-lucide-wallet class="w-4 h-4" /> Финансы <x-ui.badge variant="outline" size="xs">{{ $this->categoryStats['transaction'] ?? 0 }}</x-ui.badge>
            </x-ui.button>

            <x-ui.button wire:click="setCategoryFilter('setting')" variant="{{ $categoryFilter === 'setting' ? 'default' : 'outline' }}" size="sm" class="flex items-center gap-1.5">
                <x-lucide-settings class="w-4 h-4" /> Система <x-ui.badge variant="outline" size="xs">{{ $this->categoryStats['setting'] ?? 0 }}</x-ui.badge>
            </x-ui.button>
        </div>

        <!-- УРОВЕНЬ 2: Действия -->
        @if($this->actions->isNotEmpty())
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.button wire:click="setActionFilter('all')" variant="{{ $actionFilter === 'all' ? 'default' : 'secondary' }}" size="sm" class="flex items-center gap-1.5">
                    Все действия
                </x-ui.button>
                
                @foreach($this->actions as $action => $count)
                    @php
                        $actionColor = match(true) {
                            str_contains($action, 'ban') || str_contains($action, 'delete') || str_contains($action, 'reject') => 'text-red-500',
                            str_contains($action, 'approve') || str_contains($action, 'unban') || str_contains($action, 'activate') => 'text-green-500',
                            str_contains($action, 'update') || str_contains($action, 'edit') => 'text-blue-500',
                            str_contains($action, 'refund') || str_contains($action, 'warning') => 'text-yellow-500',
                            default => 'text-muted-foreground',
                        };
                    @endphp
                    <x-ui.button wire:click="setActionFilter('{{ $action }}')" variant="{{ $actionFilter === $action ? 'default' : 'secondary' }}" size="sm" class="flex items-center gap-1.5">
                        <span class="{{ $actionColor }}"><x-lucide-git-branch class="w-3.5 h-3.5" /></span>
                        {{ $action }} <x-ui.badge variant="outline" size="xs">{{ $count }}</x-ui.badge>
                    </x-ui.button>
                @endforeach
            </div>
        @endif

        <!-- УРОВЕНЬ 3: Точечные фильтры и поиск -->
        <div class="flex flex-wrap items-center gap-2 pt-4 border-t border-border">
            <x-ui.select wire:model.live="adminFilter">
                <x-ui.select-trigger class="w-[160px] h-9">
                    <x-ui.select-value placeholder="Все админы" />
                </x-ui.select-trigger>
                <x-ui.select-content>
                    <x-ui.select-item value="">Все админы</x-ui.select-item>
                    @foreach($this->admins as $adminId => $adminName)
                        <x-ui.select-item wire:key="admin-opt-{{ $adminId }}" value="{{ $adminId }}">
                            {{ $adminName }}
                        </x-ui.select-item>
                    @endforeach
                </x-ui.select-content>
            </x-ui.select>

            <x-ui.date-picker wire:model.live="dateFilter" placeholder="Дата" width="w-[10rem]" wire:key="date-filter-audit" />

            <div class="flex items-center gap-2 w-full sm:w-auto sm:ml-auto">
                <div class="relative w-full sm:w-64">
                    <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск (IP, action, ID)..." class="pl-9 pr-8" />
                    <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                    @if(!empty($search))
                        <button wire:click="$set('search', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
                            <x-lucide-x class="w-4 h-4" />
                        </button>
                    @endif
                </div>
                
                <!-- Кнопка сброса ТОЛЬКО для поиска и даты -->
                @if(!empty($search) || !empty($dateFilter))
                    <x-ui.button wire:click="clearSearchFilters" variant="destructive" size="sm" class="shrink-0">
                        <x-lucide-filter-x class="w-4 h-4" /> Сбросить
                    </x-ui.button>
                @endif
            </div>
        </div>
    </div>

    <!-- Table -->
    <x-ui.table>
        <x-ui.table-header>
            <x-ui.table-row>
                <x-ui.table-head class="w-32">Время и дата</x-ui.table-head>
                <x-ui.table-head class="w-56">Админ</x-ui.table-head>
                <x-ui.table-head class="w-48">Действие</x-ui.table-head>
                <x-ui.table-head class="w-64">Объект</x-ui.table-head>
                <x-ui.table-head>Изменения (Before / After)</x-ui.table-head>
                <x-ui.table-head class="w-32">IP</x-ui.table-head>
            </x-ui.table-row>
        </x-ui.table-header>

        <x-ui.table-body>
            @forelse ($this->logs as $log)
                <x-ui.table-row wire:key="log-{{ $log->id }}">
                    <!-- Время -->
                    <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap align-top">
                        <div class="flex flex-col pt-1">
                            <span class="font-medium text-foreground/80">{{ $log->created_at->format('d.m.Y') }}</span>
                            <span class="text-muted-foreground/80">{{ $log->created_at->format('H:i:s') }}</span>
                        </div>
                    </x-ui.table-cell>

                    <!-- Админ -->
                    <x-ui.table-cell>
                        @if($log->admin)
                            <a href="{{ route('admin.users.show', $log->admin_id) }}" wire:navigate class="flex items-center gap-2 group">
                                <x-avatar src="{{ $log->admin->avatar_url }}" name="{{ $log->admin->name }}" size="sm" userId="{{ $log->admin->id }}" showStatus="true" :isOnline="$log->admin->is_online" />
                                <div class="flex flex-col">
                                    <span>
                                       <x-user-status-sign :user="$log->admin" />
                                       <span class="text-sm font-medium group-hover:text-primary transition-colors">{{ $log->admin->name }}</span>
                                    </span>
                                    <span class="text-xs text-muted-foreground">{{ $log->admin->email }}</span>
                                </div>
                            </a>
                        @else
                            <x-ui.badge variant="secondary" size="sm">Система</x-ui.badge>
                        @endif
                    </x-ui.table-cell>

                    <!-- Действие -->
                    <x-ui.table-cell>
                        @php
                            $badgeColor = match(true) {
                                str_contains($log->action, 'ban') || str_contains($log->action, 'delete') => 'bg-red-500/10 text-red-500',
                                str_contains($log->action, 'approve') || str_contains($log->action, 'unban') => 'bg-green-500/10 text-green-500',
                                str_contains($log->action, 'update') => 'bg-blue-500/10 text-blue-500',
                                default => 'bg-muted text-muted-foreground',
                            };
                        @endphp
                        <span class="px-2 py-0.5 rounded text-xs font-medium {{ $badgeColor }}">{{ $log->action }}</span>
                    </x-ui.table-cell>

                    <!-- Объект (Умный вывод) -->
                    <x-ui.table-cell class="text-sm align-top">
                        @if($log->loggable)
                            @if($log->loggable_type === \App\Models\User::class)
                                <!-- Вывод пользователя -->
                                <a href="{{ route('admin.users.show', $log->loggable->id) }}" wire:navigate class="flex items-center gap-2 group">                                    
                                    <x-avatar src="{{ $log->loggable->avatar_url }}" name="{{ $log->loggable->name }}" size="md" userId="{{ $log->loggable->id }}" showStatus="true" :isOnline="$log->loggable->is_online" />
                                    <div class="flex flex-col">
                                        <span>
                                            <x-user-status-sign :user="$log->loggable" />
                                            <span class="text-sm font-medium group-hover:text-primary transition-colors">{{ $log->loggable->name }}</span>
                                        </span>
                                        <span class="text-xs text-muted-foreground">{{ $log->loggable->email }}</span>
                                        <span class="text-xs text-muted-foreground/70">Юзер ID: {{ $log->loggable->id }}</span>
                                    </div>
                                </a>
                            
                            @elseif($log->loggable_type === \App\Models\Broadcast::class)
                                @php 
                                    $b = $log->loggable;
                                    
                                    $types = [
                                        'in_app' => ['Site', 'bg-secondary text-secondary-foreground'],
                                        'email'  => ['Email', 'bg-yellow-500/10 text-yellow-500'],
                                        'push'   => ['Push', 'bg-blue-500/10 text-blue-500'],
                                    ];
                                    $statuses = [
                                        'draft'     => ['Черновик', 'bg-yellow-500/10 text-yellow-500'],
                                        'scheduled' => ['Запланировано', 'bg-blue-500/10 text-blue-500'],
                                        'sending'   => ['Отправка...', 'bg-blue-500/10 text-blue-500'],
                                        'sent'      => ['Отправлено', 'bg-green-500/10 text-green-500'],
                                        'failed'    => ['Ошибка', 'bg-red-500/10 text-red-500'],
                                    ];

                                    [$tLabel, $tColor] = $types[$b->type ?? ''] ?? ['—', 'bg-muted text-muted-foreground'];
                                    [$sLabel, $sColor] = $statuses[$b->status ?? ''] ?? ['—', 'bg-muted text-muted-foreground'];
                                @endphp
                                
                                <div class="flex flex-col gap-1 pt-1 max-w-[220px]">
                                    @if($b)
                                        <a href="{{ route('admin.system.broadcasts.edit', $log->loggable_id) }}" wire:navigate class="text-sm font-medium hover:text-primary transition-colors truncate">
                                            {{ $b->title ?? 'Без названия' }}
                                        </a>
                                    @else
                                        <span class="text-sm font-medium truncate text-muted-foreground">Рассылка удалена</span>
                                    @endif
                                    
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-muted-foreground text-xs">ID: {{ $log->loggable_id }}</span>
                                        <span class="px-2 py-0.5 rounded text-xs font-medium {{ $tColor }}">{{ $tLabel }}</span>
                                        <span class="px-2 py-0.5 rounded text-xs font-medium {{ $sColor }}">{{ $sLabel }}</span>
                                    </div>
                                </div>

                            @elseif($log->loggable_type === \App\Models\Photo::class)
                                <!-- Вывод фотографии (превью + юзер + альбом + ID) -->
                                <div class="flex items-center gap-2.5 pt-1">
                                    <a href="{{ $log->loggable->original_url ?? $log->loggable->path_medium }}" data-fancybox="gallery-{{ $log->loggable->user_id ?? 'log' }}" class="block w-10 h-10 overflow-hidden border border-border shrink-0">
                                        <img src="{{ $log->loggable->path_thumb ?? $log->loggable->path_medium }}" alt="Photo" class="w-full h-full object-cover">
                                    </a>
                                    <div class="flex flex-col text-xs min-w-0">
                                        @if($log->loggable->user)                                            
                                            <a href="{{ route('admin.users.show', $log->loggable->user_id) }}" wire:navigate class="font-medium text-foreground hover:text-primary transition-colors truncate">
                                                <x-user-status-sign :user="$log->loggable->user" />
                                                {{ $log->loggable->user->name }}
                                            </a>
                                        @endif
                                        @if($log->loggable->album)
                                            <span class="text-muted-foreground truncate">Альбом: {{ $log->loggable->album->title ?? $log->loggable->album->name }}</span>
                                        @endif
                                        <span class="text-muted-foreground/70">Фото ID:  {{ $log->loggable_id }}</span>
                                    </div>
                                </div>
                                
                            @elseif($log->loggable_type === \App\Models\PhotoComment::class)
                                @php 
                                    $c = $log->loggable;
                                    $isMassAction = str_starts_with($log->action, 'comment.mass_');
                                    
                                    if ($isMassAction) {
                                        $massCount = $log->after['count'] ?? 0;
                                        $massIds = $log->after['ids'] ?? [];
                                        $massLabel = match($log->action) {
                                            'comment.mass_approve' => 'Массовое одобрение',
                                            'comment.mass_reject' => 'Массовое отклонение',
                                            default => 'Массовое действие'
                                        };
                                        $massColor = $log->action === 'comment.mass_approve' 
                                            ? 'bg-green-500/10 text-green-500' 
                                            : 'bg-red-500/10 text-red-500';
                                    } else {
                                        $cStatuses = [
                                            'pending'  => ['Ожидает', 'bg-yellow-500/10 text-yellow-500'],
                                            'approved' => ['Одобрен', 'bg-green-500/10 text-green-500'],
                                            'rejected' => ['Отклонен', 'bg-red-500/10 text-red-500'],
                                            'spam'     => ['Спам', 'bg-red-500/10 text-red-500'],
                                        ];
                                        [$cLabel, $cColor] = $cStatuses[$c->status ?? ''] ?? ['—', 'bg-muted text-muted-foreground'];
                                    }
                                @endphp

                                @if ($isMassAction)
                                    <!-- Вывод МАССОВОГО действия -->
                                    <div class="flex flex-col gap-1 pt-1 max-w-[240px]">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-full bg-muted flex items-center justify-center shrink-0">
                                                <svg class="w-4 h-4 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z" />
                                                </svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <span class="text-sm font-medium text-foreground">Комментарии</span>
                                                <div class="flex items-center gap-1.5 flex-wrap mt-0.5">
                                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-medium {{ $massColor }}">{{ $massLabel }}</span>
                                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-secondary text-secondary-foreground">{{ $massCount }} шт.</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-[10px] text-muted-foreground/70 mt-1 truncate">
                                            @if(!empty($massIds))
                                                <span class="font-medium">ID:</span> {{ \Illuminate\Support\Str::limit(implode(', ', $massIds), 35) }}
                                            @endif
                                        </div>
                                    </div>
                                @else
                                    <!-- Вывод одиночного комментария (Автор + Статус + Текст + ID) -->
                                    <div class="flex flex-col gap-1 pt-1 max-w-[220px]">
                                        <div class="flex items-center gap-2">
                                            <x-avatar src="{{ $c->user?->avatar_url }}" name="{{ $c->user?->name ?? 'Удален' }}" size="sm" userId="{{ $c->user?->id }}" showStatus="true" :isOnline="$c->user?->is_online" />
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    @if($c->user)
                                                        <a href="{{ route('admin.users.show', $c->user->id) }}" wire:navigate class="font-medium text-xs hover:text-primary transition-colors flex items-center gap-1">
                                                            <x-user-status-sign :user="$c->user" />
                                                            {{ $c->user->name }}
                                                        </a>
                                                    @else
                                                        <span class="font-medium text-xs text-muted-foreground">Удален</span>
                                                    @endif
                                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-medium {{ $cColor }}">{{ $cLabel }}</span>
                                                </div>
                                                <p class="text-xs text-muted-foreground italic truncate mt-0.5">"{{ \Illuminate\Support\Str::limit($c->content, 40) }}"</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2 text-[10px] text-muted-foreground/70 mt-1">
                                            <span>Коммент ID: {{ $log->loggable_id }}</span>
                                            @if($c->photo_id)
                                                <span>•</span>
                                                <span>К фото #{{ $c->photo_id }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @endif

                            @elseif($log->loggable_type === \App\Models\Report::class)
                                @php 
                                    $report = $log->loggable; // Может быть null, если жалоба была жестко удалена
                                    $isMassAction = str_starts_with($log->action, 'report.mass_');
                                                                    
                                    // Получаем значение reason из модели или из диффа лога (если жалоба удалена)
                                    $reasonValue = $report?->reason ?? $log->before['reason'] ?? null;
                                    $reasonEnum = \App\Enums\ReportReason::tryFrom($reasonValue ?? '');
                                @endphp

                                @if ($isMassAction)
                                    <!-- Вывод МАССОВОГО действия с жалобами -->
                                    <div class="flex items-center gap-2 max-w-[250px]">
                                        <div class="w-8 h-8 rounded-full bg-muted flex items-center justify-center shrink-0">
                                            <x-lucide-layers class="w-4 h-4 text-muted-foreground" />
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <span class="text-sm font-medium text-foreground block truncate">
                                                {{ $log->after['label'] ?? 'Массовое действие с жалобами' }}
                                            </span>
                                            <div class="flex items-center gap-1.5 flex-wrap mt-0.5">
                                                @if(!empty($log->after['count']))
                                                    <x-ui.badge variant="secondary" size="xs">{{ $log->after['count'] }} шт.</x-ui.badge>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <!-- Вывод одиночной жалобы -->
                                    <div class="flex items-center gap-2 max-w-[250px]">
                                        <div class="w-8 h-8 rounded-full bg-muted flex items-center justify-center shrink-0">
                                            <x-lucide-flag class="w-4 h-4 text-orange-500" />
                                        </div>
                                        <div class="flex-1 min-w-0">                                            
                                            <span class="text-sm font-medium text-foreground">Жалоба ID: {{ $log->loggable_id }}</span>                                                                                        
                                            
                                            @if($reasonEnum)
                                                <div class="flex items-center gap-1.5 flex-wrap mt-1">
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $reasonEnum->color() }}">
                                                        {{ $reasonEnum->label() }}
                                                    </span>
                                                </div>
                                            @elseif($reasonValue)
                                                <!-- Фоллбэк, если причина не в Enum (например, кастный текст) -->
                                                <p class="text-xs text-muted-foreground truncate mt-1">
                                                    "{{ \Illuminate\Support\Str::limit(ucfirst($reasonValue), 35) }}"
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                @endif              

                            @elseif($log->loggable_type === \App\Models\Page::class)
                                <!-- Вывод страницы (ID + Тайтл + Бейдж статуса) -->
                                @php 
                                    // Проверяем, существует ли сама страница (не удалена ли она)
                                    $pageExists = $log->loggable !== null;
                                    
                                    // Формируем ссылку на редактирование напрямую по ID из лога
                                    $objUrl = $pageExists 
                                        ? route('admin.system.pages.edit', $log->loggable_id) 
                                        : null;
                                    
                                    $isActive = $log->loggable->is_active ?? false;
                                    
                                    $statusColor = $isActive 
                                        ? 'bg-green-500/10 text-green-500' 
                                        : 'bg-yellow-500/10 text-yellow-500';
                                        
                                    $statusLabel = $isActive ? 'Опубликована' : 'Черновик';
                                @endphp
                                
                                <div class="flex flex-col gap-1 pt-1 max-w-[200px]">
                                    @if($objUrl)
                                        <a href="{{ $objUrl }}" wire:navigate class="text-sm font-medium hover:text-primary transition-colors truncate">
                                            {{ $log->loggable->title ?? 'Без названия' }}
                                        </a>
                                    @else
                                        <span class="text-sm font-medium truncate text-muted-foreground">
                                            {{ $log->loggable->title ?? 'Страница удалена' }}
                                        </span>
                                    @endif
                                    
                                    <div class="flex items-center gap-2">
                                        <span class="text-muted-foreground text-xs">ID: {{ $log->loggable_id }}</span>
                                        <span class="px-2 py-0.5 rounded text-xs font-medium {{ $statusColor }}">
                                            {{ $statusLabel }}
                                        </span>
                                    </div>
                                </div>

                           @elseif($log->loggable_type === \App\Models\Swipe::class)
                                @php 
                                    $swipe = $log->loggable; 
                                    // Берем тип из диффа, так как свайп могли изменить (например, при Rewind)
                                    $swipeTypeValue = $log->after['type'] ?? $log->before['type'] ?? $swipe->type ?? '';
                                    $swipeType = \App\Enums\SwipeType::tryFrom($swipeTypeValue);
                                @endphp
                                <!-- Вывод Свайпа: Юзер 1 -> Иконка действия -> Юзер 2 -->
                                <div class="flex items-center gap-3 pt-1 max-w-[300px]">
                                    <!-- Кто свайпнул -->
                                    <div class="flex flex-col items-center gap-1 min-w-[40px]">
                                        @if($swipe && $swipe->user)
                                            <a href="{{ route('admin.users.show', $swipe->user->id) }}" wire:navigate class="hover:opacity-80 transition-opacity">
                                                <x-avatar src="{{ $swipe->user->avatar_url }}" name="{{ $swipe->user->name }}" size="xs" userId="{{ $swipe->user->id }}" showStatus="true" :isOnline="$swipe->user->is_online" />
                                            </a>
                                            <a href="{{ route('admin.users.show', $swipe->user->id) }}" wire:navigate class="text-[10px] truncate w-14 text-center hover:text-primary flex items-center gap-0.5 justify-center">
                                                <x-user-status-sign :user="$swipe->user" />
                                                <span class="truncate">{{ \Illuminate\Support\Str::limit($swipe->user->name, 8) }}</span>
                                            </a>
                                        @else
                                            <x-avatar name="Del" size="xs" />
                                            <span class="text-[10px] text-muted-foreground">Удален</span>
                                        @endif
                                    </div>

                                    <!-- Тип свайпа (Иконка + Бейдж) -->
                                    <div class="shrink-0 flex flex-col items-center gap-1">
                                        @if($swipeType === \App\Enums\SwipeType::Like)
                                            <x-lucide-heart class="w-5 h-5 text-green-500 fill-current" />
                                        @elseif($swipeType === \App\Enums\SwipeType::Superlike)
                                            <x-lucide-star class="w-5 h-5 text-yellow-500 fill-current" />
                                        @elseif($swipeType === \App\Enums\SwipeType::Dislike)
                                            <x-lucide-thumbs-down class="w-5 h-5 text-red-500" />
                                        @endif
                                        @if($swipeType)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-medium {{ $swipeType->color() }}">{{ $swipeType->label() }}</span>
                                        @endif
                                    </div>

                                    <!-- Кого свайпнули -->
                                    <div class="flex flex-col items-center gap-1 min-w-[40px]">
                                        @if($swipe && $swipe->targetUser)
                                            <a href="{{ route('admin.users.show', $swipe->targetUser->id) }}" wire:navigate class="hover:opacity-80 transition-opacity">
                                                <x-avatar src="{{ $swipe->targetUser->avatar_url }}" name="{{ $swipe->targetUser->name }}" size="xs" userId="{{ $swipe->targetUser->id }}" showStatus="true" :isOnline="$swipe->targetUser->is_online" />
                                            </a>
                                            <a href="{{ route('admin.users.show', $swipe->targetUser->id) }}" wire:navigate class="text-[10px] truncate w-14 text-center hover:text-primary flex items-center gap-0.5 justify-center">
                                                <x-user-status-sign :user="$swipe->targetUser" />
                                                <span class="truncate">{{ \Illuminate\Support\Str::limit($swipe->targetUser->name, 8) }}</span>
                                            </a>
                                        @else
                                            <x-avatar name="Del" size="xs" />
                                            <span class="text-[10px] text-muted-foreground">Удален</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="text-[10px] text-muted-foreground/70 mt-2">
                                    Свайп ID: {{ $log->loggable_id }}
                                </div>

                       @elseif($log->loggable_type === \App\Models\UserMatch::class)
                            @php 
                                $match = $log->loggable; 
                                // Берем статус ИЗ ДИФФА (после действия), а не текущий статус модели
                                $matchStatusValue = $log->after['status'] ?? $log->before['status'] ?? $match->status ?? '';
                                $matchStatus = \App\Enums\MatchStatus::tryFrom($matchStatusValue);
                            @endphp
                            <!-- Вывод Мэтча: Юзер 1 ❤️ Юзер 2 -->
                            <div class="flex items-center gap-3 pt-1 max-w-[300px]">
                                <!-- Пользователь 1 -->
                                <div class="flex flex-col items-center gap-1 min-w-[40px]">
                                    @if($match && $match->user1)
                                        <a href="{{ route('admin.users.show', $match->user1->id) }}" wire:navigate class="hover:opacity-80 transition-opacity">
                                            <x-avatar src="{{ $match->user1->avatar_url }}" name="{{ $match->user1->name }}" size="xs" userId="{{ $match->user1->id }}" showStatus="true" :isOnline="$match->user1->is_online" />
                                        </a>
                                        <a href="{{ route('admin.users.show', $match->user1->id) }}" wire:navigate class="text-[10px] truncate w-14 text-center hover:text-primary flex items-center gap-0.5 justify-center">
                                            <x-user-status-sign :user="$match->user1" />
                                            <span class="truncate">{{ \Illuminate\Support\Str::limit($match->user1->name, 8) }}</span>
                                        </a>
                                    @else
                                        <x-avatar name="Del" size="xs" />
                                        <span class="text-[10px] text-muted-foreground">Удален</span>
                                    @endif
                                </div>

                                <!-- Иконка и статус мэтча -->
                                <div class="shrink-0 flex flex-col items-center gap-1">
                                    @if($matchStatus === \App\Enums\MatchStatus::Active)
                                        <x-lucide-heart class="w-5 h-5 text-pink-500 fill-current" />
                                    @else
                                        <x-lucide-heart-crack class="w-5 h-5 text-muted-foreground" />
                                    @endif
                                    @if($matchStatus)
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-medium {{ $matchStatus->color() }}">{{ $matchStatus->label() }}</span>
                                    @endif
                                </div>

                                <!-- Пользователь 2 -->
                                <div class="flex flex-col items-center gap-1 min-w-[40px]">
                                    @if($match && $match->user2)
                                        <a href="{{ route('admin.users.show', $match->user2->id) }}" wire:navigate class="hover:opacity-80 transition-opacity">
                                            <x-avatar src="{{ $match->user2->avatar_url }}" name="{{ $match->user2->name }}" size="xs" userId="{{ $match->user2->id }}" showStatus="true" :isOnline="$match->user2->is_online" />
                                        </a>
                                        <a href="{{ route('admin.users.show', $match->user2->id) }}" wire:navigate class="text-[10px] truncate w-14 text-center hover:text-primary flex items-center gap-0.5 justify-center">
                                            <x-user-status-sign :user="$match->user2" />
                                            <span class="truncate">{{ \Illuminate\Support\Str::limit($match->user2->name, 8) }}</span>
                                        </a>
                                    @else
                                        <x-avatar name="Del" size="xs" />
                                        <span class="text-[10px] text-muted-foreground">Удален</span>
                                    @endif
                                </div>
                            </div>
                            <div class="text-[0.625rem] text-muted-foreground/70 mt-2">
                                Мэтч ID: {{ $log->loggable_id }}
                            </div>
                            @else
                                <!-- Для всех остальных моделей - текстовая ссылка -->
                                @php $objUrl = $this->getObjectUrl($log); @endphp
                                @if($objUrl)
                                    <a href="{{ $objUrl }}" wire:navigate class="text-sm font-medium hover:text-primary transition-colors pt-1 inline-block">
                                        {{ class_basename($log->loggable_type) }} #{{ $log->loggable_id }}
                                    </a>
                                @else
                                    <span class="text-muted-foreground pt-1 inline-block">
                                        {{ class_basename($log->loggable_type) }} #{{ $log->loggable_id }}
                                    </span>
                                @endif
                            @endif
                        @else
                            <div class="flex flex-col gap-1 pt-1">
                                <span class="text-sm text-muted-foreground flex items-center gap-2">
                                    <x-lucide-trash-2 class="w-4 h-4 text-destructive/50" />
                                    {{ class_basename($log->loggable_type) }} #{{ $log->loggable_id }}
                                </span>
                                <span class="text-[10px] text-destructive font-medium">Объект удален</span>
                            </div>
                        @endif
                    </x-ui.table-cell>

                    <!-- Изменения (Diff) -->
                    <x-ui.table-cell class="max-w-[25rem] whitespace-normal">
                        @if($log->before || $log->after)
                            <details class="group">
                                <summary class="cursor-pointer text-sm hover:text-primary transition-colors flex items-center gap-1">
                                    <x-lucide-code class="w-4 h-4 text-muted-foreground" />
                                    Показать дифф
                                    <x-lucide-chevron-down class="shrink-0 w-4 h-4 inline group-open:rotate-180 transition-transform" />
                                </summary>
                                <div class="mt-2 p-3 bg-muted/30 rounded-lg overflow-x-auto space-y-2">
                                    @if($log->before)
                                        <div class="text-xs">
                                            <span class="text-red-500 font-bold">BEFORE:</span>
                                            <pre class="text-muted-foreground whitespace-pre-wrap font-mono mt-1">{{ json_encode($log->before, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    @endif
                                    
                                    @if($log->before && $log->after)
                                        <x-lucide-arrow-down class="w-4 h-4 text-muted-foreground" />
                                    @endif

                                    @if($log->after)
                                        <div class="text-xs">
                                            <span class="text-green-500 font-bold">AFTER:</span>
                                            <pre class="text-muted-foreground whitespace-pre-wrap font-mono mt-1">{{ json_encode($log->after, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    @endif
                                </div>
                            </details>
                        @else
                            <span class="text-xs text-muted-foreground">—</span>
                        @endif
                    </x-ui.table-cell>

                    <!-- IP -->
                    <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap align-top">
                        <div class="flex items-center gap-1.5 pt-1">
                            <x-lucide-globe class="w-3 h-3" />
                            {{ $log->ip_address }}
                        </div>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @empty
                <x-ui.table-row>
                    <x-ui.table-cell colspan="6" class="py-12 text-center text-muted-foreground">
                        <div class="flex flex-col items-center gap-2">
                            <x-lucide-shield-question class="w-12 h-12 opacity-30" />
                            <p>Логи не найдены</p>
                            <x-ui.button wire:click="clearSearchFilters" variant="outline" size="sm">Сбросить поиск</x-ui.button>
                        </div>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @endforelse
        </x-ui.table-body>
    </x-ui.table>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $this->logs->links('partials.pagination') }}
    </div>

    <!-- Info -->
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div class="text-xs text-muted-foreground">
            Показано {{ $this->logs->count() }} из {{ $this->logs->total() }} записей
            @if(!empty($search))
                <span class="ml-2">(фильтр: "{{ $search }}")</span>
            @endif
            @if($actionFilter !== 'all')
                <span class="ml-2">(действие: {{ $actionFilter }})</span>
            @endif
        </div>
    </div>
</div>