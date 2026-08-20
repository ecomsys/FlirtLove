<?php

use App\Actions\Admin\FraudAlertsAction;
use App\Enums\FraudAlertSeverity;
use App\Enums\FraudAlertStatus;
use App\Models\FraudAlert;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Session;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    #[Url(as: 'q', except: '')] 
    #[Session] 
    public string $search = '';
    #[Session] 
    public string $statusFilter = 'all';
    #[Session] 
    public string $severityFilter = 'all';
    #[Session] 
    public int $perPage = 20;

    public int $filterVersion = 0;

     public function mount(): void
    {
        // Сбрасываем на "Все" ТОЛЬКО если ID пришел через URL (?q=123)
        if (is_numeric($this->search) && $this->search !== '') {
            $this->statusFilter = 'all';
        }
    }

    public function updatedSearch(): void 
    { 
        // Просто сбрасываем страницу. Фильтр статуса не трогаем!
        $this->resetPage(); 
    }

    public function updatedSeverityFilter(): void { $this->resetPage(); }

    public function setStatusFilter(string $status): void 
    { 
        $this->statusFilter = $status; 
        $this->search = ''; // Очищаем поиск при переключении вкладок
        $this->resetPage(); 
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->severityFilter = 'all';
        $this->resetPage();
        $this->filterVersion++;
    }

    #[Computed]
    public function alerts()
    {
        $searchOperator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

        return FraudAlert::query()
            ->with(['user.photos', 'user', 'admin:id,name']) 
            ->when($this->search, function ($q) use ($searchOperator) {
                $q->whereHas('user', function ($q) use ($searchOperator) {
                    $q->where('name', $searchOperator, "%{$this->search}%")
                      ->orWhere('email', $searchOperator, "%{$this->search}%");
                })->orWhereRaw("CAST(id AS TEXT) {$searchOperator} ?", ["%{$this->search}%"]);
            })
            ->when($this->statusFilter !== 'all', fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->severityFilter !== 'all', fn($q) => $q->where('severity', $this->severityFilter))
            ->latest('created_at')
            ->latest('id')
            ->paginate(min(max($this->perPage, 10), 100));
    }

    #[Computed]
    public function counts(): array
    {
        $stats = FraudAlert::query()
            ->selectRaw("COUNT(*) as total")
            ->selectRaw("SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open")
            ->selectRaw("SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved")
            ->selectRaw("SUM(CASE WHEN status = 'false_positive' THEN 1 ELSE 0 END) as false_positive")
            ->selectRaw("SUM(CASE WHEN severity = 'high' AND status = 'open' THEN 1 ELSE 0 END) as high_priority")
            ->first();

        return [
            'total' => $stats->total ?? 0,
            'open' => $stats->open ?? 0,
            'resolved' => $stats->resolved ?? 0,
            'false_positive' => $stats->false_positive ?? 0,
            'high_priority' => $stats->high_priority ?? 0,
        ];
    }

    public function resolveAndBan(int $id, string $type = 'permanent', FraudAlertsAction $action): void
    {
        try {
            $action->resolveAndBan($id, $type);
            $this->dispatch('show-toast', type: 'success', message: 'Действие применено, алерт закрыт!');
        } catch (\Exception $e) {
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера!');
        }
    }

    public function markAsFalsePositive(int $id, FraudAlertsAction $action): void
    {
        try {
            $action->markAsFalsePositive($id);
            $this->dispatch('show-toast', type: 'success', message: 'Отмечено как ложняк.');
        } catch (\Exception $e) {
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера!');
        }
    }
}; 
?>

