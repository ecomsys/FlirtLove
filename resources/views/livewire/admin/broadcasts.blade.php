<?php

use App\Models\Broadcast;
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\Notification;
use App\Notifications\BroadcastNotification;
use Illuminate\Validation\ValidationException;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    public array $selectedBroadcasts = [];
    public bool $selectAll = false;

    public ?string $dateFrom = null;
    public ?string $dateTo = null;

    // Фильтры и поиск
    public string $search = '';
    public string $statusFilter = 'all';
    public string $typeFilter = 'all';
    public int $perPage = 10;

    // Поля формы создания/редактирования
    public string $modalTitle = '';
    public string $modalType = 'system';
    public string $modalMessage = '';
    public ?int $selectedUserId = null;
    public string $scheduledDate = '';
    public bool $showCreateModal = false;

    // Редактирование
    public ?int $editingId = null;
    public bool $showEditModal = false;

    /**
     * Проверка прав администратора
     */
    private function checkAdminAccess(): void
    {
        if (!auth()->user()?->is_admin) {
            abort(403, 'Доступ запрещен. Только для администраторов.');
        }
    }

    /**
     * Сброс пагинации при обновлении поиска
     */
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

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
    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    /**
     * Переключение "Выбрать все"
     */
    public function toggleSelectAll(): void
    {
        if ($this->selectAll) {
            $this->selectedBroadcasts = $this->broadcasts->pluck('id')->toArray();
        } else {
            $this->selectedBroadcasts = [];
        }
    }

    /**
     * Массовое удаление
     */
    public function deleteSelected(): void
    {
        $this->checkAdminAccess();

        if (empty($this->selectedBroadcasts)) {
            $this->dispatch('show-toast', type: 'info', message: 'Не выбрано ни одного оповещения.');
            return;
        }

        $count = count($this->selectedBroadcasts);
        Broadcast::whereIn('id', $this->selectedBroadcasts)->delete();

        $this->selectedBroadcasts = [];
        $this->selectAll = false;

        $this->dispatch('show-toast', type: 'success', message: "Удалено {$count} оповещений.");
        $this->dispatch('$refresh');
    }

    /**
     * Открыть модалку создания оповещения
     */
    public function createModal(): void
    {
        $this->checkAdminAccess();
        $this->reset(['modalTitle', 'modalType', 'modalMessage', 'selectedUserId', 'scheduledDate', 'editingId']);
        $this->showCreateModal = true;
    }

    /**
     * Открыть модалку редактирования оповещения
     */
    public function editModal(int $id): void
    {
        $this->checkAdminAccess();
        $broadcast = Broadcast::find($id);
        if ($broadcast) {
            $this->editingId = $id;
            $this->modalTitle = $broadcast->title;
            $this->modalType = $broadcast->type;
            $this->modalMessage = $broadcast->message;
            $this->selectedUserId = $broadcast->user_id;
            $this->scheduledDate = $broadcast->scheduled_at?->format('Y-m-d\TH:i') ?? '';
            $this->showEditModal = true;
        }
    }

    /**
     * Обновить существующее оповещение
     */
    public function updateBroadcast(): void
    {
        $this->checkAdminAccess();
        
        // Livewire сам обработает ошибку и покажет @error в Blade
        $this->validate([
            'modalTitle' => 'required|string|max:255',
            'modalMessage' => 'required|string|max:5000',
            'modalType' => 'required|in:system,email,push',
            'scheduledDate' => 'nullable|date|after_or_equal:now|before:now + 1 year',
        ]);

        $broadcast = Broadcast::find($this->editingId);
        if ($broadcast) {
            $currentStatus = $broadcast->status;

            if ($this->scheduledDate) {
                $status = 'scheduled';
            } elseif ($currentStatus === 'sent') {
                $status = 'sent'; 
            } else {
                $status = 'draft'; 
            }

            DB::transaction(function () use ($broadcast, $status, $currentStatus) {
                $broadcast->update([
                    'user_id' => $this->selectedUserId,
                    'type' => $this->modalType,
                    'title' => $this->modalTitle,
                    'message' => $this->modalMessage,
                    'status' => $status,
                    'scheduled_at' => $this->scheduledDate ?: null,
                    'sent_at' => $status === 'sent' && $currentStatus !== 'sent' ? now() : $broadcast->sent_at,
                ]);

                if ($status === 'sent' && $currentStatus !== 'sent') {
                    $this->sendRealBroadcast($broadcast);
                }
            });

            $this->dispatch('show-toast', type: 'success', message: 'Оповещение обновлено!');
            $this->showEditModal = false;
            $this->reset(['modalTitle', 'modalMessage', 'selectedUserId', 'scheduledDate', 'editingId']);
            $this->dispatch('$refresh');
        }
    }

    /**
     * Создать и отправить новое оповещение
     */
    public function sendBroadcast(): void
    {
        $this->checkAdminAccess();
        
        // Livewire сам обработает ошибку и покажет @error в Blade
        $this->validate([
            'modalTitle' => 'required|string|max:255',
            'modalMessage' => 'required|string|max:5000',
            'modalType' => 'required|in:system,email,push',
            'scheduledDate' => 'nullable|date|after_or_equal:now|before:now + 1 year',
        ]);

        DB::transaction(function () {
            $status = $this->scheduledDate ? 'scheduled' : 'sent';
            $broadcast = Broadcast::create([
                'user_id' => $this->selectedUserId,
                'type' => $this->modalType,
                'title' => $this->modalTitle,
                'message' => $this->modalMessage,
                'status' => $status,
                'scheduled_at' => $this->scheduledDate ?: null,
                'sent_at' => $status === 'sent' ? now() : null,
            ]);

            if ($status === 'sent') {
                $this->sendRealBroadcast($broadcast);
            }

            Cache::forget('admin_dashboard_metrics');
        });

        $this->dispatch('show-toast', type: 'success', message: $this->scheduledDate ? 'Оповещение запланировано!' : 'Оповещение отправлено!');
        $this->showCreateModal = false;
        $this->reset(['modalTitle', 'modalMessage', 'selectedUserId', 'scheduledDate']);
        $this->dispatch('$refresh');
    }

    /**
     * Реальная отправка оповещения (Email, Push, System)
     */
    private function sendRealBroadcast($broadcast): void
    {
        $query = $broadcast->user_id === null 
            ? User::where('is_banned', false) 
            : User::where('id', $broadcast->user_id);

        $sentCount = 0;

        // Обрабатываем порциями по 500 пользователей
        $query->select(['id', 'name', 'email'])->chunk(500, function ($users) use ($broadcast, &$sentCount) {
            Notification::send($users, new BroadcastNotification($broadcast));
            $sentCount += $users->count();
        });

        Log::info('Оповещение отправлено', [
            'broadcast_id' => $broadcast->id,
            'type' => $broadcast->type,
            'recipient_type' => $broadcast->user_id === null ? 'all' : 'single',
            'sent_count' => $sentCount,
            'admin_id' => auth()->id(),
        ]);
    }

    /**
     * Отправить запланированное оповещение сейчас
     */
    public function sendNow(int $id): void
    {
        $this->checkAdminAccess();
        $broadcast = Broadcast::find($id);
        if ($broadcast && $broadcast->status === 'scheduled') {
            DB::transaction(function () use ($broadcast) {
                $broadcast->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'scheduled_at' => null,
                ]);
                $this->sendRealBroadcast($broadcast);
            });
            $this->dispatch('show-toast', type: 'success', message: 'Оповещение отправлено');
            $this->dispatch('$refresh');
        }
    }

    /**
     * Создать копию существующего оповещения
     */
    public function duplicateBroadcast(int $id): void
    {
        $this->checkAdminAccess();
        $original = Broadcast::find($id);
        if ($original) {
            $new = $original->replicate();
            $new->status = 'draft';
            $new->sent_at = null;
            $new->scheduled_at = null;
            $new->created_at = now();
            $new->save();

            $this->dispatch('show-toast', type: 'success', message: 'Оповещение продублировано');
            $this->dispatch('$refresh');
        }
    }

    /**
     * Удалить оповещение
     */
    public function deleteBroadcast(int $id): void
    {
        $this->checkAdminAccess();
        $broadcast = Broadcast::find($id);
        if ($broadcast) {
            $broadcast->delete();
            $this->dispatch('show-toast', type: 'success', message: 'Оповещение удалено');
            $this->dispatch('$refresh');
        }
    }

    /**
     * Получить список уведомлений с пагинацией и фильтрацией
     */
    #[Computed]
    public function broadcasts()
    {
        return Broadcast::query()
            ->with('user:id,name,email')
            ->select('id', 'user_id', 'type', 'title', 'message', 'status', 'scheduled_at', 'sent_at', 'created_at')
            ->when($this->search, function ($query) {
                $search = $this->search;
                $query->where('title', 'like', "%{$search}%")->orWhere('message', 'like', "%{$search}%");
            })
            ->when($this->statusFilter !== 'all', function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->typeFilter !== 'all', function ($query) {
                $query->where('type', $this->typeFilter);
            })
            ->when($this->dateFrom, function ($query) {
                $query->whereDate('created_at', '>=', $this->dateFrom);
            })
            ->when($this->dateTo, function ($query) {
                $query->whereDate('created_at', '<=', $this->dateTo);
            })
            ->latest()
            ->paginate($this->perPage);
    }

    /**
     * Получить статистику по статусам уведомлений
     */
    #[Computed]
    public function counts()
    {
        $counts = Broadcast::select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status')->toArray();

        return [
            'draft' => $counts['draft'] ?? 0,
            'sent' => $counts['sent'] ?? 0,
            'scheduled' => $counts['scheduled'] ?? 0,
            'total' => array_sum($counts),
        ];
    }

    /**
     * Получить список пользователей для комбобокса (с кешированием)
     */
    #[Computed]
    public function users()
    {
        return Cache::remember('admin_users_list', 600, function () {
            $users = User::orderBy('name')->get(['id', 'name']);
            $options = [['value' => '', 'label' => 'Всем пользователям']];
            foreach ($users as $user) {
                $options[] = ['value' => $user->id, 'label' => ' (ID: ' . $user->id . ') ' . $user->name];
            }
            return $options;
        });
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->typeFilter = 'all';
        $this->dateFrom = null;
        $this->dateTo = null;
        $this->resetPage();
    }
};
?>
<!-- ======== HTML / BLADE VIEW ======== -->
<div class="space-y-6">

    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <h1 class="text-2xl font-semibold flex items-center gap-2">
            Оповещения пользователей
            @if ($this->counts['draft'] > 0)
                <x-ui.badge variant="warning" size="sm" wire:key="draft-badge-{{ $this->counts['draft'] }}">
                    {{ $this->counts['draft'] }} черновиков
                </x-ui.badge>
            @endif
        </h1>

        <x-ui.button wire:click="createModal" variant="default" size="sm" wire:key="btn-create-broadcast">
            <x-lucide-plus class="w-4 h-4" />
            Создать оповещение
        </x-ui.button>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">

        {{--  ДОБАВЛЕНЫ wire:key для условных блоков, чтобы Livewire не глючил при переходе от 0 к 1 выбранному --}}
        @if (count($selectedBroadcasts) > 0)
            <div wire:key="delete-selected-active">
                <x-ui.alert-dialog>
                    <x-ui.alert-dialog-trigger>
                        <x-ui.button variant="destructive" size="sm" wire:loading.attr="disabled"
                            wire:target="deleteSelected" class="gap-2">
                            <span wire:loading.remove wire:target="deleteSelected">
                                <x-lucide-trash-2 class="w-4 h-4 inline" />
                                Удалить выбранные
                                <x-ui.badge variant="warning" size="xs">{{ count($selectedBroadcasts) }}</x-ui.badge>
                            </span>
                            <span wire:loading wire:target="deleteSelected">
                                <x-ui.spinner class="w-4 h-4 inline" />
                                Удаление...
                            </span>
                        </x-ui.button>
                    </x-ui.alert-dialog-trigger>
                    <x-ui.alert-dialog-content>
                        <x-ui.alert-dialog-header>
                            <x-ui.alert-dialog-title>Удалить выбранные оповещения?</x-ui.alert-dialog-title>
                            <x-ui.alert-dialog-description>
                                Вы уверены? Будут удалены <strong>{{ count($selectedBroadcasts) }}</strong> оповещений.
                                <br><strong class="text-destructive">Это действие нельзя отменить.</strong>
                            </x-ui.alert-dialog-description>
                        </x-ui.alert-dialog-header>
                        <x-ui.alert-dialog-footer>
                            <x-ui.alert-dialog-cancel>Отмена</x-ui.alert-dialog-cancel>
                            <x-ui.alert-dialog-action wire:click="deleteSelected">
                                <x-lucide-trash-2 class="w-4 h-4" />
                                Удалить
                            </x-ui.alert-dialog-action>
                        </x-ui.alert-dialog-footer>
                    </x-ui.alert-dialog-content>
                </x-ui.alert-dialog>
            </div>
        @else
            <div wire:key="delete-selected-inactive">
                <x-ui.button variant="secondary" size="sm" class="gap-2 opacity-50 cursor-not-allowed">
                    <x-lucide-trash-2 class="w-4 h-4 inline" />
                    Не выбрано
                    <x-ui.badge size="xs">0</x-ui.badge>
                </x-ui.button>
            </div>
        @endif

        <div class="flex items-center gap-2 self-end">
            <x-ui.button wire:click="resetFilters" variant="outline" size="sm" wire:key="btn-reset-filters">
                <x-lucide-rotate-ccw class="w-4 h-4" />
                Сбросить фильтры
            </x-ui.button>

            <x-ui.select wire:model.live="typeFilter" wire:key="select-type-filter-{{ $typeFilter }}" class="min-w-40">
                <x-ui.select-trigger>
                    <x-ui.select-value placeholder="Тип" />
                </x-ui.select-trigger>
                <x-ui.select-content>
                    <x-ui.select-item value="all">Все типы</x-ui.select-item>
                    <x-ui.select-item value="system">Системные</x-ui.select-item>
                    <x-ui.select-item value="email">Email</x-ui.select-item>
                    <x-ui.select-item value="push">Push</x-ui.select-item>
                </x-ui.select-content>
            </x-ui.select>

            <div class="flex items-center gap-2">
                <div class="flex items-center gap-2">
                    <x-ui.date-picker wire:model.live="dateFrom" placeholder="с" width="w-[10rem]" wire:key="date-from" />
                    <span class="text-muted-foreground">—</span>
                    <x-ui.date-picker wire:model.live="dateTo" placeholder="по" width="w-[10rem]" wire:key="date-to" />
                </div>
            </div>

            <div class="relative w-full max-w-64" wire:key="search-wrapper-{{ $search }}">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск по названию..."
                    class="pl-9 pr-8" />
                <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                @if (!empty($search))
                    <button wire:click="$set('search', '')"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Фильтры статусов -->
    <div class="flex flex-wrap gap-1.5">
        <x-ui.button wire:click="setStatusFilter('all')" wire:key="filter-all-{{ $statusFilter === 'all' ? 'active' : 'inactive' }}"
            variant="{{ $statusFilter === 'all' ? 'default' : 'secondary' }}" size="sm">
            Все
            <x-ui.badge size="xs">{{ $this->counts['total'] }}</x-ui.badge>
        </x-ui.button>

        <x-ui.button wire:click="setStatusFilter('draft')" wire:key="filter-draft-{{ $statusFilter === 'draft' ? 'active' : 'inactive' }}"
            variant="{{ $statusFilter === 'draft' ? 'default' : 'secondary' }}" size="sm">
            Черновики
            <x-ui.badge size="xs" variant="warning">{{ $this->counts['draft'] }}</x-ui.badge>
        </x-ui.button>

        <x-ui.button wire:click="setStatusFilter('scheduled')" wire:key="filter-scheduled-{{ $statusFilter === 'scheduled' ? 'active' : 'inactive' }}"
            variant="{{ $statusFilter === 'scheduled' ? 'default' : 'secondary' }}" size="sm">
            Запланированы
            <x-ui.badge size="xs" variant="info">{{ $this->counts['scheduled'] }}</x-ui.badge>
        </x-ui.button>

        <x-ui.button wire:click="setStatusFilter('sent')" wire:key="filter-sent-{{ $statusFilter === 'sent' ? 'active' : 'inactive' }}"
            variant="{{ $statusFilter === 'sent' ? 'default' : 'secondary' }}" size="sm">
            Отправлены
            <x-ui.badge size="xs" variant="success">{{ $this->counts['sent'] }}</x-ui.badge>
        </x-ui.button>
    </div>

    <!-- Таблица уведомлений -->
    <x-ui.table>
        <x-ui.table-header>
            <x-ui.table-row>
                <x-ui.table-head class="w-8">
                   <x-checkbox                        
                        wire:model.live="selectAll" 
                        wire:change="toggleSelectAll" 
                        variant="primary" 
                        size="md" 
                        wire:key="checkbox-select-all"
                    />
                </x-ui.table-head>
                <x-ui.table-head class="w-12">ID</x-ui.table-head>
                <x-ui.table-head>Название</x-ui.table-head>
                <x-ui.table-head>Тип</x-ui.table-head>
                <x-ui.table-head>Статус</x-ui.table-head>
                <x-ui.table-head>Кому</x-ui.table-head>
                <x-ui.table-head>Дата создания</x-ui.table-head>
                <x-ui.table-head class="text-right">Действия</x-ui.table-head>
            </x-ui.table-row>
        </x-ui.table-header>

        <x-ui.table-body>
            @forelse ($this->broadcasts as $broadcast)
                {{--  ГЛАВНЫЙ КЛЮЧ ДЛЯ СТРОКИ ТАБЛИЦЫ --}}
                <x-ui.table-row wire:key="broadcast-row-{{ $broadcast->id }}">
                    <x-ui.table-cell class="w-8">
                        <x-checkbox wire:model.live="selectedBroadcasts" value="{{ $broadcast->id }}" variant="primary"
                            wire:key="checkbox-item-{{ $broadcast->id }}" />
                    </x-ui.table-cell>
                    <x-ui.table-cell class="text-muted-foreground text-xs">{{ $broadcast->id }}</x-ui.table-cell>
                    <x-ui.table-cell class="max-w-[25rem] whitespace-normal">
                        <div class="min-w-0">
                            <div class="font-medium text-sm line-clamp-1" title="{{ $broadcast->title }}">
                                {{ $broadcast->title }}
                            </div>
                            <div class="text-xs text-muted-foreground line-clamp-1"
                                title="{{ $broadcast->message }}">
                                {{ Str::limit($broadcast->message, 60) }}
                            </div>
                        </div>
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        @if ($broadcast->type === 'system')
                            <x-ui.badge variant="secondary" size="xs">
                                <x-lucide-monitor class="w-3 h-3 inline mr-1" />
                                Системное
                            </x-ui.badge>
                        @elseif($broadcast->type === 'email')
                            <x-ui.badge variant="warning" size="xs">
                                <x-lucide-mail class="w-3 h-3 inline mr-1" />
                                Email
                            </x-ui.badge>
                        @else
                            <x-ui.badge variant="info" size="xs">
                                <x-lucide-smartphone class="w-3 h-3 inline mr-1" />
                                Push
                            </x-ui.badge>
                        @endif
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        @if ($broadcast->status === 'draft')
                            <x-ui.badge variant="warning" size="sm">Черновик</x-ui.badge>
                        @elseif($broadcast->status === 'sent')
                            <x-ui.badge variant="success" size="sm">Отправлено</x-ui.badge>
                        @elseif ($broadcast->status === 'scheduled')
                            <x-ui.tooltip>
                                <x-ui.tooltip-trigger>
                                    <x-ui.badge variant="info" size="sm" class="cursor-help">
                                        Запланировано
                                    </x-ui.badge>
                                </x-ui.tooltip-trigger>
                                <x-ui.tooltip-content class="bg-secondary text-secondary-foreground">
                                    <div x-data="{
                                        scheduledAt: '{{ $broadcast->scheduled_at->toIso8601String() }}',
                                        timer: null,
                                        get timeLeft() {
                                            let diff = new Date(this.scheduledAt) - new Date();
                                            if (diff <= 0) return 'Отправляется...';
                                            let h = Math.floor(diff / 3600000);
                                            let m = Math.floor((diff % 3600000) / 60000);
                                            let s = Math.floor((diff % 60000) / 1000);
                                            return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                                        },
                                        get dateFormatted() {
                                            let d = new Date(this.scheduledAt);
                                            return d.toLocaleDateString('ru-RU') + ' ' + d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
                                        },
                                        init() {
                                            this.updateTimer();
                                            this.timer = setInterval(() => this.updateTimer(), 1000);
                                        },
                                        updateTimer() {
                                            let el = this.$el.querySelector('.countdown');
                                            if (el) el.innerText = this.timeLeft;
                                        },
                                        destroy() {
                                            if (this.timer) clearInterval(this.timer);
                                        }
                                    }" x-init="init()" class="text-center">
                                        <div class="font-medium" x-text="dateFormatted"></div>
                                        <div class="text-xs text-muted-foreground countdown" x-text="timeLeft"></div>
                                    </div>
                                </x-ui.tooltip-content>
                            </x-ui.tooltip>
                        @endif
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        @if ($broadcast->user_id)
                            <div class="flex items-center gap-2">
                                <x-avatar src="{{ $broadcast->user->avatar_url }}"
                                    name="{{ $broadcast->user->name }}" size="sm"
                                    userId="{{ $broadcast->user->id }}" showStatus="true" />
                                <div>
                                    <div class="font-medium text-sm">{{ $broadcast->user->name ?? 'Пользователь' }}</div>
                                    <div class="text-xs text-muted-foreground">{{ $broadcast->user->email }}</div>
                                </div>
                            </div>
                        @else
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                                    <x-lucide-users class="w-4 h-4" />
                                </div>
                                <div>
                                    <div class="font-medium text-sm">Всем пользователям</div>
                                    <div class="text-xs text-muted-foreground">Массовая рассылка</div>
                                </div>
                            </div>
                        @endif
                    </x-ui.table-cell>
                    <x-ui.table-cell class="text-muted-foreground text-xs">
                        {{ $broadcast->created_at->format('d.m.Y H:i') }}
                    </x-ui.table-cell>
                    <x-ui.table-cell class="text-right">
                        {{--  КЛЮЧ ДЛЯ DROPDOWN, ЧТОБЫ LIVWIRE НЕ ПУТАЛ ДЕЙСТВИЯ ПРИ ФИЛЬТРАЦИИ --}}
                        <x-ui.dropdown-menu wire:key="dropdown-menu-{{ $broadcast->id }}">
                            <x-ui.dropdown-menu-trigger>
                                <x-ui.button variant="ghost" size="icon-sm">
                                    <x-lucide-more-horizontal class="w-4 h-4" />
                                </x-ui.button>
                            </x-ui.dropdown-menu-trigger>
                            <x-ui.dropdown-menu-content align="end">
                                @if ($broadcast->status === 'draft' || $broadcast->status === 'scheduled')
                                    <x-ui.dropdown-menu-item wire:key="action-edit-{{ $broadcast->id }}" wire:click="editModal({{ $broadcast->id }})">
                                        <x-lucide-pencil class="w-4 h-4" />
                                        Редактировать
                                    </x-ui.dropdown-menu-item>

                                    <x-ui.dropdown-menu-item wire:key="action-duplicate-{{ $broadcast->id }}" wire:click="duplicateBroadcast({{ $broadcast->id }})">
                                        <x-lucide-copy class="w-4 h-4" />
                                        Дублировать
                                    </x-ui.dropdown-menu-item>
                                @endif

                                @if ($broadcast->status === 'scheduled')
                                    <x-ui.dropdown-menu-item wire:key="action-sendnow-{{ $broadcast->id }}" wire:click="sendNow({{ $broadcast->id }})">
                                        <x-lucide-send class="w-4 h-4" />
                                        Отправить сейчас
                                    </x-ui.dropdown-menu-item>
                                @endif

                                <x-ui.dropdown-menu-item wire:key="action-delete-{{ $broadcast->id }}" variant="destructive"
                                    wire:click="deleteBroadcast({{ $broadcast->id }})">
                                    <x-lucide-trash-2 class="w-4 h-4" />
                                    Удалить
                                </x-ui.dropdown-menu-item>
                            </x-ui.dropdown-menu-content>
                        </x-ui.dropdown-menu>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @empty
                <x-ui.table-row wire:key="empty-state-row">
                    <x-ui.table-cell colspan="8" class="py-12 text-center text-muted-foreground">
                        <div class="flex flex-col items-center gap-2">
                            <x-lucide-bell-off class="w-12 h-12 opacity-30" />
                            <p>Нет уведомлений</p>
                        </div>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @endforelse
        </x-ui.table-body>
    </x-ui.table>

    <!-- Пагинация -->
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div class="text-xs text-muted-foreground">
            Показано {{ $this->broadcasts->firstItem() ?? 0 }} - {{ $this->broadcasts->lastItem() ?? 0 }} из
            {{ $this->broadcasts->total() }}
        </div>
        {{ $this->broadcasts->links('partials.pagination') }}
    </div>

    {{-- МОДАЛКА СОЗДАТЬ оповещение --}}
    <div x-show="$wire.showCreateModal" x-cloak wire:key="modal-create-wrapper"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100" @keydown.escape.window="$wire.showCreateModal = false">
        <div class="relative bg-card border border-border rounded-lg shadow-2xl max-w-2xl w-full mx-4 overflow-hidden">
            <div class="flex items-center justify-between p-4 border-b border-border">
                <h2 class="text-lg font-semibold">Создать оповещение</h2>
                <button @click="$wire.showCreateModal = false" class="text-muted-foreground hover:text-foreground transition-colors">
                    <x-lucide-x class="w-5 h-5" />
                </button>
            </div>

            <div class="p-6 space-y-4">
                <div class="flex flex-col gap-3">
                    <x-ui.label for="modalTitle">Заголовок</x-ui.label>
                    <x-ui.input id="modalTitle" wire:model="modalTitle" placeholder="Введите заголовок оповещения" class="w-full" />
                </div>

                <div class="flex flex-col gap-3">
                    <x-ui.label for="modalMessage">Сообщение</x-ui.label>
                    <x-ui.textarea :rows="4" :max-rows="6" id="modalMessage" wire:model="modalMessage"
                        placeholder="Введите текст оповещения" class="w-full resize-none little-scroll" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-3">
                        <x-ui.label for="modalType">Тип оповещения</x-ui.label>
                        {{--  ДОБАВЛЕН КЛЮЧ ДЛЯ COMBOBOX --}}
                        <x-ui.combobox wire:model.live="modalType" wire:key="create-combobox-type" 
                            placeholder="Выберите тип..." searchPlaceholder="Выберите тип..." width="w-full" empty="Нет типа." :options="[
                                ['value' => 'system', 'label' => 'Системное'],
                                ['value' => 'email', 'label' => 'Email'],
                                ['value' => 'push', 'label' => 'Push'],
                            ]" />
                    </div>

                    <div class="flex flex-col gap-3">
                        @php
                            $selectedLabel = collect($this->users)->firstWhere('value', $selectedUserId)['label'] ?? 'Не выбран';
                        @endphp

                        <x-ui.label for="createUser">Получатель: <span class="text-primary">{{ $selectedLabel }}</span></x-ui.label>
                        {{--  ДОБАВЛЕН КЛЮЧ ДЛЯ COMBOBOX С УЧЕТОМ ВЫБРАННОГО ЗНАЧЕНИЯ --}}
                        <x-ui.combobox wire:model.live="selectedUserId" wire:key="create-combobox-user-{{ $selectedUserId ?? 'none' }}" 
                            placeholder="Выберите получателя" searchPlaceholder="Поиск пользователя..." empty="Пользователь не найден" width="w-full"
                            :options="$this->users" />
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <x-ui.label for="scheduledDate">Запланировать отправку (макс. +1 год)</x-ui.label>
                    <x-ui.input id="scheduledDate" wire:model="scheduledDate" type="datetime-local" 
                        max="{{ now()->addYear()->format('Y-m-d\TH:i') }}" class="w-full text-foreground" />
                         {{-- ДОБАВЛЯЕМ СЮДА ОШИБКУ ВАЛИДАЦИИ --}}
                    @error('scheduledDate')
                        <p class="text-xs text-destructive flex items-center gap-1">
                            <x-lucide-alert-circle class="w-3 h-3" />
                            {{ $message }}
                        </p>
                    @enderror
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 p-4 border-t border-border bg-muted/20">
                <x-ui.button @click="$wire.showCreateModal = false" variant="outline" size="sm">
                    Отмена
                </x-ui.button>
                <x-ui.button wire:click="sendBroadcast" wire:loading.attr="disabled" wire:target="sendBroadcast"
                    variant="default" size="sm">
                    <span wire:loading.remove wire:target="sendBroadcast">
                        <x-lucide-send class="w-4 h-4 inline" />
                        Отправить
                    </span>
                    <span wire:loading wire:target="sendBroadcast">
                        <x-ui.spinner class="w-4 h-4 inline" />
                        Отправка...
                    </span>
                </x-ui.button>
            </div>
        </div>
    </div>

    {{-- МОДАЛКА РЕДАКТИРОВАНИЯ --}}
    <div x-show="$wire.showEditModal" x-cloak wire:key="modal-edit-wrapper-{{ $editingId ?? 'new' }}"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100" @keydown.escape.window="$wire.showEditModal = false">
        <div class="relative bg-card border border-border rounded-lg shadow-2xl max-w-2xl w-full mx-4 overflow-hidden">
            <div class="flex items-center justify-between p-4 border-b border-border">
                <h2 class="text-lg font-semibold">Редактировать оповещение</h2>
                <button @click="$wire.showEditModal = false" class="text-muted-foreground hover:text-foreground transition-colors">
                    <x-lucide-x class="w-5 h-5" />
                </button>
            </div>

            <div class="p-6 space-y-4">
                <div class="flex flex-col gap-3">
                    <x-ui.label for="editTitle">Заголовок</x-ui.label>
                    <x-ui.input id="editTitle" wire:model="modalTitle" placeholder="Введите заголовок оповещения" class="w-full" />
                </div>

                <div class="flex flex-col gap-3">
                    <x-ui.label for="editMessage">Сообщение</x-ui.label>
                    <x-ui.textarea :rows="4" :max-rows="6" id="editMessage" wire:model="modalMessage"
                        placeholder="Введите текст оповещения" class="w-full resize-none little-scroll" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-3">
                        <x-ui.label for="editType">Тип оповещения</x-ui.label>
                        <x-ui.combobox wire:model.live="modalType" wire:key="edit-combobox-type-{{ $editingId }}"
                            placeholder="Выберите тип..." searchPlaceholder="Выберите тип..." width="w-full"
                            empty="Нет типа." :options="[
                                ['value' => 'system', 'label' => 'Системное'],
                                ['value' => 'email', 'label' => 'Email'],
                                ['value' => 'push', 'label' => 'Push'],
                            ]" />
                    </div>

                    <div class="flex flex-col gap-3">
                        @php
                            $selectedLabel = collect($this->users)->firstWhere('value', $selectedUserId)['label'] ?? 'Не выбран';
                        @endphp

                        <x-ui.label for="editUser">Получатель: <span class="text-primary">{{ $selectedLabel }}</span></x-ui.label>
                        <x-ui.combobox wire:model.live="selectedUserId" wire:key="edit-combobox-user-{{ $editingId }}-{{ $selectedUserId ?? 'none' }}" 
                            placeholder="Выберите получателя" searchPlaceholder="Поиск пользователя..." empty="Пользователь не найден" width="w-full"
                            :options="$this->users" />
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <x-ui.label for="editDate">Запланировать отправку (макс. +1 год)</x-ui.label>
                    <x-ui.input id="editDate" wire:model="scheduledDate" type="datetime-local" class="w-full text-foreground"  
                        max="{{ now()->addYear()->format('Y-m-d\TH:i') }}" />
                      {{-- ДОБАВЛЯЕМ СЮДА ОШИБКУ ВАЛИДАЦИИ --}}
                    @error('scheduledDate')
                        <p class="text-xs text-destructive flex items-center gap-1">
                            <x-lucide-alert-circle class="w-3 h-3" />
                            {{ $message }}
                        </p>
                    @enderror
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 p-4 border-t border-border bg-muted/20">
                <x-ui.button @click="$wire.showEditModal = false" variant="outline" size="sm">
                    Отмена
                </x-ui.button>
                <x-ui.button wire:click="updateBroadcast" wire:loading.attr="disabled" wire:target="updateBroadcast"
                    variant="default" size="sm">
                    <span wire:loading.remove wire:target="updateBroadcast">
                        <x-lucide-save class="w-4 h-4 inline" />
                        Сохранить
                    </span>
                    <span wire:loading wire:target="updateBroadcast">
                        <x-ui.spinner class="w-4 h-4 inline" />
                        Сохранение...
                    </span>
                </x-ui.button>
            </div>
        </div>
    </div>
</div>