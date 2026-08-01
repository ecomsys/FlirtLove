<?php

use App\Models\Transaction;
use App\Models\AdminLog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    #[Url(as: 'status', except: 'all')]
    public string $statusFilter = 'all';
    
    #[Url(as: 'type', except: 'all')]
    public string $typeFilter = 'all';

    #[Url(as: 'period', except: 'all')]
    public string $dateFilter = 'month';

    public string $search = '';
    public int $perPage = 20;

    // Состояние модалки возврата
    public ?int $refundingTransactionId = null;
    public string $refundReason = '';

    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedTypeFilter(): void { $this->resetPage(); }
    public function updatedDateFilter(): void { $this->resetPage(); }

    public function openRefundModal(int $transactionId): void
    {
        $this->refundingTransactionId = $transactionId;
        $this->refundReason = '';
    }

    public function processRefund(): void
    {
        $this->validate(['refundReason' => 'required|string|min:3']);

        $transaction = Transaction::find($this->refundingTransactionId);
        if (!$transaction || $transaction->status !== 'success') return;

        // Вызываем наш метод из модели
        $result = $transaction->markAsRefunded(['reason' => $this->refundReason]);

        if ($result) {
            // Логируем действие
            AdminLog::record('transaction.refund', $transaction, auth()->user(), ['status' => 'success'], ['status' => 'refunded', 'reason' => $this->refundReason]);

            // В реальном приложении здесь бы шел вызов API платежки (Stripe/YooKassa) на возврат средств
            // TODO: Dispatch RefundJob
            
            $this->dispatch('show-toast', type: 'success', message: 'Возврат оформлен. Статус обновлен.');
        } else {
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка: Возврат невозможен.');
        }

        $this->refundingTransactionId = null;
    }

    public function with(): array
    {
        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

        $transactions = Transaction::with('user')
            ->when($this->statusFilter !== 'all', fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter !== 'all', fn($q) => $q->where('type', $this->typeFilter))
            ->when($this->dateFilter !== 'all', function ($q) {
                $date = match ($this->dateFilter) {
                    'day' => now()->startOfDay(),
                    'week' => now()->startOfWeek(),
                    'month' => now()->startOfMonth(),
                    default => null,
                };
                if ($date) $q->where('created_at', '>=', $date);
            })
            ->when($this->search, function ($q) use ($operator) {
                $q->where(function ($subQ) use ($operator) {
                    $subQ->where('id', $this->search)
                         ->orWhere('provider_transaction_id', $operator, "%{$this->search}%")
                         ->orWhereHas('user', fn($uq) => $uq->where('name', $operator, "%{$this->search}%"));
                });
            })
            ->latest()
            ->paginate($this->perPage);

        // Считаем выручку для шапки
        $revenue = Transaction::success()->where('type', '!=', 'refund')
            ->when($this->dateFilter !== 'all', function ($q) {
                $date = match ($this->dateFilter) {
                    'day' => now()->startOfDay(),
                    'week' => now()->startOfWeek(),
                    'month' => now()->startOfMonth(),
                    default => null,
                };
                if ($date) $q->where('created_at', '>=', $date);
            })
            ->sum('amount');

        return [
            'transactions' => $transactions,
            'totalRevenue' => $revenue,
        ];
    }
}; 
?>