<div class="space-y-6 pb-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.dashboard') }}" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-semibold flex items-center gap-2">
                    <x-lucide-siren class="w-6 h-6 text-destructive" />
                    Антифрод-мониторинг
                </h1>
                <p class="text-sm text-muted-foreground mt-1">
                    Высокий приоритет: <span class="text-destructive font-bold">{{ $this->counts['high_priority'] }}</span>
                </p>
            </div>
        </div>
    </div>

    <!-- ФИЛЬТРЫ (Кнопки статусов) -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-1.5 items-center">
            <x-ui.button wire:click="setStatusFilter('all')" variant="{{ $statusFilter === 'all' ? 'default' : 'secondary' }}" size="sm">
                Все <x-ui.badge size="xs">{{ $this->counts['total'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="setStatusFilter('open')" variant="{{ $statusFilter === 'open' ? 'default' : 'secondary' }}" size="sm">
                Открытые <x-ui.badge size="xs" variant="warning">{{ $this->counts['open'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="setStatusFilter('resolved')" variant="{{ $statusFilter === 'resolved' ? 'default' : 'secondary' }}" size="sm">
                Подтверждены <x-ui.badge size="xs" variant="success">{{ $this->counts['resolved'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="setStatusFilter('false_positive')" variant="{{ $statusFilter === 'false_positive' ? 'default' : 'secondary' }}" size="sm">
                Ложняки <x-ui.badge size="xs" variant="secondary">{{ $this->counts['false_positive'] }}</x-ui.badge>
            </x-ui.button>

            <div class="mx-2 h-6 border-l border-border"></div>

            <!-- Фильтр опасности остался селектом -->
            <x-ui.select wire:key="sev-filter-{{ $filterVersion }}" wire:model.live="severityFilter">
                <x-ui.select-trigger class="w-50"><x-ui.select-value placeholder="Опасность" /></x-ui.select-trigger>
                <x-ui.select-content>
                    <x-ui.select-item value="all">Любая опасность</x-ui.select-item>
                    @foreach(\App\Enums\FraudAlertSeverity::options() as $value => $label)
                        <x-ui.select-item wire:key="sev-opt-{{ $value }}" value="{{ $value }}">{{ $label }}</x-ui.select-item>
                    @endforeach
                </x-ui.select-content>
            </x-ui.select>

            @if($search || $statusFilter !== 'all' || $severityFilter !== 'all')
                <x-ui.button wire:click="clearFilters" variant="ghost" size="sm" class="text-muted-foreground">
                    <x-lucide-x class="w-4 h-4" /> Сбросить
                </x-ui.button>
            @endif
        </div>

        <div class="relative w-64">
            <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск по имени, email или id..." class="pl-9" />
            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            @if (!empty($search))
                <button wire:click="$set('search', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
                    <x-lucide-x class="w-4 h-4" />
                </button>
            @endif
        </div>
    </div>

    <!-- ТАБЛИЦА -->
    <x-ui.table>
        <x-ui.table-header>
            <x-ui.table-row>
                <x-ui.table-head class="w-10">ID</x-ui.table-head>
                <x-ui.table-head>Кто нарушил</x-ui.table-head>
                <x-ui.table-head>Триггер</x-ui.table-head>
                <x-ui.table-head>Опасность</x-ui.table-head>
                <x-ui.table-head>Статус</x-ui.table-head>
                <x-ui.table-head>Дата</x-ui.table-head>
                <x-ui.table-head class="text-right">Действия</x-ui.table-head>
            </x-ui.table-row>
        </x-ui.table-header>

        <x-ui.table-body>
            @forelse ($this->alerts as $alert)
                @php 
                    $isHighlighted = is_numeric($this->search) && $alert->id === (int)$this->search;
                    $isOpen = $alert->status === \App\Enums\FraudAlertStatus::Open || $alert->status === 'open';
                    $isHighSeverity = $alert->severity === \App\Enums\FraudAlertSeverity::High || $alert->severity === 'high';
                @endphp
                <x-ui.table-row 
                    wire:key="alert-{{ $alert->id }}-status-{{ $alert->status }}" 
                    class="{{ $isHighlighted ? 'bg-primary/10 ring-2 ring-primary/50 transition-all duration-500' : '' }} {{ $isOpen && $isHighSeverity ? 'bg-destructive/5' : '' }}"
                    x-data="{ isHi: {{ $isHighlighted ? 'true' : 'false' }} }"
                    x-init="isHi && $nextTick(() => { $el.scrollIntoView({ behavior: 'smooth', block: 'center' }) })"
                >
                    <x-ui.table-cell class="text-xs font-mono whitespace-nowrap {{ $isHighlighted ? 'text-primary font-bold' : 'text-muted-foreground' }}">
                        #{{ $alert->id }}
                    </x-ui.table-cell>
                    
                    <!-- КОЛОНКА ЮЗЕРА (Узкая) -->
                    <x-ui.table-cell class="max-w-[180px]">
                        @if($alert->user)
                        <a href="{{ route('admin.users.show', $alert->user->id) }}" class="flex gap-2 items-center group" wire:navigate>
                            <x-avatar src="{{ $alert->user->avatar_url }}" name="{{  $alert->user->name }}" size="sm" userId="{{  $alert->user->id }}" showStatus="true" :isOnline="$alert->user->is_online"/>                            
                            <div class="block min-w-0">
                                <div class="font-medium text-sm text-foreground flex items-center gap-1.5 group-hover:text-primary transition-colors truncate">
                                    <x-user-status-sign :user="$alert->user" />
                                    <span class="truncate">{{ $alert->user->name }}</span>                                
                                    @if($alert->user->has_active_premium)<x-lucide-crown class="w-3.5 h-3.5 text-yellow-500 shrink-0" />@endif                              
                                    @if($alert->user->is_verified)<x-lucide-badge-check class="w-3.5 h-3.5 text-blue-500 shrink-0" />@endif
                                </div>
                                <div class="text-xs text-muted-foreground truncate">{{ $alert->user->email }}</div>
                            </div>
                        </a>
                        @else
                            <span class="text-xs text-muted-foreground italic">Аккаунт удален</span>
                        @endif
                    </x-ui.table-cell>
                    
                    <!-- КОЛОНКА ТРИГГЕРА (Широкая, с переносом строк) -->
                    <x-ui.table-cell class="max-w-[350px] w-[350px]">
                        <div x-data="{ open: false }" class="w-full">
                            <button @click="open = !open" class="w-full text-left flex items-start justify-between gap-2 group">
                                <div class="min-w-0">
                                    <div class="font-medium text-sm text-foreground whitespace-normal break-words">{{ $alert->trigger_label }}</div>
                                    
                                    <!-- ПРИМЕНЯЕМ ЦВЕТ ИЗ ENUM -->
                                    <div class="font-mono text-[10px] {{ $alert->trigger_type?->colorClass() }} break-all inline-block py-0.5 px-1 rounded-sm">{{ $alert->trigger_type->value }}</div>
                                </div>
                                <x-lucide-chevron-down class="w-4 h-4 shrink-0 mt-1 text-primary transition-transform duration-200" x-bind:class="open ? 'rotate-180' : ''" />
                            </button>
                            
                            <div x-show="open" x-collapse class="mt-2 p-3 bg-muted/50 rounded-md border border-border text-xs space-y-1">
                                @if(!empty($alert->meta))
                                    @foreach($alert->meta as $key => $val)
                                        <div class="flex gap-2">
                                            <span class="font-mono text-muted-foreground shrink-0">{{ $key }}:</span>
                                            <span class="text-foreground font-medium whitespace-normal break-words">
                                                @if(is_array($val))
                                                    {{ implode(', ', $val) }}
                                                @else
                                                    {{ $val }}
                                                @endif
                                            </span>
                                        </div>
                                    @endforeach
                                @else
                                    <p class="text-muted-foreground italic">Улик нет</p>
                                @endif
                            </div>
                        </div>
                    </x-ui.table-cell>
                    
                    <x-ui.table-cell>
                        <x-ui.badge variant="{{ $alert->severityBadge['variant'] }}" size="sm">
                            {{ $alert->severityBadge['label'] }}
                        </x-ui.badge>
                    </x-ui.table-cell>
                    
                    <x-ui.table-cell>
                        <x-ui.badge variant="{{ $alert->statusBadge['variant'] }}" size="sm">
                            {{ $alert->statusBadge['label'] }}
                        </x-ui.badge>
                        @if($alert->admin)
                            <div class="text-[10px] text-muted-foreground mt-1">{{ $alert->admin->name }}</div>
                        @endif
                    </x-ui.table-cell>
                    
                    <x-ui.table-cell class="text-muted-foreground text-xs whitespace-nowrap">
                        {{ $alert->created_at->format('d.m.Y H:i') }}
                    </x-ui.table-cell>
                    
                    <!-- ВЫПАДАЮЩЕЕ МЕНЮ ДЕЙСТВИЙ С ТИПАМИ БАНА -->
                    <x-ui.table-cell class="text-right">
                        <x-ui.dropdown-menu>
                            <x-ui.dropdown-menu-trigger>
                                <x-ui.button variant="ghost" size="icon-sm">
                                    <x-lucide-more-horizontal class="w-4 h-4" />
                                </x-ui.button>
                            </x-ui.dropdown-menu-trigger>
                            <x-ui.dropdown-menu-content align="end">
                                @if($isOpen)
                                    <x-ui.dropdown-menu-label>Выберите наказание</x-ui.dropdown-menu-label>
                                    <x-ui.dropdown-menu-separator />
                                    
                                    @if($alert->user)
                                        <x-ui.dropdown-menu-item wire:click="resolveAndBan({{ $alert->id }}, 'shadow')" wire:confirm="Наложить теневой бан и закрыть алерт?">
                                            <x-lucide-eye-off class="w-4 h-4 text-purple-500" /> Теневой бан...
                                        </x-ui.dropdown-menu-item>
                                        <x-ui.dropdown-menu-item wire:click="resolveAndBan({{ $alert->id }}, 'temp')" wire:confirm="Забанить на 3 дня и закрыть алерт?">
                                            <x-lucide-clock class="w-4 h-4 text-yellow-500" /> Бан на 3 дня...
                                        </x-ui.dropdown-menu-item>
                                        <x-ui.dropdown-menu-item wire:click="resolveAndBan({{ $alert->id }}, 'permanent')" wire:confirm="Забанить навсегда и закрыть алерт?">
                                            <x-lucide-gavel class="w-4 h-4 text-red-500" /> Вечный бан
                                        </x-ui.dropdown-menu-item>
                                    @else
                                        <x-ui.dropdown-menu-item wire:click="resolveAndBan({{ $alert->id }}, 'permanent')" wire:confirm="Закрыть алерт? (Аккаунт уже удален)">
                                            <x-lucide-check class="w-4 h-4 text-green-500" /> Закрыть алерт
                                        </x-ui.dropdown-menu-item>
                                    @endif

                                    <x-ui.dropdown-menu-separator />
                                    <x-ui.dropdown-menu-item wire:click="markAsFalsePositive({{ $alert->id }})" wire:confirm="Отметить как ложное срабатывание?">
                                        <x-lucide-shield-check class="w-4 h-4 text-green-500" /> Отметить как ложняк
                                    </x-ui.dropdown-menu-item>
                                @else
                                    <x-ui.dropdown-menu-label>Алерт закрыт</x-ui.dropdown-menu-label>
                                    <x-ui.dropdown-menu-separator />
                                    @if($alert->admin)
                                        <x-ui.dropdown-menu-item disabled>
                                            <x-lucide-user-check class="w-4 h-4" /> Разобрал: {{ $alert->admin->name }}
                                        </x-ui.dropdown-menu-item>
                                    @endif
                                @endif
                            </x-ui.dropdown-menu-content>
                        </x-ui.dropdown-menu>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @empty
                <x-ui.table-row wire:key="empty-state">
                    <x-ui.table-cell colspan="7" class="py-12 text-center text-muted-foreground">
                        <x-lucide-shield-check class="w-12 h-12 opacity-30 mx-auto mb-2" />
                        Открытых алертов нет. Можно отдохнуть!
                    </x-ui.table-cell>
                </x-ui.table-row>
            @endforelse
        </x-ui.table-body>
    </x-ui.table>

    <!-- Пагинация -->
    <div class="flex items-center justify-end flex-wrap gap-2">
        <div class="text-xs text-muted-foreground">
            Показано {{ $this->alerts->firstItem() ?? 0 }} - {{ $this->alerts->lastItem() ?? 0 }} из {{ $this->alerts->total() }}
        </div>
        {{ $this->alerts->links('partials.pagination') }}
    </div>
</div>