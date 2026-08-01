<?php

use App\Models\AdminLog;
use App\Models\FraudAlert;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    public string $statusFilter = 'open'; // По умолчанию смотрим открытые
    public string $severityFilter = 'all';
    public string $triggerFilter = 'all';
    public string $search = '';
    public int $perPage = 15;

    // Модалка доказательств
    public bool $showMetaModal = false;
    public ?int $viewingAlertId = null;

    // Маппинг триггеров для UI
    private array $triggerLabels = [
        'same_device' => 'Одно устройство',
        'mass_messaging' => 'Масс-спам',
        'links_in_chat' => 'Ссылки в чате',
        'prostitute' => 'Проституция',
        'scam' => 'Мошенничество',
        'stop_word_alert' => 'Стоп-слово (Тихая)',
    ];

    public function mount(): void
    {
        // Если переходим с дашборда по алерту, сразу фильтруем открытые
        $this->statusFilter = request('status', 'open');
    }

    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedSeverityFilter(): void { $this->resetPage(); }
    public function updatedTriggerFilter(): void { $this->resetPage(); }
    public function updatedSearch(): void { $this->resetPage(); }

    /**
     * Подтвердить нарушение (забанить юзера - ручной бан, если не сработал автомат).
     */
    public function resolveAlert(int $id): void
    {
        $alert = FraudAlert::find($id);
        if ($alert && $alert->isOpen()) {
            $alert->resolve(auth()->id());
            
            // Опционально: забанить юзера прямо отсюда
            // $alert->user?->update(['status' => 'banned', 'ban_reason' => 'Fraud: ' . $alert->trigger_type]);

            AdminLog::record('fraud.resolve', $alert, auth()->user());
            $this->dispatch('show-toast', type: 'success', message: 'Алерт подтвержден. Нарушитель наказан.');
        }
    }

    /**
     * Отметить как ложное срабатывание (простить юзера).
     */
    public function markFalsePositive(int $id): void
    {
        $alert = FraudAlert::find($id);
        if ($alert && $alert->isOpen()) {
            $alert->markAsFalsePositive(auth()->id());
            
            AdminLog::record('fraud.false_positive', $alert, auth()->user());
            $this->dispatch('show-toast', type: 'info', message: 'Отмечено как ложное срабатывание.');
        }
    }

    /**
     * Быстрый бан юзера из таблицы.
     */
    public function banUser(int $alertId): void
    {
        $alert = FraudAlert::find($alertId);
        if ($alert && $alert->user) {
            $alert->user->update([
                'status' => 'banned',
                'ban_reason' => 'Антифрод: ' . ($this->triggerLabels[$alert->trigger_type] ?? $alert->trigger_type),
            ]);
            
            // Автоматически закрываем алерт
            if ($alert->isOpen()) {
                $alert->resolve(auth()->id());
            }

            AdminLog::record('user.ban', $alert->user, auth()->user(), ['status' => 'active'], ['status' => 'banned']);
            $this->dispatch('show-toast', type: 'success', message: 'Пользователь забанен!');
        }
    }

    public function viewMeta(int $id): void
    {
        $this->viewingAlertId = $id;
        $this->showMetaModal = true;
    }

    #[Computed]
    public function alerts()
    {
        $query = FraudAlert::with(['user', 'admin']);

        $query->when($this->statusFilter !== 'all', fn($q) => $q->where('status', $this->statusFilter));
        $query->when($this->severityFilter !== 'all', fn($q) => $q->where('severity', $this->severityFilter));
        $query->when($this->triggerFilter !== 'all', fn($q) => $q->where('trigger_type', $this->triggerFilter));

        if (!empty($this->search)) {
            $search = strtolower($this->search);
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', fn($q) => $q->where('name', 'like', "%{$search}%"))
                  ->orWhere('trigger_type', 'like', "%{$search}%");
            });
        }

               // Сначала высокие, потом средние, потом новые (Вариант для PostgreSQL)
        return $query->orderByRaw("CASE WHEN severity = 'high' THEN 1 WHEN severity = 'medium' THEN 2 WHEN severity = 'low' THEN 3 ELSE 4 END")
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);
    }

    #[Computed]
    public function counts(): array
    {
        return [
            'open' => FraudAlert::where('status', 'open')->count(),
            'resolved' => FraudAlert::where('status', 'resolved')->count(),
            'false_positive' => FraudAlert::where('status', 'false_positive')->count(),
            'high' => FraudAlert::open()->where('severity', 'high')->count(),
        ];
    }
}; 
?>

