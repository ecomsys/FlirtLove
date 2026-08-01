<?php

use App\Models\AdminLog;
use App\Models\Broadcast;
use App\Models\User;
use App\Notifications\BroadcastNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    public array $selectedBroadcasts = [];
    public bool $selectAll = false;
    public ?string $dateFrom = null;
    public ?string $dateTo = null;
    public string $search = '';
    public string $statusFilter = 'all';
    public string $typeFilter = 'all';
    public int $perPage = 10;

    // Поля формы
    public string $modalTitle = '';
    public string $modalType = 'in_app'; // in_app, push, email
    public string $modalMessage = '';
    public string $targetAudienceType = 'all'; // Сегмент: all, premium, male, female
    public string $scheduledDate = '';
    public bool $showCreateModal = false;
    public ?int $editingId = null;
    public bool $showEditModal = false;

    public function mount()
    {
        $saved = session('moderate_broadcasts', []);
        if (isset($saved['statusFilter'])) $this->statusFilter = $saved['statusFilter'];
        if (isset($saved['typeFilter'])) $this->typeFilter = $saved['typeFilter'];
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }   
    public function updatingDateFrom(): void { $this->resetPage(); }
    public function updatingDateTo(): void { $this->resetPage(); }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        session(['moderate_broadcasts' => array_merge(session('moderate_broadcasts', []), ['statusFilter' => $status])]);
        $this->resetPage();
    }

    public function updatedTypeFilter($value): void 
    { 
        session(['moderate_broadcasts' => array_merge(session('moderate_broadcasts', []), ['typeFilter' => $value])]);
        $this->resetPage(); 
    }

    public function toggleSelectAll(): void
    {
        if ($this->selectAll) {
            $this->selectedBroadcasts = $this->broadcasts->getCollection()->pluck('id')->toArray();
        } else {
            $this->selectedBroadcasts = [];
        }
    }

    // ============================================
    // ДЕЙСТВИЯ
    // ============================================

    public function deleteSelected(): void
    {
        if (empty($this->selectedBroadcasts)) {
            $this->dispatch('show-toast', type: 'info', message: 'Не выбрано ни одной рассылки.');
            return;
        }

        $count = count($this->selectedBroadcasts);
        Broadcast::whereIn('id', $this->selectedBroadcasts)->delete();

        $this->selectedBroadcasts = [];
        $this->selectAll = false;
        $this->dispatch('show-toast', type: 'success', message: "Удалено {$count} рассылок.");
    }

    public function createModal(): void
    {
        $this->reset(['modalTitle', 'modalType', 'modalMessage', 'targetAudienceType', 'scheduledDate', 'editingId']);
        $this->resetValidation(); 
        $this->showCreateModal = true;
    }

    public function editModal(int $id): void
    {
        $broadcast = Broadcast::find($id);
        if ($broadcast && in_array($broadcast->status, ['draft', 'scheduled'])) {
            $this->editingId = $id;
            $this->modalTitle = $broadcast->title;
            $this->modalType = $broadcast->type;
            $this->modalMessage = $broadcast->message;
            $this->targetAudienceType = $broadcast->target_audience['type'] ?? 'all';
            $this->scheduledDate = $broadcast->scheduled_at?->format('Y-m-d\TH:i') ?? '';
            $this->resetValidation();
            $this->showEditModal = true;
        }
    }

    public function updateBroadcast(): void
    {
        $this->validate($this->rules());

        $broadcast = Broadcast::find($this->editingId);
        if (!$broadcast) return;

        $currentStatus = $broadcast->status;
        $status = !empty($this->scheduledDate) ? 'scheduled' : 'draft';

        $broadcast->update([
            'type' => $this->modalType,
            'title' => $this->modalTitle,
            'message' => $this->modalMessage,
            'target_audience' => $this->buildTargetAudience(),
            'status' => $status,
            'scheduled_at' => $this->scheduledDate ?: null,
        ]);

        AdminLog::record('broadcast.update', $broadcast, auth()->user());

        $this->dispatch('show-toast', type: 'success', message: 'Рассылка обновлена!');
        $this->showEditModal = false;
    }

    public function sendBroadcast(): void
    {
        $this->validate($this->rules());

        $status = !empty($this->scheduledDate) ? 'scheduled' : 'sending';

        $broadcast = Broadcast::create([
            'admin_id' => auth()->id(),
            'type' => $this->modalType,
            'title' => $this->modalTitle,
            'message' => $this->modalMessage,
            'target_audience' => $this->buildTargetAudience(),
            'status' => $status,
            'scheduled_at' => $this->scheduledDate ?: null,
        ]);

        AdminLog::record('broadcast.create', $broadcast, auth()->user());

        if ($status === 'sending') {
            $this->dispatchRealBroadcast($broadcast);
        }

        $this->dispatch('show-toast', type: 'success', message: $this->scheduledDate ? 'Рассылка запланирована!' : 'Рассылка запущена!');
        $this->showCreateModal = false;
    }

    public function sendNow(int $id): void
    {
        $broadcast = Broadcast::find($id);
        if ($broadcast && $broadcast->status === 'scheduled') {
            $this->dispatchRealBroadcast($broadcast);
            AdminLog::record('broadcast.send_now', $broadcast, auth()->user());
            $this->dispatch('show-toast', type: 'success', message: 'Рассылка запущена прямо сейчас');
        }
    }

    public function duplicateBroadcast(int $id): void
    {
        $broadcast = Broadcast::find($id);
        if ($broadcast) {
            $new = $broadcast->replicate();
            $new->status = 'draft';
            $new->sent_at = null;
            $new->scheduled_at = null;
            $new->sent_count = 0;
            $new->failed_count = 0;
            $new->total_recipients = 0;
            $new->started_at = null;
            $new->save();

            $this->dispatch('show-toast', type: 'success', message: 'Рассылка продублирована');
        }
    }

    public function deleteBroadcast(int $id): void
    {
        $broadcast = Broadcast::find($id);
        if ($broadcast) {
            $broadcast->delete();
            $this->dispatch('show-toast', type: 'success', message: 'Рассылка удалена');
        }
    }

    // ============================================
    // ЯДРО ОТПРАВКИ (Адаптировано под новую модель)
    // ============================================

    private function dispatchRealBroadcast(Broadcast $broadcast): void
    {
        try {
            $query = $this->getTargetQuery($broadcast->target_audience);
            $totalRecipients = $query->count();

            if ($totalRecipients === 0) {
                $broadcast->markAsSent(); // Нет юзеров - считаем выполнено
                return;
            }

            $broadcast->markAsSending($totalRecipients);

            $query->select(['id', 'name', 'email'])->chunk(500, function ($users) use ($broadcast) {
                try {
                    Notification::send($users, new BroadcastNotification($broadcast));
                    $broadcast->increment('sent_count', $users->count());
                } catch (\Exception $e) {
                    Log::error('Broadcast chunk failed', ['broadcast_id' => $broadcast->id, 'error' => $e->getMessage()]);
                    $broadcast->increment('failed_count', $users->count());
                }
            });

            $broadcast->markAsSent();

        } catch (\Exception $e) {
            Log::error('Broadcast failed entirely', ['broadcast_id' => $broadcast->id, 'error' => $e->getMessage()]);
            $broadcast->markAsFailed();
        }
    }

    /**
     * Формируем запрос юзеров на основе JSON target_audience
     */
    private function getTargetQuery(array $targetAudience): \Illuminate\Database\Eloquent\Builder
    {
        $query = User::query();

        // Базовое правило: не отправлять админам и забаненным
        $query->where('role', 'user')->where('status', 'active');

        $type = $targetAudience['type'] ?? 'all';

        switch ($type) {
            case 'premium':
                $query->where('is_premium', true)->where('premium_expires_at', '>', now());
                break;
            case 'male':
                // Предполагаем, что пол хранится в таблице user_profiles
                $query->whereHas('profile', fn($q) => $q->where('gender', 'male'));
                break;
            case 'female':
                $query->whereHas('profile', fn($q) => $q->where('gender', 'female'));
                break;
            case 'all':
            default:
                // Всем подходящим юзерам
                break;
        }

        return $query;
    }

    /**
     * Формируем JSON для сохранения
     */
    private function buildTargetAudience(): array
    {
        return ['type' => $this->targetAudienceType];
    }

    protected function rules(): array
    {
        return [
            'modalTitle' => 'required|string|max:255',
            'modalMessage' => 'required|string|max:5000',
            'modalType' => 'required|in:in_app,push,email',
            'targetAudienceType' => 'required|in:all,premium,male,female',
            'scheduledDate' => 'nullable|date|after_or_equal:now|before_or_equal:' . now()->addYear()->toDateTimeString(),
        ];
    }

    // ============================================
    // ВЫВОД ДАННЫХ
    // ============================================

    #[Computed]
    public function broadcasts()
    {
        return Broadcast::query()
            ->with('admin:id,name')
            ->when($this->search, function ($query) {
                $search = $this->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('message', 'like', "%{$search}%");
                });
            })
            ->when($this->statusFilter !== 'all', fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter !== 'all', fn($q) => $q->where('type', $this->typeFilter))
            ->when($this->dateFrom, fn($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->latest()
            ->paginate($this->perPage);
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

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'typeFilter', 'dateFrom', 'dateTo']);
        $this->statusFilter = 'all';
        $this->typeFilter = 'all';
        session()->forget('moderate_broadcasts');
        $this->resetPage();
    }
}; 
?>

