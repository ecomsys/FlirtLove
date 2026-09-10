<?php

use App\Models\User;
use App\Models\PromoCodeUsage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component 
{
    use WithPagination;

    public int $userId;

    public function mount(int $userId): void
    {
        $this->userId = $userId;
    }

    // НОВОЕ: Добавлено для консистентности
    #[Computed]
    public function user(): User
    {
        return User::withTrashed()->findOrFail($this->userId);
    }

    #[Computed]
    public function usages()
    {
        return PromoCodeUsage::where('user_id', $this->userId)
            ->with(['promoCode', 'transaction'])
            ->latest()
            ->paginate(15);
    }

    #[On('user-action-performed')] 
    public function refreshUsages(): void
    {
        unset($this->usages);
    }
}; 
?>
<div class="space-y-4">
    @if($this->usages->isEmpty())
        <div class="p-4 bg-muted/20 rounded-lg border border-dashed border-border text-center text-xs text-muted-foreground">
            Пользователь не использовал промокоды.
        </div>
    @else
        <x-ui.table>
            <x-ui.table-header>
                <x-ui.table-row>
                    <x-ui.table-head class="w-16">ID</x-ui.table-head>
                    <x-ui.table-head>Промокод</x-ui.table-head>
                    <x-ui.table-head>Скидка / Бонус</x-ui.table-head>
                    <x-ui.table-head>Транзакция</x-ui.table-head>
                    <x-ui.table-head>Дата применения</x-ui.table-head>
                </x-ui.table-row>
            </x-ui.table-header>
            <x-ui.table-body>
                @foreach($this->usages as $usage)
                    <x-ui.table-row wire:key="usage-{{ $usage->id }}">
                        <x-ui.table-cell class="text-xs font-mono text-muted-foreground">#{{ $usage->id }}</x-ui.table-cell>
                        <x-ui.table-cell>
                            @if($usage->promoCode)
                                <span class="text-sm font-mono font-medium bg-muted px-2 py-0.5 rounded">{{ $usage->promoCode->code }}</span>
                            @else
                                <span class="text-sm font-mono text-muted-foreground italic">Код удален</span>
                            @endif
                        </x-ui.table-cell>
                        
                        <!-- ФИКС: Выводим снапшот скидки и корректно разделяем типы -->
                        <x-ui.table-cell class="text-xs">
                            <div class="flex flex-col gap-1">
                                @if($usage->promoCode)
                                    @if($usage->promoCode->discount_type === 'percent')
                                        <x-ui.badge variant="info" size="xs">-{{ $usage->promoCode->discount_value }}%</x-ui.badge>
                                    @elseif($usage->promoCode->discount_type === 'fixed')
                                        <x-ui.badge variant="info" size="xs">-{{ $usage->promoCode->discount_value }} ₽</x-ui.badge>
                                    @elseif($usage->promoCode->discount_type === 'premium')
                                        <x-ui.badge variant="warning" size="xs">+Premium ({{ $usage->promoCode->duration_days }} дн.)</x-ui.badge>
                                    @elseif($usage->promoCode->discount_type === 'vip')
                                        <x-ui.badge variant="warning" size="xs">+VIP ({{ $usage->promoCode->duration_days }} дн.)</x-ui.badge>
                                    @endif
                                @else
                                    <x-ui.badge variant="secondary" size="xs">Архив</x-ui.badge>
                                @endif
                                
                                @if($usage->applied_discount > 0)
                                    <span class="text-[10px] text-muted-foreground">Списано: {{ $usage->applied_discount }} ₽</span>
                                @endif
                            </div>
                        </x-ui.table-cell>
                        
                        <x-ui.table-cell class="text-xs">
                            @if($usage->transaction)
                                <a href="{{ route('admin.finances.transactions', ['q' => $usage->transaction->id]) }}" wire:navigate class="text-blue-500 hover:underline font-mono">
                                    #{{ $usage->transaction->id }} ({{ $usage->transaction->amount }} ₽)
                                </a>
                            @else
                                <span class="text-muted-foreground">—</span>
                            @endif
                        </x-ui.table-cell>
                        <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap">
                            {{ $usage->created_at->format('d.m.Y H:i') }}
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @endforeach
            </x-ui.table-body>
        </x-ui.table>
        <div class="mt-2">{{ $this->usages->links('partials.pagination') }}</div>
    @endif
</div>