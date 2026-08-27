<?php

use App\Actions\Admin\BroadcastsAction;
use App\Models\Broadcast;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    /** @var array Выбранные чекбоксами рассылки для массового удаления */
    public array $selectedBroadcasts = [];
    
    /** @var bool Состояние чекбокса "Выбрать все на странице" */
    public bool $selectAll = false;
    
    #[Url(as: 'date_from', except: '')] 
    public ?string $dateFrom = null;
    
    #[Url(as: 'date_to', except: '')] 
    public ?string $dateTo = null;
    
    /** @var string Поиск (по названию, тексту или ID) */
    #[Url(as: 'q', except: '')]
    public string $search = '';
    
    /** @var string Фильтр статуса */
    #[Url(as: 'status', except: 'draft')]
    public string $statusFilter = 'draft';
    
    /** @var string Фильтр типа */
    #[Url(as: 'type', except: 'all')]
    public string $typeFilter = 'all';
    
    /** @var int Количество записей на страницу */
    public int $perPage = 10;

    /** @var string URL для кнопки "Назад" */
    public string $backUrl = '';

    // === ХУКИ ОБНОВЛЕНИЯ ФИЛЬТРОВ ===
    
    public function updatedSearch(): void 
    { 
        $this->resetPage(); 
        $this->clearComputedCache(); 

        // Умная подсветка вкладки при ручном вводе ID
        if (is_numeric($this->search) && !empty($this->search)) {
            $broadcast = Broadcast::find((int) $this->search);
            if ($broadcast) {
                $this->statusFilter = $broadcast->status;
            } else {
                $this->statusFilter = 'all';
            }
        }
    }

    // ФИКС: Очищаем поиск при смене любого фильтра
    public function updatedStatusFilter(): void { $this->search = ''; $this->resetPage(); $this->clearComputedCache(); }   
    public function updatedTypeFilter(): void { $this->search = ''; $this->resetPage(); $this->clearComputedCache(); }
    public function updatingDateFrom(): void { $this->search = ''; $this->resetPage(); $this->clearComputedCache(); }
    public function updatingDateTo(): void { $this->search = ''; $this->resetPage(); $this->clearComputedCache(); }

    public function mount(): void
    {
        // ФИКС: Запоминаем URL "Назад" только при первой загрузке
        $previousUrl = url()->previous();
        $this->backUrl = ($previousUrl && $previousUrl !== url()->current()) 
            ? $previousUrl 
            : route('admin.dashboard');

        // Умный поиск: если пришли по прямой ссылке ?q=123, автоматически переключаем вкладку
        if (!empty($this->search) && is_numeric($this->search)) {
            $broadcast = Broadcast::find((int) $this->search);
            if ($broadcast) {
                $this->statusFilter = $broadcast->status;
            }
        }
    }

    /**
     * Очистка строки поиска.
     */
    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
        $this->clearComputedCache();
    }

    /**
     * Обработка изменения галки "Выбрать все".
     */
    public function updatedSelectAll(): void
    {
        if ($this->selectAll) {
            $this->selectedBroadcasts = $this->broadcasts->getCollection()->pluck('id')->map(fn($id) => (string) $id)->toArray();
        } else {
            $this->selectedBroadcasts = [];
        }
    }

    // === ДЕЙСТВИЯ (ДЕЛЕГИРУЕМ В ACTION) ===

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->search = ''; // ФИКС: Очищаем поиск
        $this->resetPage();
        $this->clearComputedCache();
    }

    public function createBroadcast(): void
    {
        $this->redirect(route('admin.system.broadcasts.create'), navigate: true);
    }

    public function editBroadcast(int $id): void
    {
        $this->redirect(route('admin.system.broadcasts.edit', $id), navigate: true);
    }

    public function sendNow(int $id, BroadcastsAction $action): void
    {
        $result = $action->sendNow($id, auth()->user());
        
        $this->dispatch('show-toast', type: $result['success'] ? 'success' : 'info', message: $result['message']);
        
        if ($result['success']) {
            $this->clearComputedCache();
        }
    }

    public function duplicateBroadcast(int $id, BroadcastsAction $action): void
    {
        $newBroadcast = $action->duplicateBroadcast($id, auth()->user());
        
        if ($newBroadcast) {
            $this->dispatch('show-toast', type: 'success', message: 'Рассылка скопирована в черновики');
            $this->redirect(route('admin.system.broadcasts.edit', $newBroadcast->id), navigate: true);
        } else {
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера!');
        }
    }

    public function deleteBroadcast(int $id, BroadcastsAction $action): void
    {
        $result = $action->deleteBroadcast($id, auth()->user());
        
        $this->dispatch('show-toast', type: $result['success'] ? 'success' : 'error', message: $result['message']);
        
        if ($result['success']) {
            $this->clearComputedCache();
        }
    }

    public function deleteSelected(BroadcastsAction $action): void
    {
        if (empty($this->selectedBroadcasts)) {
            $this->dispatch('show-toast', type: 'info', message: 'Не выбрано ни одной рассылки.');
            return;
        }

        $actualDeletedCount = $action->deleteSelected($this->selectedBroadcasts, auth()->user());

        if ($actualDeletedCount > 0) {
            $this->dispatch('show-toast', type: 'success', message: "Удалено {$actualDeletedCount} рассылок.");
        } else {
            $this->dispatch('show-toast', type: 'info', message: 'Нет доступных для удаления рассылок (возможно, они в процессе отправки).');
        }

        $this->selectedBroadcasts = [];
        $this->selectAll = false;
        $this->clearComputedCache();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'typeFilter', 'dateFrom', 'dateTo']);
        $this->statusFilter = 'all';
        $this->typeFilter = 'all';
        $this->resetPage();
        $this->clearComputedCache();
    }

    #[On('broadcast-saved')]
    public function refreshList(): void
    {
        $this->clearComputedCache();
    }

    // === ВЫЧИСЛЯЕМЫЕ СВОЙСТВА (DATA SOURCE) ===

       #[Computed]
    public function broadcasts()
    {
        $avatarQuery = fn($q) => $q->select(['user_id', 'is_primary', 'path_thumb', 'path_medium'])
                                  ->orderByDesc('is_primary')
                                  ->limit(1);

        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

        $paginated = Broadcast::query()
            // ФИКС 1: withTrashed() для админа-автора рассылки
            ->with(['admin' => fn($q) => $q->withTrashed()->select('id', 'name', 'email', 'last_seen')->with(['photos' => $avatarQuery])])
            ->when($this->search, function ($query) use ($operator) {
                $search = '%' . $this->search . '%';
                $query->where(function ($q) use ($search, $operator) {
                    $q->where('title', $operator, $search)
                      ->orWhere('message', $operator, $search);
                    if (is_numeric($this->search)) {
                        $q->orWhere('id', (int) $this->search);
                    }
                });
            })
            ->when($this->statusFilter !== 'all', fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter !== 'all', fn($q) => $q->where('type', $this->typeFilter))
            ->when($this->dateFrom, fn($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->latest('created_at')
            ->paginate($this->perPage);

        $targetUserIds = $paginated->getCollection()
            ->pluck('target_audience.user_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (!empty($targetUserIds)) {
            // ФИКС 2: withTrashed() для юзера-получателя рассылки
            $targetUsers = User::with(['photos' => $avatarQuery])
                ->withTrashed() // <-- ВОТ ЭТО ДОБАВИЛИ
                ->whereIn('id', $targetUserIds)
                ->get()
                ->keyBy('id');

            $paginated->getCollection()->each(function ($broadcast) use ($targetUsers) {
                $userId = $broadcast->target_audience['user_id'] ?? null;
                $broadcast->setRelation('targetUser', $userId ? $targetUsers->get($userId) : null);
            });
        }

        return $paginated;
    }

    #[Computed]
    public function counts(): array
    {
        $counts = Broadcast::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        return [
            'draft' => $counts['draft'] ?? 0,
            'scheduled' => $counts['scheduled'] ?? 0,
            'sending' => $counts['sending'] ?? 0,
            'sent' => $counts['sent'] ?? 0,
            'failed' => $counts['failed'] ?? 0,
            'total' => array_sum($counts),
        ];
    }

    private function clearComputedCache(): void
    {
        unset($this->broadcasts);
        unset($this->counts);
    }
}; 
?>

<div class="space-y-6 pb-6">
    <!-- Заголовок страницы -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-4">
            <a href="{{ $backUrl }}" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            <h1 class="text-2xl font-semibold flex items-center gap-2">
                <x-lucide-radio class="w-6 h-6" />
                Рассылки
                @if ($this->counts['draft'] > 0)
                    <x-ui.badge variant="warning" size="sm">{{ $this->counts['draft'] }} черновиков</x-ui.badge>
                @endif
            </h1>
        </div>

       <x-ui.button wire:click="createBroadcast" variant="default" size="sm">
            <x-lucide-plus class="w-4 h-4" />
            Создать рассылку
        </x-ui.button>
    </div>

    <!-- Панель фильтров и массовых действий -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        @if (count($selectedBroadcasts) > 0)
            <x-ui.alert-dialog>
                <x-ui.alert-dialog-trigger>
                    <x-ui.button variant="destructive" size="sm" class="gap-2">
                        <x-lucide-trash-2 class="w-4 h-4 inline" />
                        Удалить выбранные
                        <x-ui.badge variant="warning" size="xs">{{ count($selectedBroadcasts) }}</x-ui.badge>
                    </x-ui.button>
                </x-ui.alert-dialog-trigger>
                <x-ui.alert-dialog-content>
                    <x-ui.alert-dialog-header>
                        <x-ui.alert-dialog-title>Удалить выбранные рассылки?</x-ui.alert-dialog-title>
                        <x-ui.alert-dialog-description>
                            Будут удалены <strong>{{ count($selectedBroadcasts) }}</strong> рассылок. Рассылки в процессе отправки удалены не будут. Это действие нельзя отменить.
                        </x-ui.alert-dialog-description>
                    </x-ui.alert-dialog-header>
                    <x-ui.alert-dialog-footer>
                        <x-ui.alert-dialog-cancel>Отмена</x-ui.alert-dialog-cancel>
                        <x-ui.alert-dialog-action wire:click="deleteSelected">Удалить</x-ui.alert-dialog-action>
                    </x-ui.alert-dialog-footer>
                </x-ui.alert-dialog-content>
            </x-ui.alert-dialog>
        @else
            <x-ui.button variant="secondary" size="sm" class="gap-2 opacity-50 cursor-not-allowed">
                <x-lucide-trash-2 class="w-4 h-4 inline" /> Не выбрано
            </x-ui.button>
        @endif

        <div class="flex items-center gap-2 self-end">
            <x-ui.button wire:click="resetFilters" variant="outline" size="sm">
                <x-lucide-rotate-ccw class="w-4 h-4" />
                <span>Сбросить</span>
            </x-ui.button>

            <x-ui.select wire:model.live="typeFilter" class="min-w-32">
                <x-ui.select-trigger><x-ui.select-value placeholder="Тип" /></x-ui.select-trigger>
                <x-ui.select-content>
                    <x-ui.select-item value="all">Все типы</x-ui.select-item>
                    <x-ui.select-item value="in_app">В приложении</x-ui.select-item>
                    <x-ui.select-item value="email">Email</x-ui.select-item>
                    <x-ui.select-item value="push">Push</x-ui.select-item>
                </x-ui.select-content>
            </x-ui.select>

            <div class="flex items-center gap-2">
                <x-ui.date-picker wire:model.live="dateFrom" placeholder="с" width="w-[10rem]" />
                <span class="text-muted-foreground">—</span>
                <x-ui.date-picker wire:model.live="dateTo" placeholder="по" width="w-[10rem]" />
            </div>

            <div class="relative w-64">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск по ID или тексту..." class="pl-9 pr-8" />
                <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                @if (!empty($search))
                    <button wire:click="clearSearch" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground z-10">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Кнопки фильтрации по статусам -->
    <div class="flex flex-wrap gap-1.5">
        <x-ui.button wire:click="setStatusFilter('all')" variant="{{ $statusFilter === 'all' ? 'default' : 'secondary' }}" size="sm">
            Все <x-ui.badge size="xs">{{ $this->counts['total'] }}</x-ui.badge>
        </x-ui.button>
        <x-ui.button wire:click="setStatusFilter('draft')" variant="{{ $statusFilter === 'draft' ? 'default' : 'secondary' }}" size="sm">
            Черновики <x-ui.badge size="xs" variant="warning">{{ $this->counts['draft'] }}</x-ui.badge>
        </x-ui.button>
        <x-ui.button wire:click="setStatusFilter('scheduled')" variant="{{ $statusFilter === 'scheduled' ? 'default' : 'secondary' }}" size="sm">
            Запланированы <x-ui.badge size="xs" variant="info">{{ $this->counts['scheduled'] }}</x-ui.badge>
        </x-ui.button>
        <x-ui.button wire:click="setStatusFilter('sending')" variant="{{ $statusFilter === 'sending' ? 'default' : 'secondary' }}" size="sm">
            В процессе <x-ui.badge size="xs" variant="info">{{ $this->counts['sending'] }}</x-ui.badge>
        </x-ui.button>
        <x-ui.button wire:click="setStatusFilter('sent')" variant="{{ $statusFilter === 'sent' ? 'default' : 'secondary' }}" size="sm">
            Отправлены <x-ui.badge size="xs" variant="success">{{ $this->counts['sent'] }}</x-ui.badge>
        </x-ui.button>
        <x-ui.button wire:click="setStatusFilter('failed')" variant="{{ $statusFilter === 'failed' ? 'default' : 'secondary' }}" size="sm">
            Ошибки <x-ui.badge size="xs" variant="destructive">{{ $this->counts['failed'] }}</x-ui.badge>
        </x-ui.button>
    </div>

    <!-- Таблица рассылок. Polling (2s) активируется только если есть рассылки в статусе 'sending' -->
    <x-ui.table :poll="($this->counts['sending'] > 0 || $this->counts['scheduled'] > 0) ? '2s' : false" >
        <x-ui.table-header>
            <x-ui.table-row>
                <x-ui.table-head class="w-8"><x-checkbox wire:model.live="selectAll" /></x-ui.table-head>
                <x-ui.table-head class="w-12 text-xs">ID</x-ui.table-head>
                <x-ui.table-head>Рассылка</x-ui.table-head>
                <x-ui.table-head>Тип</x-ui.table-head>
                <x-ui.table-head>Аудитория</x-ui.table-head>
                <x-ui.table-head>Статус</x-ui.table-head>
                <x-ui.table-head>Автор</x-ui.table-head>
                <x-ui.table-head>Создано</x-ui.table-head>
                <x-ui.table-head>Отправлено</x-ui.table-head>
                <x-ui.table-head class="text-right">Действия</x-ui.table-head>
            </x-ui.table-row>
        </x-ui.table-header>

        <x-ui.table-body>
            @forelse ($this->broadcasts as $broadcast)
                @php 
                    $isHighlighted = is_numeric($this->search) && $broadcast->id == (int)$this->search; 
                @endphp
                <x-ui.table-row 
                    wire:key="broadcast-row-{{ $broadcast->id }}" 
                    class="{{ in_array($broadcast->id, array_map('intval', $this->selectedBroadcasts)) ? 'bg-muted/50' : '' }} {{ $isHighlighted ? 'bg-blue-500/10 ring-2 ring-blue-500/50' : '' }}"
                    x-data="{ isHi: {{ $isHighlighted ? 'true' : 'false' }} }"
                    x-init="isHi && setTimeout(() => { $el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 200)"
                >             
                    
                    <x-ui.table-cell class="w-8">
                        <x-checkbox wire:model.live="selectedBroadcasts" value="{{ $broadcast->id }}" />
                    </x-ui.table-cell>
                    
                    <x-ui.table-cell class="text-xs text-muted-foreground/70 whitespace-nowrap {{ $isHighlighted ? 'text-blue-500 font-bold' : '' }}">
                        #{{ $broadcast->id }}
                    </x-ui.table-cell>
                    
                    <x-ui.table-cell class="max-w-[18rem] whitespace-normal">
                        <a href="{{ route('admin.system.broadcasts.edit', $broadcast->id) }}" wire:navigate class="block group cursor-pointer">
                            <div class="font-medium text-sm line-clamp-1 group-hover:text-primary transition-colors">
                                {{ $broadcast->title }}
                            </div>
                            <div class="text-xs text-muted-foreground line-clamp-1">
                                {{ \Illuminate\Support\Str::limit($broadcast->message, 60) }}
                            </div>
                        </a>
                    </x-ui.table-cell>
                    
                    <x-ui.table-cell>
                        @if ($broadcast->type === 'in_app')
                            <x-ui.badge variant="secondary" size="xs"><x-lucide-bell class="w-3 h-3 inline mr-1" />Site</x-ui.badge>
                        @elseif($broadcast->type === 'email')
                            <x-ui.badge variant="warning" size="xs"><x-lucide-mail class="w-3 h-3 inline mr-1" />Email</x-ui.badge>
                        @else
                            <x-ui.badge variant="info" size="xs"><x-lucide-smartphone class="w-3 h-3 inline mr-1" />Push</x-ui.badge>
                        @endif
                    </x-ui.table-cell>
               
                    <x-ui.table-cell class="max-w-64">
                        @if (!empty($broadcast->target_audience['user_id']) && $broadcast->targetUser)
                            <a href="{{ route('admin.users.show', $broadcast->targetUser->id) }}" wire:navigate class="flex items-center gap-2 group">
                                <x-avatar 
                                    src="{{ $broadcast->targetUser->avatar_url }}" 
                                    name="{{ $broadcast->targetUser->name }}" 
                                    size="sm" 
                                    userId="{{ $broadcast->targetUser->id }}" 
                                    showStatus="true" 
                                    :isOnline="$broadcast->targetUser->is_online" 
                                />
                                <div class="flex flex-col">
                                    <span class="text-sm font-medium group-hover:text-primary transition-colors flex items-center gap-1">
                                        <x-user-status-sign :user="$broadcast->targetUser" />
                                        {{ $broadcast->targetUser->name }}
                                    </span>
                                    <span class="text-xs text-muted-foreground">
                                        {{ $broadcast->targetUser->email }}
                                    </span>
                                </div>
                            </a>
                        @else
                            <ul class="text-sm whitespace-normal flex gap-2 flex-wrap" title="{{ $broadcast->audience_label }}">
                                @forelse ($broadcast->audience_parts as $part)
                                    <li class="border border-border rounded-sm p-2 bg-card text-card-foreground whitespace-nowrap">{{ $part }}</li>
                                @empty
                                    <li class="border border-border rounded-sm p-2 bg-card text-card-foreground ">Все пользователи</li>
                                @endforelse
                            </ul>
                        @endif
                    </x-ui.table-cell>

                    <x-ui.table-cell class="w-30">
                        @if ($broadcast->status === 'draft')
                            <x-ui.badge variant="warning" size="sm">Черновик</x-ui.badge>
                        @elseif($broadcast->status === 'scheduled')
                            <x-ui.badge variant="info" size="sm">Запланировано</x-ui.badge>
                            <div class="text-xs text-muted-foreground mt-1">{{ $broadcast->scheduled_at?->format('d.m.Y H:i') }}</div>
                        @elseif($broadcast->status === 'sending')
                            <div class="flex items-center gap-2 text-blue-500 font-medium text-sm mb-1">
                                Отправка... ({{ $broadcast->progress }}%)
                            </div>
                            <div class="w-full bg-muted rounded-full h-1.5">
                                <div class="bg-blue-500 h-1.5 rounded-full transition-all duration-500" style="width: {{ $broadcast->progress }}%"></div>
                            </div>
                            <div class="text-[10px] text-muted-foreground mt-1 text-center">
                                {{ $broadcast->sent_count }} / {{ $broadcast->total_recipients }}
                            </div>
                        @elseif($broadcast->status === 'sent')
                            <x-ui.badge variant="success" size="sm">Отправлено</x-ui.badge>
                            <div class="text-xs text-muted-foreground mt-1">
                                {{ $broadcast->sent_count }} доставлено 
                            </div>
                            @if($broadcast->failed_count > 0)
                                <div class="text-xs text-destructive">
                                    {{ $broadcast->failed_count }} ошибок
                                </div>
                            @endif
                        @elseif($broadcast->status === 'failed')
                            <x-ui.badge variant="destructive" size="sm">Ошибка</x-ui.badge>
                            <div class="text-xs text-destructive mt-1">
                                Упало: {{ $broadcast->failed_count }}
                            </div>
                        @endif
                    </x-ui.table-cell>
                    
                    <x-ui.table-cell>
                        @if($broadcast->admin)
                            <a href="{{ route('admin.users.show', $broadcast->admin->id) }}" wire:navigate class="flex items-center gap-2 group">
                                <x-avatar 
                                    src="{{ $broadcast->admin->avatar_url }}" 
                                    name="{{ $broadcast->admin->name }}" 
                                    size="sm" 
                                    userId="{{ $broadcast->admin->id }}"
                                    showStatus="true" 
                                    :isOnline="$broadcast->admin->is_online" 
                                />
                                <div class="flex flex-col">
                                    <span> 
                                        <x-user-status-sign :user="$broadcast->admin" />
                                        <span class="text-sm font-medium group-hover:text-primary transition-colors">
                                            {{ $broadcast->admin->name }}
                                        </span>
                                    </span>
                                    <span class="text-xs text-muted-foreground">
                                        {{ $broadcast->admin->email }}
                                    </span>
                                </div>
                            </a>
                        @else
                            <x-ui.badge variant="secondary" size="sm">Система</x-ui.badge>
                        @endif
                    </x-ui.table-cell>

                   <!-- ЯЧЕЙКА "Создано" -->
                    <x-ui.table-cell class="text-muted-foreground text-xs whitespace-nowrap">
                        <div>{{ $broadcast->created_at->format('d.m.Y') }}</div>
                        <div class="text-[10px] opacity-70">{{ $broadcast->created_at->format('H:i') }}</div>
                    </x-ui.table-cell>

                   <!-- ЯЧЕЙКА "Отправлено" -->
                    <x-ui.table-cell class="text-muted-foreground text-xs whitespace-nowrap font-medium">
                        @php
                            $sentDate = $broadcast->sent_at ?? $broadcast->started_at;
                        @endphp
                        @if ($sentDate)
                            <div>{{ $sentDate->format('d.m.Y') }}</div>
                            <div class="text-[10px] opacity-70">{{ $sentDate->format('H:i') }}</div>
                        @else
                            <span class="text-muted-foreground/40">—</span>
                        @endif
                    </x-ui.table-cell>

                    <x-ui.table-cell class="text-right">
                        {{-- ОБЕРТКА С wire:key СО СТАТУСОМ: Гарантирует пересоздание меню при смене статуса (фикс для Alpine Teleport) --}}
                        <div wire:key="dropdown-wrapper-{{ $broadcast->id }}-{{ $broadcast->status }}">
                      
                            <x-ui.dropdown-menu>
                                <x-ui.dropdown-menu-trigger>
                                    <x-ui.button variant="ghost" size="icon-sm">
                                        <x-lucide-more-horizontal class="w-4 h-4" />
                                    </x-ui.button>
                                </x-ui.dropdown-menu-trigger>
                                
                                <x-ui.dropdown-menu-content align="end" wire:key="actions-{{ $broadcast->id }}-{{ $broadcast->status }}">
                                    
                                    @if (in_array($broadcast->status, ['draft', 'scheduled']))
                                        <x-ui.dropdown-menu-item wire:click="editBroadcast({{ $broadcast->id }})" x-on:click="open = false">
                                            <x-lucide-pencil class="w-4 h-4" /> Редактировать
                                        </x-ui.dropdown-menu-item>
                                        <x-ui.dropdown-menu-item wire:click="sendNow({{ $broadcast->id }})" x-on:click="open = false">
                                            <x-lucide-send class="w-4 h-4" /> Отправить сейчас
                                        </x-ui.dropdown-menu-item>
                                    @endif

                                    @if (in_array($broadcast->status, ['draft', 'scheduled', 'sent', 'failed']))
                                        <x-ui.dropdown-menu-item wire:click="duplicateBroadcast({{ $broadcast->id }})" x-on:click="open = false">
                                            <x-lucide-copy class="w-4 h-4" /> Дублировать
                                        </x-ui.dropdown-menu-item>

                                        <x-ui.dropdown-menu-item variant="destructive" wire:click="deleteBroadcast({{ $broadcast->id }})" wire:confirm="Удалить рассылку?" x-on:click="open = false">
                                            <x-lucide-trash-2 class="w-4 h-4" /> Удалить
                                        </x-ui.dropdown-menu-item>
                                    @endif

                                </x-ui.dropdown-menu-content>
                            </x-ui.dropdown-menu>
                        </div>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @empty
                <x-ui.table-row>
                    <x-ui.table-cell colspan="10" class="py-12 text-center text-muted-foreground bg-card">
                        <x-ui.empty>
                            <x-ui.empty-header>
                                <x-ui.empty-media variant="icon">
                                    <x-lucide-radio class="w-12 h-12 opacity-30" />
                                </x-ui.empty-media>
                                <x-ui.empty-title>Нет рассылок</x-ui.empty-title>       
                            </x-ui.empty-header>    
                        </x-ui.empty>                                                                        
                    </x-ui.table-cell>
                </x-ui.table-row>
            @endforelse
        </x-ui.table-body>
    </x-ui.table>

    <!-- Пагинация -->
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div class="text-xs text-muted-foreground">
            Показано {{ $this->broadcasts->firstItem() ?? 0 }} - {{ $this->broadcasts->lastItem() ?? 0 }} из {{ $this->broadcasts->total() }}
        </div>
        {{ $this->broadcasts->links('partials.pagination') }}
    </div>

</div>