<div class="space-y-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <h1 class="text-2xl font-semibold flex items-center gap-2">
            <x-lucide-radio class="w-6 h-6" />
            Рассылки
            @if ($this->counts['draft'] > 0)
                <x-ui.badge variant="warning" size="sm">{{ $this->counts['draft'] }} черновиков</x-ui.badge>
            @endif
        </h1>

        <x-ui.button wire:click="createModal" variant="default" size="sm">
            <x-lucide-plus class="w-4 h-4" />
            Создать рассылку
        </x-ui.button>
    </div>

    <!-- Фильтры и Действия -->
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
                            Будут удалены <strong>{{ count($selectedBroadcasts) }}</strong> рассылок. Это действие нельзя отменить.
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

    <!-- Статусы -->
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

    <!-- Таблица -->
    <x-ui.table>
        <x-ui.table-header>
            <x-ui.table-row>
                <x-ui.table-head class="w-8"><x-checkbox wire:model.live="selectAll" wire:change="toggleSelectAll" /></x-ui.table-head>
                <x-ui.table-head>Рассылка</x-ui.table-head>
                <x-ui.table-head>Тип</x-ui.table-head>
                <x-ui.table-head>Аудитория</x-ui.table-head>
                <x-ui.table-head>Статус / Прогресс</x-ui.table-head>
                <x-ui.table-head>Автор</x-ui.table-head>
                <x-ui.table-head>Дата</x-ui.table-head>
                <x-ui.table-head class="text-right">Действия</x-ui.table-row>
            </x-ui.table-row>
        </x-ui.table-header>

        <x-ui.table-body>
            @forelse ($this->broadcasts as $broadcast)
                <x-ui.table-row wire:key="broadcast-row-{{ $broadcast->id }}">
                    <x-ui.table-cell class="w-8">
                        <x-checkbox wire:model.live="selectedBroadcasts" value="{{ $broadcast->id }}" />
                    </x-ui.table-cell>
                    <x-ui.table-cell class="max-w-[25rem] whitespace-normal">
                        <div class="font-medium text-sm line-clamp-1">{{ $broadcast->title }}</div>
                        <div class="text-xs text-muted-foreground line-clamp-1">{{ Str::limit($broadcast->message, 60) }}</div>
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        @if ($broadcast->type === 'in_app')
                            <x-ui.badge variant="secondary" size="xs"><x-lucide-bell class="w-3 h-3 inline mr-1" />В приложении</x-ui.badge>
                        @elseif($broadcast->type === 'email')
                            <x-ui.badge variant="warning" size="xs"><x-lucide-mail class="w-3 h-3 inline mr-1" />Email</x-ui.badge>
                        @else
                            <x-ui.badge variant="info" size="xs"><x-lucide-smartphone class="w-3 h-3 inline mr-1" />Push</x-ui.badge>
                        @endif
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        @php
                            $audienceType = $broadcast->target_audience['type'] ?? 'all';
                            $audienceLabel = match($audienceType) {
                                'premium' => 'VIP пользователи',
                                'male' => 'Мужчины',
                                'female' => 'Женщины',
                                default => 'Все пользователи'
                            };
                        @endphp
                        <span class="text-sm">{{ $audienceLabel }}</span>
                    </x-ui.table-cell>
                    <x-ui.table-cell class="w-48">
                        @if ($broadcast->status === 'draft')
                            <x-ui.badge variant="warning" size="sm">Черновик</x-ui.badge>
                        @elseif($broadcast->status === 'scheduled')
                            <x-ui.badge variant="info" size="sm">Запланировано</x-ui.badge>
                            <div class="text-xs text-muted-foreground mt-1">{{ $broadcast->scheduled_at->format('d.m.Y H:i') }}</div>
                        @elseif($broadcast->status === 'sending')
                            <div class="space-y-1">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-blue-500 font-medium">Отправка...</span>
                                    <span>{{ $broadcast->progress }}%</span>
                                </div>
                                <div class="w-full bg-muted rounded-full h-1.5">
                                    <div class="bg-blue-500 h-1.5 rounded-full transition-all" style="width: {{ $broadcast->progress }}%"></div>
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ $broadcast->sent_count }} из {{ $broadcast->total_recipients }}
                                </div>
                            </div>
                        @elseif($broadcast->status === 'sent')
                            <x-ui.badge variant="success" size="sm">Отправлено</x-ui.badge>
                            <div class="text-xs text-muted-foreground mt-1">
                                {{ $broadcast->sent_count }} доставлено / {{ $broadcast->failed_count }} ошибок
                            </div>
                        @elseif($broadcast->status === 'failed')
                            <x-ui.badge variant="destructive" size="sm">Ошибка</x-ui.badge>
                            <div class="text-xs text-muted-foreground mt-1">
                                Упало: {{ $broadcast->failed_count }}
                            </div>
                        @endif
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        <span class="text-sm">{{ $broadcast->admin?->name ?? 'Система' }}</span>
                    </x-ui.table-cell>
                    <x-ui.table-cell class="text-muted-foreground text-xs whitespace-nowrap">
                        {{ $broadcast->created_at->format('d.m.Y H:i') }}
                    </x-ui.table-cell>
                    <x-ui.table-cell class="text-right">
                        <x-ui.dropdown-menu>
                            <x-ui.dropdown-menu-trigger>
                                <x-ui.button variant="ghost" size="icon-sm"><x-lucide-more-horizontal class="w-4 h-4" /></x-ui.button>
                            </x-ui.dropdown-menu-trigger>
                            <x-ui.dropdown-menu-content align="end">
                                @if (in_array($broadcast->status, ['draft', 'scheduled']))
                                    <x-ui.dropdown-menu-item wire:click="editModal({{ $broadcast->id }})">
                                        <x-lucide-pencil class="w-4 h-4" /> Редактировать
                                    </x-ui.dropdown-menu-item>
                                    <x-ui.dropdown-menu-item wire:click="duplicateBroadcast({{ $broadcast->id }})">
                                        <x-lucide-copy class="w-4 h-4" /> Дублировать
                                    </x-ui.dropdown-menu-item>
                                @endif

                                @if ($broadcast->status === 'scheduled')
                                    <x-ui.dropdown-menu-item wire:click="sendNow({{ $broadcast->id }})">
                                        <x-lucide-send class="w-4 h-4" /> Отправить сейчас
                                    </x-ui.dropdown-menu-item>
                                @endif

                                @if (!in_array($broadcast->status, ['sending']))
                                    <x-ui.dropdown-menu-item variant="destructive" wire:click="deleteBroadcast({{ $broadcast->id }})" wire:confirm="Удалить рассылку?">
                                        <x-lucide-trash-2 class="w-4 h-4" /> Удалить
                                    </x-ui.dropdown-menu-item>
                                @endif
                            </x-ui.dropdown-menu-content>
                        </x-ui.dropdown-menu>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @empty
                <x-ui.table-row>
                    <x-ui.table-cell colspan="8" class="py-12 text-center text-muted-foreground">
                        <div class="flex flex-col items-center gap-2">
                            <x-lucide-radio class="w-12 h-12 opacity-30" />
                            <p>Нет рассылок</p>
                        </div>
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

    {{-- МОДАЛКА СОЗДАТЬ --}}
    <div x-show="$wire.showCreateModal" x-cloak @click.self="$wire.showCreateModal = false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" @keydown.escape.window="$wire.showCreateModal = false">
        <div class="relative bg-card border border-border rounded-lg shadow-2xl max-w-2xl w-full mx-4 overflow-hidden">
            <div class="flex items-center justify-between p-4 border-b border-border">
                <h2 class="text-lg font-semibold">Создать рассылку</h2>
                <button @click="$wire.showCreateModal = false" class="text-muted-foreground hover:text-foreground"><x-lucide-x class="w-5 h-5" /></button>
            </div>

            <div class="p-6 space-y-4">
                <div class="flex flex-col gap-2">
                    <x-ui.label for="c-title">Заголовок</x-ui.label>
                    <x-ui.input id="c-title" wire:model="modalTitle" placeholder="Заголовок уведомления" />
                    @error('modalTitle') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-2">
                    <x-ui.label for="c-msg">Сообщение</x-ui.label>
                    <x-ui.textarea :rows="4" id="c-msg" wire:model="modalMessage" placeholder="Текст уведомления" class="resize-none little-scroll" />
                    @error('modalMessage') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <x-ui.label for="c-type">Тип</x-ui.label>
                        <x-ui.select wire:model="modalType">
                            <x-ui.select-trigger><x-ui.select-value /></x-ui.select-trigger>
                            <x-ui.select-content>
                                <x-ui.select-item value="in_app">В приложении</x-ui.select-item>
                                <x-ui.select-item value="email">Email</x-ui.select-item>
                                <x-ui.select-item value="push">Push</x-ui.select-item>
                            </x-ui.select-content>
                        </x-ui.select>
                        @error('modalType') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex flex-col gap-2">
                        <x-ui.label for="c-audience">Кому</x-ui.label>
                        <x-ui.select wire:model="targetAudienceType">
                            <x-ui.select-trigger><x-ui.select-value /></x-ui.select-trigger>
                            <x-ui.select-content>
                                <x-ui.select-item value="all">Всем пользователям</x-ui.select-item>
                                <x-ui.select-item value="premium">VIP (Премиум)</x-ui.select-item>
                                <x-ui.select-item value="male">Мужчинам</x-ui.select-item>
                                <x-ui.select-item value="female">Женщинам</x-ui.select-item>
                            </x-ui.select-content>
                        </x-ui.select>
                        @error('targetAudienceType') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <x-ui.label for="c-date">Запланировать отправку (макс. +1 год)</x-ui.label>
                    <x-ui.input id="c-date" wire:model="scheduledDate" type="datetime-local" max="{{ now()->addYear()->format('Y-m-d\TH:i') }}" />
                    @error('scheduledDate') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 p-4 border-t border-border bg-muted/20">
                <x-ui.button @click="$wire.showCreateModal = false" variant="outline" size="sm">Отмена</x-ui.button>
                <x-ui.button wire:click="sendBroadcast" variant="default" size="sm">
                    <x-lucide-send class="w-4 h-4 inline" /> Отправить
                </x-ui.button>
            </div>
        </div>
    </div>

    {{-- МОДАЛКА РЕДАКТИРОВАНИЯ --}}
    <div x-show="$wire.showEditModal" x-cloak @click.self="$wire.showEditModal = false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" @keydown.escape.window="$wire.showEditModal = false">
        <div class="relative bg-card border border-border rounded-lg shadow-2xl max-w-2xl w-full mx-4 overflow-hidden">
            <div class="flex items-center justify-between p-4 border-b border-border">
                <h2 class="text-lg font-semibold">Редактировать рассылку</h2>
                <button @click="$wire.showEditModal = false" class="text-muted-foreground hover:text-foreground"><x-lucide-x class="w-5 h-5" /></button>
            </div>

            <div class="p-6 space-y-4">
                <div class="flex flex-col gap-2">
                    <x-ui.label for="e-title">Заголовок</x-ui.label>
                    <x-ui.input id="e-title" wire:model="modalTitle" />
                    @error('modalTitle') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-2">
                    <x-ui.label for="e-msg">Сообщение</x-ui.label>
                    <x-ui.textarea :rows="4" id="e-msg" wire:model="modalMessage" class="resize-none little-scroll" />
                    @error('modalMessage') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <x-ui.label>Тип</x-ui.label>
                        <x-ui.select wire:model="modalType">
                            <x-ui.select-trigger><x-ui.select-value /></x-ui.select-trigger>
                            <x-ui.select-content>
                                <x-ui.select-item value="in_app">В приложении</x-ui.select-item>
                                <x-ui.select-item value="email">Email</x-ui.select-item>
                                <x-ui.select-item value="push">Push</x-ui.select-item>
                            </x-ui.select-content>
                        </x-ui.select>
                    </div>

                    <div class="flex flex-col gap-2">
                        <x-ui.label>Кому</x-ui.label>
                        <x-ui.select wire:model="targetAudienceType">
                            <x-ui.select-trigger><x-ui.select-value /></x-ui.select-trigger>
                            <x-ui.select-content>
                                <x-ui.select-item value="all">Всем пользователям</x-ui.select-item>
                                <x-ui.select-item value="premium">VIP (Премиум)</x-ui.select-item>
                                <x-ui.select-item value="male">Мужчинам</x-ui.select-item>
                                <x-ui.select-item value="female">Женщинам</x-ui.select-item>
                            </x-ui.select-content>
                        </x-ui.select>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <x-ui.label for="e-date">Запланировать отправку</x-ui.label>
                    <x-ui.input id="e-date" wire:model="scheduledDate" type="datetime-local" />
                    @error('scheduledDate') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 p-4 border-t border-border bg-muted/20">
                <x-ui.button @click="$wire.showEditModal = false" variant="outline" size="sm">Отмена</x-ui.button>
                <x-ui.button wire:click="updateBroadcast" variant="default" size="sm">
                    <x-lucide-save class="w-4 h-4 inline" /> Сохранить
                </x-ui.button>
            </div>
        </div>
    </div>
</div>