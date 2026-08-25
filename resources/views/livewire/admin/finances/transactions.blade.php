<?php

use App\Actions\Admin\TransactionAction;
use App\Enums\RefundReason;
use App\Models\Transaction;
use App\Services\Payments\MockAcquiringService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public string $statusFilter = 'all';
    public string $typeFilter = 'all';

    #[Url(as: 'period', except: 'all')]
    public string $dateFilter = 'all';

    public ?int $viewingTransactionId = null;
    public ?int $refundingTransactionId = null;
    
    public string $refundReason = '';
    public string $refundComment = '';

    /** @var string URL для кнопки "Назад" */
    public string $backUrl = '';

    public function mount(): void
    {
        // ФИКС: Запоминаем URL "Назад" только при первой загрузке
        $previousUrl = url()->previous();
        $this->backUrl = ($previousUrl && $previousUrl !== url()->current()) 
            ? $previousUrl 
            : route('admin.dashboard');

        if (request()->has('q')) {
            $this->statusFilter = 'all';
            $this->typeFilter = 'all';
            $this->dateFilter = 'all';
        }
    }
    // ФИКС РЕАКТИВНОСТИ: Сбрасываем кэш при любом изменении фильтров
    public function updatedSearch(): void { $this->resetPage(); $this->clearComputedCache(); }
    public function updatedStatusFilter(): void { $this->resetPage(); $this->clearComputedCache(); }
    public function updatedTypeFilter(): void { $this->resetPage(); $this->clearComputedCache(); }
    public function updatedDateFilter(): void { $this->resetPage(); $this->clearComputedCache(); }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->resetPage();
        $this->clearComputedCache();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'typeFilter', 'dateFilter']);
        $this->statusFilter = 'all';
        $this->typeFilter = 'all';
        $this->dateFilter = 'all';
        $this->resetPage();
        $this->clearComputedCache();
    }

    public function viewTransaction(int $id): void { $this->viewingTransactionId = $id; }

    public function openRefundModal(int $transactionId): void
    {
        $this->refundingTransactionId = $transactionId;
        $this->refundReason = '';
        $this->refundComment = '';
    }

    public function syncTransaction(int $id, TransactionAction $action, MockAcquiringService $bank): void
    {
        $transaction = Transaction::find($id);
        if (!$transaction || $transaction->status !== 'pending') return;

        $result = $action->syncWithBank($transaction, $bank);

        $this->dispatch('show-toast', type: $result['success'] ? 'success' : 'error', message: $result['message']);
        $this->clearComputedCache();
    }

        #[Computed]
    public function refundingTransaction()
    {
        if (!$this->refundingTransactionId) return null;
        
        return Transaction::find($this->refundingTransactionId);
    }

    public function processRefund(TransactionAction $action): void
    {
        $this->validate([
            'refundReason' => ['required', 'in:' . implode(',', array_column(RefundReason::cases(), 'value'))],
            'refundComment' => 'nullable|string|min:3',
        ]);

        $transaction = Transaction::find($this->refundingTransactionId);
        if (!$transaction || $transaction->status !== 'success') return;

        $reasonEnum = RefundReason::tryFrom($this->refundReason);
        
        $action->processRefund($transaction, $reasonEnum, $this->refundComment);

        $this->refundingTransactionId = null;
        $this->dispatch('show-toast', type: 'success', message: 'Заявка на возврат отправлена в банк.');
        $this->clearComputedCache();
    }

    private function clearComputedCache(): void
    {
        unset($this->transactions);
        unset($this->counts);
        unset($this->totalRevenue);
        unset($this->viewingTransaction);
    }

    // ============================================
    // ВЫВОД ДАННЫХ
    // ============================================

    private function applyDateFilter($q): void
    {
        if ($this->dateFilter !== 'all') {
            $date = match ($this->dateFilter) {
                'day' => now()->startOfDay(),
                'week' => now()->startOfWeek(),
                'month' => now()->startOfMonth(),
                default => null
            };
            if ($date) $q->where('created_at', '>=', $date);
        }
    }

    #[Computed]
    public function counts(): array
    {
        $baseQuery = Transaction::query();
        $this->applyDateFilter($baseQuery);

        return [
            'all' => (clone $baseQuery)->count(),
            'success' => (clone $baseQuery)->where('status', 'success')->count(),
            'pending' => (clone $baseQuery)->where('status', 'pending')->count(),
            'failed' => (clone $baseQuery)->where('status', 'failed')->count(),
            'refunded' => (clone $baseQuery)->where('status', 'refunded')->count(),
        ];
    }

    #[Computed]
    public function transactions()
    {
        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
        $search = $this->search;

        $userQuery = fn($q) => $q->withTrashed()->select('id', 'name', 'email', 'status', 'is_premium', 'premium_expires_at', 'last_seen', 'deleted_at')
            ->with(['photos' => fn($sq) => $sq->select('id', 'user_id', 'is_primary', 'status', 'path_thumb')->orderByDesc('is_primary')->limit(1)]);

        $query = Transaction::query()
            ->with(['user' => $userQuery])
            ->when($search, function ($q) use ($search, $operator) {
                $q->where(function ($q) use ($search, $operator) {
                    $q->whereHas('user', fn($uq) => $uq->withTrashed()->where('name', $operator, "%{$search}%")->orWhere('email', $operator, "%{$search}%"))
                      ->orWhere('provider_transaction_id', $operator, "%{$search}%");
                    
                    if (is_numeric($search)) {
                        $q->orWhere('id', (int) $search)->orWhere('user_id', (int) $search);
                    }
                });
            })
            ->when($this->statusFilter !== 'all', fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter !== 'all', fn($q) => $q->where('type', $this->typeFilter));

        $this->applyDateFilter($query);

        return $query->latest('created_at')->latest('id')->paginate(15);
    }

    #[Computed]
    public function totalRevenue(): string
    {
        $query = Transaction::success()->where('type', '!=', 'refund');
        $this->applyDateFilter($query);
        $sum = $query->sum('amount');
        return number_format($sum, 2, '.', ' ') . ' ₽';
    }

    #[Computed]
    public function viewingTransaction()
    {
        if (!$this->viewingTransactionId) return null;

        $userQuery = fn($q) => $q->withTrashed()->select('id', 'name', 'email', 'status', 'is_premium', 'premium_expires_at', 'last_seen', 'deleted_at')
            ->with(['photos' => fn($sq) => $sq->select('id', 'user_id', 'is_primary', 'status', 'path_thumb')->orderByDesc('is_primary')->limit(1)]);

        return Transaction::with(['user' => $userQuery])->find($this->viewingTransactionId);
    }
}; 
?>

