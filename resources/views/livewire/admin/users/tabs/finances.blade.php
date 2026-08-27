<?php

use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSubscription;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component 
{
    use WithPagination;

    public int $userId;

    #[Url(as: 'trans_page')] 
    public int $transPage = 1;

    #[Url(as: 'sub_page')] 
    public int $subPage = 1;

    public function mount(int $userId): void
    {
        $this->userId = $userId;
    }

    #[Computed]
    public function user(): User
    {
        return User::withTrashed()
            ->with('balance')
            ->findOrFail($this->userId);
    }

    #[Computed]
    public function transactions()
    {
        return Transaction::where('user_id', $this->userId)
            ->latest()
            ->paginate(5, ['*'], 'transPage');
    }

    #[Computed]
    public function subscriptions()
    {
        return UserSubscription::where('user_id', $this->userId)
            ->with('plan')
            ->latest('starts_at')
            ->paginate(5, ['*'], 'subPage');
    }

    #[On('user-action-performed')] 
    public function refreshFinance(): void
    {
        unset($this->user);
        unset($this->transactions);
        unset($this->subscriptions);
    }
}; 
?>

<div class="space-y-6">
    
    {{-- Верхняя панель: Баланс и Статус --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        {{-- Карточка Баланса --}}
        <div class="p-4 bg-muted/20 rounded-lg border border-border h-full flex flex-col justify-center">
            <h3 class="text-sm font-semibold mb-4 flex items-center gap-2">
                <x-lucide-wallet class="w-4 h-4" /> Текущий баланс
            </h3>
            
            <div class="flex items-center gap-6 flex-wrap">
                <div class="flex items-center gap-2">
                    <x-lucide-gem class="w-10 h-10 text-blue-500" />
                    <div class="flex flex-col">
                        <span class="text-2xl font-bold">{{ $this->user->balance?->credits ?? 0 }}</span>
                        <span class="text-xs text-muted-foreground">Кредитов</span>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <x-lucide-star class="w-10 h-10 text-yellow-500 fill-current" />
                    <div class="flex flex-col">
                        <span class="text-2xl font-bold">{{ $this->user->balance?->superlikes_remaining ?? 0 }}</span>
                        <span class="text-xs text-muted-foreground">Суперлайков</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Карточка Статус Подписки (Premium & VIP) --}}
        <div class="p-4 bg-muted/20 rounded-lg border border-border h-full flex flex-col justify-center">
            <h3 class="text-sm font-semibold mb-4 flex items-center gap-2">
                <x-lucide-crown class="w-4 h-4 text-yellow-500" /> Активные статусы
            </h3>
            
            <div class="flex flex-col gap-3">
                {{-- Premium --}}
                <div class="flex items-center gap-3">
                    @if($this->user->has_active_premium)
                        <x-ui.badge variant="success" size="sm">Premium</x-ui.badge>
                        @if($this->user->premium_expires_at)
                            <span class="text-xs text-muted-foreground">до {{ $this->user->premium_expires_at->format('d.m.Y H:i') }}</span>
                        @endif
                    @elseif($this->user->premium_expires_at && $this->user->premium_expires_at->isPast())
                        <x-ui.badge variant="destructive" size="sm">Premium истек</x-ui.badge>
                        <span class="text-xs text-muted-foreground">{{ $this->user->premium_expires_at->format('d.m.Y') }}</span>
                    @else
                        <x-ui.badge variant="secondary" size="sm">Premium нет</x-ui.badge>
                    @endif
                </div>

                {{-- VIP --}}
                <div class="flex items-center gap-3">
                    @if($this->user->has_active_vip)
                        <x-ui.badge variant="info" size="sm">VIP (Буст)</x-ui.badge>
                        @if($this->user->vip_expires_at)
                            <span class="text-xs text-muted-foreground">до {{ $this->user->vip_expires_at->format('d.m.Y H:i') }}</span>
                        @endif
                    @elseif($this->user->vip_expires_at && $this->user->vip_expires_at->isPast())
                        <x-ui.badge variant="destructive" size="sm">VIP истек</x-ui.badge>
                        <span class="text-xs text-muted-foreground">{{ $this->user->vip_expires_at->format('d.m.Y') }}</span>
                    @else
                        <x-ui.badge variant="secondary" size="sm">VIP нет</x-ui.badge>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Нижняя панель: Таблицы бок о бок --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">     

        {{-- Левая колонка: История транзакций --}}
        <div class="space-y-3">
            <h3 class="text-sm font-semibold flex items-center gap-2">
                <x-lucide-receipt class="w-4 h-4" /> Транзакции ({{ $this->transactions->total() }})
            </h3>

            @if($this->transactions->isEmpty())
                <div class="p-4 bg-muted/20 rounded-lg border border-border text-center text-xs text-muted-foreground">
                    Транзакций не найдено.
                </div>
            @else
                <x-ui.table>
                    <x-ui.table-header>
                        <x-ui.table-row>
                            <x-ui.table-head class="w-12">ID</x-ui.table-head>
                            <x-ui.table-head>Назначение</x-ui.table-head>
                            <x-ui.table-head>Сумма</x-ui.table-head>
                            <x-ui.table-head>Статус</x-ui.table-head>
                            <x-ui.table-head>Дата</x-ui.table-head>
                        </x-ui.table-row>
                    </x-ui.table-header>
                    <x-ui.table-body>
                        @foreach($this->transactions as $trans)
                            <x-ui.table-row wire:key="trans-{{ $trans->id }}">
                                <x-ui.table-cell class="text-xs font-mono whitespace-nowrap">
                                    <a href="{{ route('admin.finances.transactions', ['q' => $trans->id]) }}" wire:navigate class="text-blue-500 hover:underline" title="Открыть в журнале транзакций">
                                        #{{ $trans->id }}
                                    </a>
                                </x-ui.table-cell>
                                <x-ui.table-cell>
                                    <div class="flex flex-col">
                                        @if($trans->type === 'subscription')
                                            @php $tier = $trans->meta['tier'] ?? 'subscription'; @endphp
                                            <span class="text-sm font-medium">Покупка {{ $tier === 'vip' ? 'VIP' : 'Premium' }}</span>
                                            <span class="text-xs text-muted-foreground">
                                                {{ $trans->meta['plan_name'] ?? 'Тариф удален' }}
                                            </span>
                                        @elseif($trans->type === 'credits')
                                            <span class="text-sm font-medium">Покупка кредитов</span>
                                            <span class="text-xs text-muted-foreground">
                                                {{ $trans->credits_amount ?? 0 }} шт
                                            </span>
                                        @else
                                            <span class="text-sm text-muted-foreground">{{ ucfirst($trans->type) }}</span>
                                        @endif
                                    </div>
                                </x-ui.table-cell>
                                <x-ui.table-cell>
                                    @php
                                        $amountColor = match($trans->status) {
                                            'success' => 'text-green-500 font-semibold',
                                            'refunded' => 'text-red-500 font-semibold',
                                            'failed' => 'text-muted-foreground/50',
                                            'pending' => 'text-yellow-500',
                                            default => 'text-foreground'
                                        };
                                    @endphp
                                    <span class="text-sm {{ $amountColor }}">
                                        {{ $trans->amount }} ₽
                                    </span>
                                </x-ui.table-cell>
                                <x-ui.table-cell>
                                    @if($trans->status === 'success')
                                        <x-ui.badge variant="success" size="xs">Успешно</x-ui.badge>
                                    @elseif($trans->status === 'pending')
                                        <x-ui.badge variant="warning" size="xs">Ожидает</x-ui.badge>
                                    @elseif($trans->status === 'failed')
                                        <x-ui.badge variant="destructive" size="xs">Ошибка</x-ui.badge>
                                    @elseif($trans->status === 'refunded')
                                        <x-ui.badge variant="secondary" size="xs">Возврат</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="secondary" size="xs">{{ ucfirst($trans->status) }}</x-ui.badge>
                                    @endif
                                </x-ui.table-cell>
                                <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap">
                                    {{ $trans->created_at->format('d.m.y H:i') }}
                                </x-ui.table-cell>
                            </x-ui.table-row>
                        @endforeach
                    </x-ui.table-body>
                </x-ui.table>
                <div class="mt-2">{{ $this->transactions->links('partials.pagination') }}</div>
            @endif
        </div>

                {{-- Правая колонка: История подписок --}}
        <div class="space-y-3">
            <h3 class="text-sm font-semibold flex items-center gap-2">
                <x-lucide-history class="w-4 h-4" /> Подписки ({{ $this->subscriptions->total() }})
            </h3>

            @if($this->subscriptions->isEmpty())
                <div class="p-4 bg-muted/20 rounded-lg border border-border text-center text-xs text-muted-foreground">
                    Подписок не найдено.
                </div>
            @else
                <x-ui.table>
                    <x-ui.table-header>
                        <x-ui.table-row>
                            <x-ui.table-head class="w-12">ID</x-ui.table-head>
                            <x-ui.table-head>Тип / Тариф</x-ui.table-head>
                            <x-ui.table-head>Начало</x-ui.table-head>
                            <x-ui.table-head>Конец</x-ui.table-head>
                            <x-ui.table-head>Чек</x-ui.table-head>
                            <x-ui.table-head>Статус</x-ui.table-head>
                        </x-ui.table-row>
                    </x-ui.table-header>
                    <x-ui.table-body>
                        @foreach($this->subscriptions as $sub)
                            <x-ui.table-row wire:key="sub-{{ $sub->id }}">
                                <x-ui.table-cell class="text-xs font-mono whitespace-nowrap">
                                    <a href="{{ route('admin.finances.subscriptions', ['q' => $sub->plan_id]) }}" wire:navigate class="text-blue-500 hover:underline" title="Открыть в журнале подписок">
                                        #{{ $sub->plan_id }}
                                    </a>
                                </x-ui.table-cell>
                                <x-ui.table-cell>
                                    <div class="flex flex-col">
                                        <x-ui.badge variant="{{ $sub->tier === 'vip' ? 'info' : 'secondary' }}" size="xs">
                                            {{ $sub->tier === 'vip' ? 'VIP' : 'Premium' }}
                                        </x-ui.badge>
                                        <span class="text-sm font-medium mt-1">{{ $sub->plan?->name ?? 'Тариф удален' }}</span>
                                    </div>
                                </x-ui.table-cell>
                                <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap">
                                    {{ $sub->starts_at->format('d.m.y') }}
                                </x-ui.table-cell>
                                <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap">
                                    {{ $sub->ends_at->format('d.m.y') }}
                                </x-ui.table-cell>
                                <x-ui.table-cell class="text-xs text-muted-foreground font-mono whitespace-nowrap">
                                    @if($sub->transaction_id)
                                        <a href="{{ route('admin.finances.transactions', ['q' => $sub->transaction_id]) }}" wire:navigate class="text-blue-500 hover:underline" title="Найти чек об оплате">
                                            #{{ $sub->transaction_id }}
                                        </a>
                                    @else
                                        <span>-</span>
                                    @endif
                                </x-ui.table-cell>
                                <x-ui.table-cell>
                                    @if($sub->status === 'active')
                                        <x-ui.badge variant="success" size="xs">Активна</x-ui.badge>
                                    @elseif($sub->status === 'expired')
                                        <x-ui.badge variant="secondary" size="xs">Истекла</x-ui.badge>
                                    @elseif($sub->status === 'canceled')
                                        <x-ui.badge variant="warning" size="xs">Отменена</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="destructive" size="xs">Ошибка</x-ui.badge>
                                    @endif
                                </x-ui.table-cell>
                            </x-ui.table-row>
                        @endforeach
                    </x-ui.table-body>
                </x-ui.table>
                <div class="mt-2">{{ $this->subscriptions->links('partials.pagination') }}</div>
            @endif
        </div>
    </div>
</div>