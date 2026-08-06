<?php

use App\Models\AdminLog;
use App\Models\Broadcast;
use App\Models\User;
use App\Jobs\SendBroadcastJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Session;
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
    
    #[Session] 
    public ?string $dateFrom = null;
    #[Session] 
    public ?string $dateTo = null;
    #[Session] 
    public string $search = '';
    #[Session] 
    public string $statusFilter = 'all';
    #[Session] 
    public string $typeFilter = 'all';
    
    /** @var int Количество записей на страницу */
    public int $perPage = 10;

    // === ХУКИ ОБНОВЛЕНИЯ ФИЛЬТРОВ ===
    // При изменении любого фильтра сбрасываем пагинацию и очищаем кэш вычисляемых свойств,
    // чтобы Livewire гарантированно сделал новый запрос в БД.
    
    public function updatingSearch(): void { $this->resetPage(); $this->clearComputedCache(); }
    public function updatingStatusFilter(): void { $this->resetPage(); $this->clearComputedCache(); }   
    public function updatingTypeFilter(): void { $this->resetPage(); $this->clearComputedCache(); }
    public function updatingDateFrom(): void { $this->resetPage(); $this->clearComputedCache(); }
    public function updatingDateTo(): void { $this->resetPage(); $this->clearComputedCache(); }

    /**
     * Обработка изменения галки "Выбрать все".
     * Заполняет массив ID-шников текущей страницы или очищает его.
     */
    public function updatedSelectAll(): void
    {
        if ($this->selectAll) {
            $this->selectedBroadcasts = $this->broadcasts->getCollection()->pluck('id')->map(fn($id) => (string) $id)->toArray();
        } else {
            $this->selectedBroadcasts = [];
        }
    }

    // === ДЕЙСТВИЯ (ACTION METHODS) ===

    /**
     * Установка фильтра статуса через кнопки над таблицей.
     */
    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
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

    /**
     * Ручной запуск рассылки.
     * Использует атомарный запрос UPDATE с условием WHERE IN, 
     * чтобы защититься от двойного клика (Race Condition).
     */
    public function sendNow(int $id): void
    {
        $broadcast = Broadcast::find($id);
        
        if (!$broadcast || !in_array($broadcast->status, ['draft', 'scheduled'])) {
            $this->dispatch('show-toast', type: 'info', message: 'Эту рассылку уже нельзя отправить.');
            return;
        }

        try {
            // 1. Сохраняем состояние ДО запуска
            $before = $broadcast->only(['status', 'started_at']);

            $updated = Broadcast::where('id', $id)
                ->whereIn('status', ['draft', 'scheduled'])
                ->update([
                    'status' => 'sending', 
                    'started_at' => now()
                ]);
            
            if ($updated) {
                // 2. Обновляем модель в памяти и получаем состояние ПОСЛЕ
                $broadcast->refresh();
                $after = $broadcast->only(['status', 'started_at']);

                SendBroadcastJob::dispatch($broadcast->id, $broadcast->target_audience)->onQueue('broadcasts');
                
                // 3. Передаем дифф в лог!
                AdminLog::record('broadcast.send_now', $broadcast, auth()->user(), $before, $after);
                Log::info("Админ запустил рассылку вручную", ['broadcast_id' => $id, 'admin_id' => auth()->id()]);
                
                $this->dispatch('show-toast', type: 'success', message: 'Рассылка поставлена в очередь');
                $this->clearComputedCache();
            }
        } catch (\Exception $e) {
            Log::error("Ошибка ручного запуска рассылки: " . $e->getMessage());
            $broadcast->markAsFailed();
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера при запуске!');
        }
    }

     /**
     * Дублирование рассылки.
     * Создает копию со статусом "draft" и сброшенными счетчиками/датами.
     */
    public function duplicateBroadcast(int $id): void
    {
        try {
            $broadcast = Broadcast::find($id);
            if ($broadcast) {
                // Сохраняем ID источника для лога
                $before = ['source_id' => $broadcast->id, 'source_title' => $broadcast->title];

                $new = $broadcast->replicate();
                $new->status = 'draft';
                $new->sent_at = null;
                $new->scheduled_at = null;
                $new->sent_count = 0;
                $new->failed_count = 0;
                $new->total_recipients = 0;
                $new->started_at = null;
                $new->save();

                // Фиксируем, что создалось
                $after = ['new_id' => $new->id, 'new_title' => $new->title, 'status' => 'draft'];

                // Логируем с диффом!
                AdminLog::record('broadcast.duplicate', $broadcast, auth()->user(), $before, $after);
                Log::info("Админ продублировал рассылку", ['source_id' => $id, 'new_id' => $new->id]);

                $this->dispatch('show-toast', type: 'success', message: 'Рассылка скопирована в черновики');
                
                $this->redirect(route('admin.system.broadcasts.edit', $new->id), navigate: true);
            }
        } catch (\Exception $e) {
            Log::error("Ошибка дублирования рассылки: " . $e->getMessage());
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера!');
        }
    }

        /**
     * Удаление одной рассылки.
     * Запрещено удаление рассылок в статусе 'sending'.
     */
    public function deleteBroadcast(int $id): void
    {
        try {
            $broadcast = Broadcast::find($id);
            if ($broadcast) {
                if ($broadcast->status === 'sending') {
                    $this->dispatch('show-toast', type: 'error', message: 'Нельзя удалить рассылку в процессе отправки!');
                    return;
                }

                // Сохраняем данные перед удалением
                $before = $broadcast->only(['id', 'title', 'type', 'status', 'target_audience']);

                AdminLog::record('broadcast.delete', $broadcast, auth()->user(), $before, null);
                $broadcast->delete();
                Log::info("Админ удалил рассылку", ['broadcast_id' => $id]);
                
                $this->dispatch('show-toast', type: 'success', message: 'Рассылка удалена');
                $this->clearComputedCache();
            }
        } catch (\Exception $e) {
            Log::error("Ошибка удаления рассылки: " . $e->getMessage());
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера!');
        }
    }

       /**
     * Массовое удаление выбранных рассылок.
     * Использует транзакцию и блокировку строк, чтобы безопасно удалить и залогировать.
     */
    public function deleteSelected(): void
    {
        if (empty($this->selectedBroadcasts)) {
            $this->dispatch('show-toast', type: 'info', message: 'Не выбрано ни одной рассылки.');
            return;
        }

        try {
            $actualDeletedCount = 0;
            
            DB::transaction(function () use (&$actualDeletedCount) {
                $broadcasts = Broadcast::whereIn('id', $this->selectedBroadcasts)
                    ->where('status', '!=', 'sending')
                    ->lockForUpdate()
                    ->get();
                
                foreach ($broadcasts as $broadcast) {
                    // Сохраняем данные перед удалением
                    $before = $broadcast->only(['id', 'title', 'type', 'status', 'target_audience']);
                    
                    AdminLog::record('broadcast.delete', $broadcast, auth()->user(), $before, null);
                    $broadcast->delete();
                    $actualDeletedCount++;
                }
            });

            if ($actualDeletedCount > 0) {
                Log::info("Админ удалил рассылки", ['count' => $actualDeletedCount, 'admin_id' => auth()->id()]);
                $this->dispatch('show-toast', type: 'success', message: "Удалено {$actualDeletedCount} рассылок.");
            } else {
                $this->dispatch('show-toast', type: 'info', message: 'Нет доступных для удаления рассылок (возможно, они в процессе отправки).');
            }

            $this->selectedBroadcasts = [];
            $this->selectAll = false;
            $this->clearComputedCache();
        } catch (\Exception $e) {
            Log::error("Ошибка массового удаления рассылок: " . $e->getMessage());
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера при удалении!');
        }
    }

    /**
     * Сброс всех фильтров к значениям по умолчанию.
     */
    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'typeFilter', 'dateFrom', 'dateTo']);
        $this->resetPage();
        $this->clearComputedCache();
    }

    /**
     * Слушатель события сохранения формы.
     * Обновляет список, когда мы возвращаемся со страницы создания/редтирования.
     */
    #[On('broadcast-saved')]
    public function refreshList(): void
    {
        $this->clearComputedCache();
    }

    // === ВЫЧИСЛЯЕМЫЕ СВОЙСТВА (DATA SOURCE) ===

    /**
     * Получение пагинированного списка рассылок с фильтрами.
     * Жадно загружает админа-автора и целевого юзера (если рассылка персональная).
     */
    #[Computed]
    public function broadcasts()
    {
        $avatarQuery = fn($q) => $q->select(['user_id', 'is_primary', 'path_thumb', 'path_medium'])
                                  ->orderByDesc('is_primary')
                                  ->limit(1);

        $paginated = Broadcast::query()
            ->with(['admin' => fn($q) => $q->select('id', 'name', 'email', 'last_seen')->with(['photos' => $avatarQuery])])
            ->when($this->search, function ($query) {
                $search = '%' . $this->search . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'ilike', $search)
                      ->orWhere('message', 'ilike', $search);
                });
            })
            ->when($this->statusFilter !== 'all', fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter !== 'all', fn($q) => $q->where('type', $this->typeFilter))
            ->when($this->dateFrom, fn($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->latest('created_at')
            ->paginate($this->perPage);

        // Жадная загрузка целевых юзеров для персональных рассылок
        $targetUserIds = $paginated->getCollection()
            ->pluck('target_audience.user_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (!empty($targetUserIds)) {
            $targetUsers = User::with(['photos' => $avatarQuery])
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

    /**
     * Подсчет количества рассылок по каждому статусу для кнопок-фильтров.
     */
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

    // === ХЕЛПЕРЫ ===

    /**
     * Очистка кэша вычисляемых свойств (broadcasts и counts).
     * Вызывается при любом действии или изменении фильтра, чтобы таблица всегда была свежей.
     */
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
        <h1 class="text-2xl font-semibold flex items-center gap-2">
            <x-lucide-radio class="w-6 h-6" />
            Рассылки
            @if ($this->counts['draft'] > 0)
                <x-ui.badge variant="warning" size="sm">{{ $this->counts['draft'] }} черновиков</x-ui.badge>
            @endif
        </h1>

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
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск..." class="pl-9 pr-8" />
                <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                @if (!empty($search))
                    <button wire:click="$set('search', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
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
                {{-- Статичный wire:key по ID гарантирует, что строка не будет перестроена с нуля при поллинге --}}
                <x-ui.table-row wire:key="broadcast-row-{{ $broadcast->id }}" 
                    class="{{ in_array($broadcast->id, array_map('intval', $this->selectedBroadcasts)) ? 'bg-muted/50' : '' }}" >             
                    
                    <x-ui.table-cell class="w-8">
                        <x-checkbox wire:model.live="selectedBroadcasts" value="{{ $broadcast->id }}" />
                    </x-ui.table-cell>
                    
                    <x-ui.table-cell class="text-xs text-muted-foreground/70 whitespace-nowrap">
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
                                        @if($broadcast->targetUser->status === 'banned')
                                            <span class="text-destructive font-bold text-base leading-none" title="Статус: Забанен. Причина: {{ $broadcast->targetUser->ban_reason ?? 'не указана' }}">
                                                !
                                            </span>
                                        @elseif($broadcast->targetUser->status === 'shadowbanned')
                                            <span class="text-yellow-500 font-bold text-base leading-none" title="Статус: Теневой бан. Причина: {{ $broadcast->targetUser->ban_reason ?? 'не указана' }}">
                                                !
                                            </span>
                                        @endif
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
                                    <span class="text-sm font-medium group-hover:text-primary transition-colors">
                                        {{ $broadcast->admin->name }}
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