<div class="space-y-6">
    <!-- Шапка с кнопкой "Назад" -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-4">
            <a href="{{ $backUrl }}" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-semibold flex items-center gap-2">
                    <x-lucide-landmark class="w-6 h-6" />
                    Финансы и транзакции
                </h1>
                <p class="text-sm text-muted-foreground">История платежей и списаний</p>
            </div>
        </div>

        {{-- Блок выручки --}}
        <div class="bg-emerald-500/10 text-emerald-600 px-4 py-2 rounded-lg border border-emerald-500/20 flex items-center gap-2">
            <x-lucide-trending-up class="w-5 h-5" />
            <span>Выручка за период: <span class="font-bold">{{ $this->totalRevenue }}</span></span>
        </div>
    </div>

    <!-- Фильтры -->
    <div class="flex flex-col gap-2">      

        <div class="flex items-center justify-between gap-2">
            {{-- ФИЛЬТР ПЕРИОДА --}}
            <div class="flex gap-1 mr-2">
                <x-ui.button wire:click="$set('dateFilter', 'all')" variant="{{ $dateFilter == 'all' ? 'default' : 'secondary' }}" size="sm">За все время</x-ui.button>
                <x-ui.button wire:click="$set('dateFilter', 'day')" variant="{{ $dateFilter == 'day' ? 'default' : 'secondary' }}" size="sm">Сегодня</x-ui.button>
                <x-ui.button wire:click="$set('dateFilter', 'week')" variant="{{ $dateFilter == 'week' ? 'default' : 'secondary' }}" size="sm">Неделя</x-ui.button>
                <x-ui.button wire:click="$set('dateFilter', 'month')" variant="{{ $dateFilter == 'month' ? 'default' : 'secondary' }}" size="sm">Месяц</x-ui.button>                
            </div>

            <div class="flex items-center gap-2">
                <x-ui.select wire:key="type-filter-select" wire:model.live="typeFilter">
                    <x-ui.select-trigger class="w-40"><x-ui.select-value placeholder="Тип операции" /></x-ui.select-trigger>
                    <x-ui.select-content>
                        <x-ui.select-item value="all">Все типы</x-ui.select-item>
                        <x-ui.select-item value="subscription">Подписки</x-ui.select-item>
                        <x-ui.select-item value="credits">Кредиты</x-ui.select-item>                   
                    </x-ui.select-content>
                </x-ui.select>

                <div class="relative w-64">
                    <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="ID, Email или ID от банка..." class="pl-9 pr-8" />
                    <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                    @if(!empty($search))
                        <button wire:click="$set('search', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"><x-lucide-x class="w-4 h-4" /></button>
                    @endif
                </div>
            </div>    
         </div>

           <div class="flex flex-wrap gap-1.5">
            <x-ui.button wire:click="setStatusFilter('all')" variant="{{ $statusFilter === 'all' ? 'default' : 'secondary' }}" size="sm">
                Все <x-ui.badge size="xs" class="ml-1">{{ $this->counts['all'] }}</x-ui.badge>
            </x-ui.button>
             <x-ui.button wire:click="setStatusFilter('pending')" variant="{{ $statusFilter === 'pending' ? 'default' : 'secondary' }}" size="sm">
                <x-lucide-clock class="w-4 h-4 inline mr-1 text-yellow-500" /> В ожидании <x-ui.badge size="xs" class="ml-1">{{ $this->counts['pending'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="setStatusFilter('success')" variant="{{ $statusFilter === 'success' ? 'default' : 'secondary' }}" size="sm">
                <x-lucide-check-circle class="w-4 h-4 inline mr-1 text-green-500" /> Успешные <x-ui.badge size="xs" class="ml-1">{{ $this->counts['success'] }}</x-ui.badge>
            </x-ui.button>           
            <x-ui.button wire:click="setStatusFilter('failed')" variant="{{ $statusFilter === 'failed' ? 'default' : 'secondary' }}" size="sm">
                <x-lucide-x-circle class="w-4 h-4 inline mr-1 text-red-500" /> Ошибки <x-ui.badge size="xs" class="ml-1">{{ $this->counts['failed'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="setStatusFilter('refunded')" variant="{{ $statusFilter === 'refunded' ? 'default' : 'secondary' }}" size="sm">
                <x-lucide-rotate-ccw class="w-4 h-4 inline mr-1 text-blue-500" /> Возвраты <x-ui.badge size="xs" class="ml-1">{{ $this->counts['refunded'] }}</x-ui.badge>
            </x-ui.button>
        </div>
    </div>     
    <!-- Таблица -->
    <x-ui.table>
        <x-ui.table-header>
            <x-ui.table-row>
                <x-ui.table-head class="w-12">ID</x-ui.table-head>
                <x-ui.table-head>Пользователь</x-ui.table-head>
                <x-ui.table-head>Сумма</x-ui.table-head>
                <x-ui.table-head>Тип</x-ui.table-head>
                <x-ui.table-head>Статус</x-ui.table-head>
                <x-ui.table-head>Провайдер</x-ui.table-head>
                <x-ui.table-head>Дата</x-ui.table-head>
                <x-ui.table-head class="text-right">Действия</x-ui.table-head>
            </x-ui.table-row>
        </x-ui.table-header>

        <x-ui.table-body>
            @forelse ($this->transactions as $transaction)
                          @php 
                    $statusBadge = $transaction->status_badge;
                    
                    // ФИКС: Контрастные цвета. Premium - success (зеленый), VIP - info (синий), Кредиты - warning (желтый)
                    $typeBadge = match($transaction->type) {
                        'subscription' => [
                            'variant' => ($transaction->meta['tier'] ?? 'sub') === 'vip' ? 'default' : 'outline', 
                            'label' => ($transaction->meta['tier'] ?? 'sub') === 'vip' ? 'VIP' : 'Premium'
                        ],
                        'credits' => ['variant' => 'warning', 'label' => 'Кредиты'],
                        'refund' => ['variant' => 'destructive', 'label' => 'Возврат'],
                        default => ['variant' => 'secondary', 'label' => $transaction->type]
                    };
                    
                    $amountClass = match($transaction->status) {
                        'refunded' => 'text-destructive font-medium',
                        'failed' => 'text-muted-foreground/50',
                        'pending' => 'text-muted-foreground',
                        default => 'text-green-500 font-medium'
                    };
                    $amountSign = $transaction->status === 'refunded' ? '-' : '';
                    $isHighlighted = is_numeric($this->search) && $transaction->id == (int)$this->search;
                @endphp
                <x-ui.table-row 
                    wire:key="trans-{{ $transaction->id }}-{{ $transaction->status }}"
                    class="{{ $isHighlighted ? 'bg-blue-500/10 ring-2 ring-blue-500/50' : '' }}"
                    x-data="{ isHi: {{ $isHighlighted ? 'true' : 'false' }} }"
                    x-init="isHi && setTimeout(() => { $el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 200)"
                >
                    <x-ui.table-cell class="text-xs font-mono whitespace-nowrap {{ $isHighlighted ? 'text-blue-500 font-bold' : 'text-muted-foreground' }}">
                        #{{ $transaction->id }}
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        @if($transaction->user)
                            <a href="{{ route('admin.users.show', $transaction->user->id) }}?tab=finance" wire:navigate class="flex items-center gap-2 group">
                                <x-avatar src="{{ $transaction->user->avatar_url }}" name="{{ $transaction->user->name }}" size="sm" userId="{{ $transaction->user->id }}" showStatus="true" :isOnline="$transaction->user->is_online"/>
                                <div class="flex flex-col min-w-0">
                                    <span class="text-sm font-medium group-hover:text-primary flex items-center gap-1.5">
                                        <x-user-status-sign :user="$transaction->user" />
                                        {{ $transaction->user->name }}
                                        @if($transaction->user->has_active_premium)<x-lucide-crown class="w-3.5 h-3.5 text-yellow-500" />@endif
                                    </span>
                                    <span class="text-xs text-muted-foreground truncate">{{ $transaction->user->email }}</span>                                    
                                </div>
                            </a>
                        @else
                            <span class="text-xs text-muted-foreground italic">Юзер удален</span>
                        @endif
                    </x-ui.table-cell>
                    <x-ui.table-cell class="font-medium text-sm whitespace-nowrap {{ $amountClass }}">
                        {{ $amountSign }}{{ $transaction->formatted_amount }}
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        <x-ui.badge variant="{{ $typeBadge['variant'] }}" size="sm">{{ $typeBadge['label'] }}</x-ui.badge>
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        <x-ui.badge variant="{{ $statusBadge['variant'] }}" size="sm">{{ $statusBadge['label'] }}</x-ui.badge>
                    </x-ui.table-cell>
                    <x-ui.table-cell class="text-xs text-muted-foreground uppercase">
                        {{ $transaction->provider ?? '—' }}
                    </x-ui.table-cell>
                    <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap">
                        {{ $transaction->created_at->format('d.m.Y H:i') }}
                    </x-ui.table-cell>
                    <x-ui.table-cell class="text-right">
                        <div class="flex gap-1 justify-end">
                            <x-ui.button wire:click="viewTransaction({{ $transaction->id }})" variant="ghost" size="icon-sm" title="Посмотреть детали">
                                <x-lucide-eye class="w-4 h-4" />
                            </x-ui.button>
                            
                            {{-- Кнопка ручной синхронизации для зависших платежей --}}
                            @if($transaction->status === 'pending')
                                <x-ui.button wire:click="syncTransaction({{ $transaction->id }})" variant="ghost" size="icon-sm" title="Проверить в банке" wire:loading.attr="disabled" wire:target="syncTransaction({{ $transaction->id }})">
                                    <span wire:loading.remove wire:target="syncTransaction({{ $transaction->id }})"><x-lucide-refresh-cw class="w-4 h-4 text-blue-500" /></span>
                                    <span wire:loading wire:target="syncTransaction({{ $transaction->id }})"><x-lucide-loader-2 class="w-4 h-4 animate-spin" /></span>
                                </x-ui.button>
                            @endif

                            {{-- Кнопка возврата --}}
                            @if($transaction->status === 'success' && $transaction->type !== 'refund')
                                <x-ui.button wire:click="openRefundModal({{ $transaction->id }})" variant="ghost" size="icon-sm" title="Оформить возврат">
                                    <x-lucide-undo-2 class="w-4 h-4 text-destructive" />
                                </x-ui.button>
                            @endif
                        </div>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @empty
                <x-ui.table-row>
                    <x-ui.table-cell colspan="8" class="py-12 text-center text-muted-foreground">
                        <div class="flex flex-col items-center gap-2">
                            <x-lucide-receipt class="w-12 h-12 opacity-30" />
                            <p>Транзакций не найдено</p>
                            @if(!empty($search) || $statusFilter !== 'all' || $typeFilter !== 'all')
                                <x-ui.button wire:click="resetFilters" variant="outline" size="sm" class="mt-2">Сбросить фильтры</x-ui.button>
                            @endif
                        </div>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @endforelse
        </x-ui.table-body>
    </x-ui.table>

    <div class="mt-4">{{ $this->transactions->links('partials.pagination') }}</div>

    <!-- МОДАЛКА ПРОСМОТРА ДЕТАЛЕЙ ТРАНЗАКЦИИ -->
    @if($viewingTransactionId)
        <div wire:key="view-modal-{{ $viewingTransactionId }}" 
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
             wire:click="$set('viewingTransactionId', null)">
             
            <div class="relative bg-card border border-border rounded-lg shadow-2xl max-w-2xl w-full mx-4 overflow-hidden" wire:click.stop>
                 
                <div class="flex items-center justify-between p-4 border-b border-border">
                    <h2 class="text-lg font-semibold">Детали транзакции #{{ $this->viewingTransaction->id }}</h2>
                    <x-ui.button variant="ghost" size="icon-sm" wire:click="$set('viewingTransactionId', null)">
                        <x-lucide-x class="w-5 h-5" />
                    </x-ui.button>
                </div>

                <div class="p-6 space-y-4">
                    {{-- Блок пользователя с аватаркой (на всю ширину) --}}
                    <div class="pb-4 border-b border-border mb-4">
                        <p class="text-xs text-muted-foreground mb-2">Пользователь</p>
                        @if($this->viewingTransaction->user)
                            <a href="{{ route('admin.users.show', $this->viewingTransaction->user->id) }}?tab=finance" wire:navigate class="flex items-center gap-2 group">
                                <x-avatar src="{{ $this->viewingTransaction->user->avatar_url }}" name="{{ $this->viewingTransaction->user->name }}" size="sm" userId="{{ $this->viewingTransaction->user->id }}" showStatus="true" :isOnline="$this->viewingTransaction->user->is_online"/>
                                <div class="flex flex-col min-w-0">
                                    <span class="text-sm font-medium group-hover:text-primary flex items-center gap-1.5">
                                        <x-user-status-sign :user="$this->viewingTransaction->user" />
                                        {{ $this->viewingTransaction->user->name }}
                                        @if($this->viewingTransaction->user->has_active_premium)<x-lucide-crown class="w-3.5 h-3.5 text-yellow-500" />@endif                                        
                                    </span>
                                    <span class="text-xs text-muted-foreground truncate">{{ $this->viewingTransaction->user->email }}</span>
                                </div>
                            </a>
                        @else
                            <span class="text-sm text-muted-foreground italic">Юзер удален</span>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-xs text-muted-foreground">Сумма</p>
                            <p class="font-medium">{{ $this->viewingTransaction->formatted_amount }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Тип</p>
                            @php
                                $modalTypeVariant = 'secondary';
                                $modalTypeLabel = ucfirst($this->viewingTransaction->type);
                                if ($this->viewingTransaction->type === 'subscription') {
                                    $isVip = ($this->viewingTransaction->meta['tier'] ?? '') === 'vip';
                                    $modalTypeVariant = $isVip ? 'default' : 'outline';
                                    $modalTypeLabel = $isVip ? 'VIP' : 'Premium';
                                } elseif ($this->viewingTransaction->type === 'credits') {
                                    $modalTypeVariant = 'warning';
                                }
                            @endphp
                            <x-ui.badge variant="{{ $modalTypeVariant }}" size="sm">{{ $modalTypeLabel }}</x-ui.badge>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Статус</p>
                            <x-ui.badge variant="{{ $this->viewingTransaction->status_badge['variant'] }}" size="sm">{{ $this->viewingTransaction->status_badge['label'] }}</x-ui.badge>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Провайдер</p>
                            <p class="font-medium uppercase">{{ $this->viewingTransaction->provider ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">ID транзакции провайдера</p>
                            <p class="font-mono text-xs break-all bg-muted p-2 rounded">{{ $this->viewingTransaction->provider_transaction_id ?? '—' }}</p>
                        </div>
                        @if($this->viewingTransaction->credits_amount)
                            <div>
                                <p class="text-xs text-muted-foreground">Начислено кредитов</p>
                                <p class="font-medium">{{ $this->viewingTransaction->credits_amount }} 💎</p>
                            </div>
                        @endif
                        <div>
                            <p class="text-xs text-muted-foreground">Дата</p>
                            <p class="font-medium">{{ $this->viewingTransaction->created_at->format('d.m.Y H:i:s') }}</p>
                        </div>
                    </div>

                    @if($this->viewingTransaction->meta)
                        <div>
                            <p class="text-xs text-muted-foreground mb-1">Сырые данные (Meta):</p>
                            <pre class="text-[10px] bg-muted/50 p-3 rounded-md overflow-auto max-h-40 border border-border font-mono whitespace-pre-wrap">{{ json_encode($this->viewingTransaction->meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

       <!-- МОДАЛКА ВОЗВРАТА (Refund) -->
    @if($refundingTransactionId)
        <div wire:key="refund-modal-{{ $refundingTransactionId }}"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
             wire:click="$set('refundingTransactionId', null)">
             
            <div class="relative bg-card border border-border rounded-lg shadow-2xl max-w-md w-full mx-4 overflow-hidden" wire:click.stop>
                
                <div class="flex items-center justify-between p-4 border-b border-border">
                    <h2 class="text-lg font-semibold text-destructive flex items-center gap-2">
                        <x-lucide-alert-triangle class="w-5 h-5" /> Оформить возврат
                    </h2>
                    <x-ui.button variant="ghost" size="icon-sm" wire:click="$set('refundingTransactionId', null)">
                        <x-lucide-x class="w-5 h-5" />
                    </x-ui.button>
                </div>

                <div class="p-6 space-y-4">
                    
                    {{-- НОВОЕ: Плашка с данными транзакции --}}
                    @if($this->refundingTransaction)
                        <div class="bg-muted/30 border border-border rounded-md p-4 space-y-2 mb-2">
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-muted-foreground">ID транзакции:</span>
                                <span class="font-mono font-bold">#{{ $this->refundingTransaction->id }}</span>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-muted-foreground">Сумма:</span>
                                <span class="font-bold text-foreground">{{ $this->refundingTransaction->formatted_amount }}</span>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-muted-foreground">Тип операции:</span>
                                @php
                                    $refundTypeBadge = match($this->refundingTransaction->type) {
                                        'subscription' => [
                                            'variant' => ($this->refundingTransaction->meta['tier'] ?? 'sub') === 'vip' ? 'default' : 'outline', 
                                            'label' => ($this->refundingTransaction->meta['tier'] ?? 'sub') === 'vip' ? 'VIP' : 'Premium'
                                        ],
                                        'credits' => ['variant' => 'warning', 'label' => 'Кредиты'],
                                        default => ['variant' => 'secondary', 'label' => ucfirst($this->refundingTransaction->type)]
                                    };
                                @endphp
                                <x-ui.badge variant="{{ $refundTypeBadge['variant'] }}" size="sm">{{ $refundTypeBadge['label'] }}</x-ui.badge>
                            </div>
                        </div>
                    @endif

                    <p class="text-sm text-muted-foreground">
                        Вы уверены? Деньги будут возвращены пользователю. В приложении будет вызван API платежной системы.
                    </p>
                    
                    <div class="space-y-3">
                        <!-- Селект причины возврата (Enum) -->
                        <div>
                            <x-ui.label class="text-sm font-medium">Причина возврата (обязательно)</x-ui.label>
                            <x-ui.select wire:model="refundReason" class="mt-1">
                                <x-ui.select-trigger class="w-full">
                                    <x-ui.select-value placeholder="Выберите причину..." />
                                </x-ui.select-trigger>
                                <x-ui.select-content>
                                    @foreach(\App\Enums\RefundReason::options() as $value => $label)
                                        <x-ui.select-item value="{{ $value }}">{{ $label }}</x-ui.select-item>
                                    @endforeach
                                </x-ui.select-content>
                            </x-ui.select>
                            @error('refundReason') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                        </div>

                        <!-- Текстовый комментарий (опционально) -->
                        <div>
                            <x-ui.label class="text-sm font-medium">Комментарий / Детали (опционально)</x-ui.label>
                            <textarea wire:model="refundComment" rows="2" placeholder="Например: Банк отклонил 3-D Secure..." class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring mt-1"></textarea>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 p-4 border-t border-border bg-muted/20">
                    <x-ui.button wire:click="$set('refundingTransactionId', null)" variant="outline" size="sm">Отмена</x-ui.button>
                    <x-ui.button wire:click="processRefund" variant="destructive" size="sm" wire:loading.attr="disabled" wire:target="processRefund">
                        <span wire:loading.remove wire:target="processRefund">Подтвердить возврат</span>
                        <span wire:loading wire:target="processRefund" class="flex items-center gap-2">
                            <x-lucide-loader-2 class="w-4 h-4 animate-spin inline" /> Обработка...
                        </span>
                    </x-ui.button>
                </div>
            </div>
        </div>
    @endif   
</div>

