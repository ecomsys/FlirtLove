<?php

use App\Models\AdminLog;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    // ФИКС: Переводим фильтры на #[Url], выкидываем session()
    #[Url(as: 'q', except: '')]
    public string $search = '';
    
    #[Url(as: 'cat', except: 'all')]
    public string $categoryFilter = 'all';
    
    #[Url(as: 'act', except: 'all')]
    public string $actionFilter = 'all';
    
    #[Url(as: 'adm', except: '')]
    public string $adminFilter = '';
    
    #[Url(as: 'date', except: '')]
    public string $dateFilter = '';

    public int $perPage = 15;

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedDateFilter(): void { $this->resetPage(); }
    public function updatedAdminFilter(): void { $this->resetPage(); }

    public function setCategoryFilter(string $category): void
    {
        $this->categoryFilter = $category;
        $this->actionFilter = 'all'; // Сбрасываем действие при смене категории
        $this->resetPage();
    }

    public function setActionFilter(string $action): void
    {
        $this->actionFilter = $action;
        $this->resetPage();
    }

    public function clearSearchFilters(): void
    {
        $this->reset(['search', 'dateFilter']);
        $this->resetPage();
    }

       public function getObjectUrl(AdminLog $log): ?string
    {
        // 1. Если объекта нет в БД (удален) — сразу возвращаем null (ссылки не будет)
        if (!$log->loggable) {
            return null;
        }

        $modelClass = $log->loggable_type;
        $id = $log->loggable_id;

        // ФИКС: Особая логика для Чатов, так как у них разные страницы просмотра в зависимости от типа
        if ($modelClass === \App\Models\Chat::class) {
            $chatType = $log->loggable->type ?? 'private';
            $routeName = $chatType === 'support' 
                ? 'admin.communication.support' 
                : 'admin.communication.chats';
                
            return Route::has($routeName) ? route($routeName, ['q' => $id]) : null;
        }

        // 2. Карта роутов для остальных моделей
        $routeMap = [
            // --- Прямые ссылки на редактирование/просмотр (Route Model Binding) ---
            \App\Models\User::class           => ['admin.users.show', null],
            \App\Models\Page::class           => ['admin.system.pages.edit', null],
            \App\Models\Broadcast::class      => ['admin.system.broadcasts.edit', null],
            \App\Models\Diary::class          => ['admin.moderation.diary.moderate', null],
            \App\Models\BlogPost::class       => ['admin.system.blog.index', 'q'],

            // --- Ссылки на списки с автопоиском по ID (?q=ID) ---
            \App\Models\Photo::class          => ['admin.moderation.photos', 'q'],
            \App\Models\PhotoComment::class   => ['admin.moderation.photo-comments', 'q'],
            \App\Models\DiaryComment::class   => ['admin.moderation.diary.comments', 'q'],
            \App\Models\Report::class         => ['admin.moderation.reports', 'q'],
            \App\Models\Swipe::class          => ['admin.moderation.dating', 'q'],
            \App\Models\UserMatch::class      => ['admin.moderation.dating', 'q'],
            \App\Models\SupportTemplate::class=> ['admin.communication.templates', 'q'],
            \App\Models\FraudAlert::class     => ['admin.security.fraud-alerts.index', 'q'],
            \App\Models\Transaction::class    => ['admin.finances.transactions', 'q'],
            \App\Models\UserSubscription::class => ['admin.finances.subscriptions', 'q'],
            
            // --- Специфичные GET-параметры ---
            \App\Models\UserGift::class       => ['admin.finances.gifts', 'history_search'], // История дарений
            \App\Models\Gift::class           => ['admin.finances.gifts', 'catalog_search'], // Каталог подарков
        ];

        if (!isset($routeMap[$modelClass])) {
            return null;
        }

        [$routeName, $paramName] = $routeMap[$modelClass];

        if (!Route::has($routeName)) {
            return null;
        }

        if ($paramName === null) {
            return route($routeName, $id);
        }

        return route($routeName, [$paramName => $id]);
    }

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
            \App\Models\Broadcast::class => [],
        ]);

        return $logs;
    }

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

    #[Computed]
    public function admins(): array
    {
        return Cache::remember('admin_audit_admins_list', 60, function () {
            return User::whereHas('adminLogs')
                ->select('id', 'name')
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();
        });
    }

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
                Журнал админов
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
                        @php
                            $type = $log->loggable_type ? class_basename($log->loggable_type) : 'Система';
                            $id = $log->loggable_id;
                            $url = $this->getObjectUrl($log);
                            
                            // Проверяем, является ли это системным действием (без привязки к конкретному ID)
                            $isSystemAction = in_array($log->loggable_type, ['settings', null]) || !$id;
                            
                            // Объект считается удаленным, только если он должен был быть, но его нет
                            $isDeleted = !$log->loggable && $log->loggable_type && !$isSystemAction;
                        @endphp

                        <div class="flex flex-col gap-0.5 pt-1">
                            @if ($isSystemAction)
                                <span class="text-sm font-medium text-muted-foreground">
                                    Системные настройки
                                </span>
                            @elseif ($url)
                                <a href="{{ $url }}" wire:navigate class="text-sm font-medium text-primary hover:underline transition-colors">
                                    {{ $type }} #{{ $id }}
                                </a>
                            @else
                                <span class="text-sm font-medium text-muted-foreground">
                                    {{ $type }} #{{ $id }}
                                </span>
                            @endif
                            
                            @if ($isDeleted)
                                <span class="text-[10px] text-destructive/80">объект удален</span>
                            @endif
                        </div>
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