<div class="space-y-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <h1 class="text-2xl font-semibold">Транзакции</h1>
        <div class="bg-emerald-500/10 text-emerald-600 px-4 py-2 rounded-lg border border-emerald-500/20">
            Выручка за период: <span class="font-bold">{{ number_format($totalRevenue, 2, '.', ' ') }} ₽</span>
        </div>
    </div>

    <!-- Фильтры -->
    <div class="flex flex-wrap items-center gap-3">
        <!-- Статус -->
        <select wire:model.live="statusFilter" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm md:w-[140px]">
            <option value="all">Все статусы</option>
            <option value="pending">В ожидании</option>
            <option value="success">Успешные</option>
            <option value="failed">Ошибки</option>
            <option value="refunded">Возвраты</option>
        </select>

        <!-- Тип -->
        <select wire:model.live="typeFilter" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm md:w-[160px]">
            <option value="all">Все типы</option>
            <option value="subscription">Подписки</option>
            <option value="credits">Кредиты</option>
            <option value="refund">Возвраты</option>
        </select>

        <!-- Период -->
        <div class="flex gap-1">
            <x-ui.button wire:click="$set('dateFilter', 'day')" variant="{{ $dateFilter == 'day' ? 'default' : 'secondary' }}" size="sm">Сегодня</x-ui.button>
            <x-ui.button wire:click="$set('dateFilter', 'week')" variant="{{ $dateFilter == 'week' ? 'default' : 'secondary' }}" size="sm">Неделя</x-ui.button>
            <x-ui.button wire:click="$set('dateFilter', 'month')" variant="{{ $dateFilter == 'month' ? 'default' : 'secondary' }}" size="sm">Месяц</x-ui.button>
            <x-ui.button wire:click="$set('dateFilter', 'all')" variant="{{ $dateFilter == 'all' ? 'default' : 'secondary' }}" size="sm">Все</x-ui.button>
        </div>

        <div class="ml-auto relative">
            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="ID, Транзакция или Имя..."
                class="pl-9 pr-3 py-2 text-sm bg-card border border-border rounded-lg focus:outline-none w-64" />
        </div>
    </div>

    <!-- Таблица -->
    <x-ui.table>
        <x-ui.table-header>
            <x-ui.table-row>
                <x-ui.table-head class="w-12">ID</x-ui.table-head>
                <x-ui.table-head>Юзер</x-ui.table-head>
                <x-ui.table-head>Сумма</x-ui.table-head>
                <x-ui.table-head>Тип</x-ui.table-head>
                <x-ui.table-head>Статус</x-ui.table-head>
                <x-ui.table-head>Платежка</x-ui.table-head>
                <x-ui.table-head>Дата</x-ui.table-head>
                <x-ui.table-head class="text-right">Действия</x-ui.table-head>
            </x-ui.table-row>
        </x-ui.table-header>

        <x-ui.table-body>
            @forelse ($transactions as $transaction)
                <x-ui.table-row wire:key="transaction-{{ $transaction->id }}">
                    <x-ui.table-cell class="text-muted-foreground text-xs">#{{ $transaction->id }}</x-ui.table-cell>
                    
                    <x-ui.table-cell>
                        @if($transaction->user)
                            <a href="{{ route('admin.users.show', $transaction->user_id) }}" wire:navigate class="text-sm font-medium hover:text-primary">
                                {{ $transaction->user->name }}
                            </a>
                        @else
                            <span class="text-muted-foreground">Удален</span>
                        @endif
                    </x-ui.table-cell>

                    <x-ui.table-cell class="font-medium {{ $transaction->type === 'refund' ? 'text-destructive' : 'text-foreground' }}">
                        {{ $transaction->type === 'refund' ? '-' : '+' }}{{ $transaction->formatted_amount }}
                    </x-ui.table-cell>

                    <x-ui.table-cell>
                        @php $typeMap = ['subscription' => 'Подписка', 'credits' => 'Кредиты', 'refund' => 'Возврат']; @endphp
                        <x-ui.badge variant="{{ $transaction->type === 'refund' ? 'destructive' : 'secondary' }}" size="xs">
                            {{ $typeMap[$transaction->type] ?? $transaction->type }}
                        </x-ui.badge>
                    </x-ui.table-cell>

                    <x-ui.table-cell>
                        @php $statusMap = ['pending' => 'warning', 'success' => 'success', 'failed' => 'destructive', 'refunded' => 'secondary']; @endphp
                        @php $statusLabels = ['pending' => 'Ожидает', 'success' => 'Успешно', 'failed' => 'Ошибка', 'refunded' => 'Возврат']; @endphp
                        <x-ui.badge variant="{{ $statusMap[$transaction->status] ?? 'secondary' }}" size="xs">
                            {{ $statusLabels[$transaction->status] ?? $transaction->status }}
                        </x-ui.badge>
                    </x-ui.table-cell>

                    <x-ui.table-cell class="text-xs text-muted-foreground">
                        {{ $transaction->provider ?? '—' }}<br>
                        <span class="text-[10px]">{{ $transaction->provider_transaction_id }}</span>
                    </x-ui.table-cell>

                    <x-ui.table-cell class="text-muted-foreground text-xs whitespace-nowrap">
                        {{ $transaction->created_at->format('d.m.Y H:i') }}
                    </x-ui.table-cell>

                    <x-ui.table-cell class="text-right">
                        @if($transaction->status === 'success' && $transaction->type !== 'refund')
                            <x-ui.button wire:click="openRefundModal({{ $transaction->id }})" variant="ghost" size="icon-xs" title="Оформить возврат">
                                <x-lucide-undo-2 class="w-4 h-4 text-destructive" />
                            </x-ui.button>
                        @endif
                    </x-ui.table-cell>
                </x-ui.table-row>
            @empty
                <x-ui.table-row>
                    <x-ui.table-cell colspan="8" class="py-12 text-center text-muted-foreground">
                        <x-lucide-inbox class="w-12 h-12 mx-auto opacity-30 mb-2" />
                        <p>Нет транзакций</p>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @endforelse
        </x-ui.table-body>
    </x-ui.table>

    <div class="mt-4">
        {{ $transactions->links('partials.pagination') }}
    </div>

    <!-- МОДАЛКА ВОЗВРАТА -->
    <div x-data="{ show: @entangle('refundingTransactionId') }" x-show="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" style="display: none;">
        <div class="bg-card border border-border rounded-lg p-6 w-full max-w-md shadow-xl">
            <h3 class="text-lg font-semibold mb-2">⚠️ Оформить возврат</h3>
            <p class="text-sm text-muted-foreground mb-4">
                Вы уверены? Деньги будут возвращены пользователю. В реальном приложении будет вызван API платежной системы.
            </p>
            
            <div class="space-y-3 mb-4">
                <div>
                    <label class="text-sm font-medium">Причина возврата (обязательно)</label>
                    <textarea wire:model="refundReason" rows="3" class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"></textarea>
                    @error('refundReason') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <x-ui.button wire:click="$set('refundingTransactionId', null)">Отмена</x-ui.button>
                <x-ui.button wire:click="processRefund" variant="destructive">Подтвердить возврат</x-ui.button>
            </div>
        </div>
    </div>
</div>