<div class="space-y-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <h1 class="text-2xl font-semibold flex items-center gap-2">
            <x-lucide-siren class="w-6 h-6 text-destructive" />
            Антифрод мониторинг
            @if($this->counts['high'] > 0)
                <x-ui.badge variant="destructive" size="lg" class="animate-pulse">{{ $this->counts['high'] }} CRITICAL</x-ui.badge>
            @endif
        </h1>
    </div>

    <!-- Фильтры -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-1.5">
            <x-ui.button wire:click="$set('statusFilter', 'open')" variant="{{ $statusFilter === 'open' ? 'default' : 'secondary' }}" size="sm">
                Открытые <x-ui.badge size="xs" variant="warning">{{ $this->counts['open'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="$set('statusFilter', 'resolved')" variant="{{ $statusFilter === 'resolved' ? 'default' : 'secondary' }}" size="sm">
                Подтвержденные <x-ui.badge size="xs" variant="success">{{ $this->counts['resolved'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="$set('statusFilter', 'false_positive')" variant="{{ $statusFilter === 'false_positive' ? 'default' : 'secondary' }}" size="sm">
                Ложные <x-ui.badge size="xs">{{ $this->counts['false_positive'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="$set('statusFilter', 'all')" variant="{{ $statusFilter === 'all' ? 'default' : 'secondary' }}" size="sm">
                Все
            </x-ui.button>
        </div>

        <div class="flex items-center gap-2">
            <x-ui.select wire:model.live="severityFilter" class="min-w-36">
                <x-ui.select-trigger><x-ui.select-value placeholder="Угроза" /></x-ui.select-trigger>
                <x-ui.select-content>
                    <x-ui.select-item value="all">Все уровни</x-ui.select-item>
                    <x-ui.select-item value="high">🔴 Высокий</x-ui.select-item>
                    <x-ui.select-item value="medium">🟡 Средний</x-ui.select-item>
                    <x-ui.select-item value="low">🟢 Низкий</x-ui.select-item>
                </x-ui.select-content>
            </x-ui.select>

            <x-ui.select wire:model.live="triggerFilter" class="min-w-44">
                <x-ui.select-trigger><x-ui.select-value placeholder="Тип триггера" /></x-ui.select-trigger>
                <x-ui.select-content>
                    <x-ui.select-item value="all">Все триггеры</x-ui.select-item>
                    @foreach($this->triggerLabels as $key => $label)
                        <x-ui.select-item value="{{ $key }}">{{ $label }}</x-ui.select-item>
                    @endforeach
                </x-ui.select-content>
            </x-ui.select>

            <div class="relative w-64">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск по юзеру..." class="pl-9 pr-8" />
                <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            </div>
        </div>
    </div>

    <!-- Таблица -->
    <x-ui.table>
        <x-ui.table-header>
            <x-ui.table-row>
                <x-ui.table-head>Подозреваемый</x-ui.table-head>
                <x-ui.table-head>Триггер</x-ui.table-head>
                <x-ui.table-head>Угроза</x-ui.table-head>
                <x-ui.table-head>Статус</x-ui.table-head>
                <x-ui.table-head>Дата</x-ui.table-head>
                <x-ui.table-head class="text-right">Действия</x-ui.table-row>
            </x-ui.table-row>
        </x-ui.table-header>

        <x-ui.table-body>
            @forelse ($this->alerts as $alert)
                <x-ui.table-row wire:key="alert-{{ $alert->id }}" class="{{ $alert->severity === 'high' && $alert->isOpen() ? 'bg-red-500/5 border-l-4 border-l-red-500' : '' }}">
                    <!-- Подозреваемый -->
                    <x-ui.table-cell>
                        @if($alert->user)
                            <div class="flex items-center gap-2">
                                <x-avatar src="{{ $alert->user->avatar_url }}" name="{{ $alert->user->name }}" size="sm" />
                                <div>
                                    <div class="text-sm font-medium">{{ $alert->user->name }}</div>
                                    <div class="text-xs text-muted-foreground">ID: {{ $alert->user_id }}</div>
                                </div>
                            </div>
                        @else
                            <span class="text-xs text-muted-foreground">Удален</span>
                        @endif
                    </x-ui.table-cell>

                    <!-- Триггер -->
                    <x-ui.table-cell>
                        <div class="text-sm font-medium">{{ $this->triggerLabels[$alert->trigger_type] ?? $alert->trigger_type }}</div>
                        <button wire:click="viewMeta({{ $alert->id }})" class="text-xs text-primary hover:underline flex items-center gap-1 mt-0.5">
                            <x-lucide-eye class="w-3 h-3" /> Доказательства
                        </button>
                    </x-ui.table-cell>

                    <!-- Угроза -->
                    <x-ui.table-cell>
                        <x-ui.badge variant="{{ $alert->severity_badge['variant'] }}" size="sm">
                            {{ $alert->severity_badge['label'] }}
                        </x-ui.badge>
                    </x-ui.table-cell>

                    <!-- Статус -->
                    <x-ui.table-cell>
                        <x-ui.badge variant="{{ $alert->status_badge['variant'] }}" size="sm">
                            {{ $alert->status_badge['label'] }}
                        </x-ui.badge>
                        @if($alert->admin)
                            <div class="text-xs text-muted-foreground mt-1">Админ: {{ $alert->admin->name }}</div>
                        @endif
                    </x-ui.table-cell>

                    <!-- Дата -->
                    <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap">
                        {{ $alert->created_at->format('d.m.Y H:i') }}
                    </x-ui.table-cell>

                    <!-- Действия -->
                    <x-ui.table-cell class="text-right">
                        @if($alert->isOpen())
                            <div class="flex items-center justify-end gap-1">
                                @if($alert->user && $alert->user->status !== 'banned')
                                    <x-ui.tooltip>
                                        <x-ui.tooltip-trigger>
                                            <x-ui.button wire:click="banUser({{ $alert->id }})" variant="destructive" size="icon-sm">
                                                <x-lucide-hammer class="w-4 h-4" />
                                            </x-ui.button>
                                        </x-ui.tooltip-trigger>
                                        <x-ui.tooltip-content>Забанить</x-ui.tooltip-content>
                                    </x-ui.tooltip>
                                @endif

                                <x-ui.tooltip>
                                    <x-ui.tooltip-trigger>
                                        <x-ui.button wire:click="resolveAlert({{ $alert->id }})" variant="success" size="icon-sm" class="bg-green-500/10 text-green-500 hover:bg-green-500/20">
                                            <x-lucide-check class="w-4 h-4" />
                                        </x-ui.button>
                                    </x-ui.tooltip-trigger>
                                    <x-ui.tooltip-content>Подтвердить (Без бана)</x-ui.tooltip-content>
                                </x-ui.tooltip>

                                <x-ui.tooltip>
                                    <x-ui.tooltip-trigger>
                                        <x-ui.button wire:click="markFalsePositive({{ $alert->id }})" variant="outline" size="icon-sm">
                                            <x-lucide-shield-check class="w-4 h-4" />
                                        </x-ui.button>
                                    </x-ui.tooltip-trigger>
                                    <x-ui.tooltip-content>Ложняк (Простить)</x-ui.tooltip-content>
                                </x-ui.tooltip>
                            </div>
                        @else
                            <span class="text-xs text-muted-foreground">Разобрано</span>
                        @endif
                    </x-ui.table-cell>
                </x-ui.table-row>
            @empty
                <x-ui.table-row>
                    <x-ui.table-cell colspan="6" class="py-12 text-center text-muted-foreground">
                        <div class="flex flex-col items-center gap-2">
                            <x-lucide-shield-check class="w-12 h-12 opacity-30" />
                            <p>Алерты не найдены</p>
                            @if($statusFilter === 'open')
                                <p class="text-xs text-green-500">Все чисто! Нет подозрительных активностей.</p>
                            @endif
                        </div>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @endforelse
        </x-ui.table-body>
    </x-ui.table>

    <!-- Пагинация -->
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div class="text-xs text-muted-foreground">
            Показано {{ $this->alerts->firstItem() ?? 0 }} - {{ $this->alerts->lastItem() ?? 0 }} из {{ $this->alerts->total() }}
        </div>
        {{ $this->alerts->links('partials.pagination') }}
    </div>

    <!-- МОДАЛКА ДОКАЗАТЕЛЬСТВ (META) -->
    <div x-show="$wire.showMetaModal" x-cloak @click.self="$wire.showMetaModal = false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" x-transition @keydown.escape.window="$wire.showMetaModal = false">
        <div class="relative bg-card border border-border rounded-lg shadow-2xl max-w-2xl w-full mx-4 overflow-hidden">
            <div class="flex items-center justify-between p-4 border-b border-border">
                <h2 class="text-lg font-semibold">Доказательства (Meta)</h2>
                <button @click="$wire.showMetaModal = false" class="text-muted-foreground hover:text-foreground"><x-lucide-x class="w-5 h-5" /></button>
            </div>

            <div class="p-6">
                @php $alert = FraudAlert::find($viewingAlertId); @endphp
                @if($alert)
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <x-ui.badge variant="{{ $alert->severity_badge['variant'] }}" size="sm">{{ $alert->severity_badge['label'] }}</x-ui.badge>
                            <span class="text-sm font-medium">{{ $this->triggerLabels[$alert->trigger_type] ?? $alert->trigger_type }}</span>
                        </div>
                        
                        <div class="bg-muted/30 rounded-lg p-4 max-h-[60vh] overflow-y-auto little-scroll">
                            <pre class="text-xs text-muted-foreground whitespace-pre-wrap font-mono">{{ json_encode($alert->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>

                        <p class="text-xs text-muted-foreground">
                            Этот JSON сформирован автоматически воркером при срабатывании триггера.
                        </p>
                    </div>
                @endif
            </div>

            <div class="flex items-center justify-end gap-2 p-4 border-t border-border bg-muted/20">
                <x-ui.button @click="$wire.showMetaModal = false" variant="outline" size="sm">Закрыть</x-ui.button>
            </div>
        </div>
    </div>
</div>