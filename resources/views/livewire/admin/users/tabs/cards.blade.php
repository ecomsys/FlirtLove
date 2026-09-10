<?php

use App\Models\User;
use App\Models\UserCard;
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

    // НОВОЕ: Добавлено для консистентности с другими табами
    #[Computed]
    public function user(): User
    {
        return User::withTrashed()->findOrFail($this->userId);
    }

    #[Computed]
    public function cards()
    {
        return UserCard::where('user_id', $this->userId)
            ->withTrashed() // Показываем даже удаленные карты
            ->latest()
            ->paginate(15);
    }

    #[On('user-action-performed')] 
    public function refreshCards(): void
    {
        unset($this->cards);
    }
}; 
?>

<div class="space-y-4">
    @if($this->cards->isEmpty())
        <div class="p-4 bg-muted/20 rounded-lg border border-dashed border-border text-center text-xs text-muted-foreground">
            У пользователя нет привязанных банковских карт.
        </div>
    @else
        <x-ui.table>
            <x-ui.table-header>
                <x-ui.table-row>
                    <x-ui.table-head class="w-16">ID</x-ui.table-head>
                    <x-ui.table-head>Карта</x-ui.table-head>
                    <x-ui.table-head>Платежный шлюз</x-ui.table-head>
                    <x-ui.table-head>Срок</x-ui.table-head>
                    <x-ui.table-head>Статус</x-ui.table-head>
                    <x-ui.table-head>Привязана</x-ui.table-head>
                </x-ui.table-row>
            </x-ui.table-header>
            <x-ui.table-body>
                @foreach($this->cards as $card)
                    <x-ui.table-row wire:key="card-{{ $card->id }}" class="{{ $card->trashed() ? 'opacity-50' : '' }}">
                        <x-ui.table-cell class="text-xs font-mono text-muted-foreground">#{{ $card->id }}</x-ui.table-cell>
                        <x-ui.table-cell>
                            <div class="flex items-center gap-2">
                                <x-lucide-credit-card class="w-4 h-4 text-muted-foreground" />
                                <span class="text-sm font-medium uppercase">{{ $card->card_type ?? '—' }}</span>
                                <span class="text-sm font-mono">**** {{ $card->last4 ?? '—' }}</span>
                            </div>
                        </x-ui.table-cell>
                        <x-ui.table-cell class="text-xs">
                            <x-ui.badge variant="secondary" size="xs">{{ ucfirst($card->gateway ?? '—') }}</x-ui.badge>
                        </x-ui.table-cell>
                        <x-ui.table-cell class="text-xs text-muted-foreground">
                            @if($card->expiry_month && $card->expiry_year)
                                {{ $card->expiry_month }} / {{ $card->expiry_year }}
                                {{-- НОВОЕ: Бейдж истечения срока --}}
                                @if($card->isExpired())
                                    <x-ui.badge variant="destructive" size="xs" class="ml-1">Истекла</x-ui.badge>
                                @endif
                            @else
                                —
                            @endif
                        </x-ui.table-cell>
                        <x-ui.table-cell>
                            <div class="flex items-center gap-1.5">
                                @if($card->trashed())
                                    <x-ui.badge variant="destructive" size="xs">Удалена</x-ui.badge>
                                @else
                                    @if($card->is_default) <x-ui.badge variant="default" size="xs">По умолчанию</x-ui.badge> @endif
                                    @if($card->is_active) <x-ui.badge variant="success" size="xs">Активна</x-ui.badge> @else <x-ui.badge variant="secondary" size="xs">Неактивна</x-ui.badge> @endif
                                @endif
                            </div>
                        </x-ui.table-cell>
                        <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap">
                            {{ $card->created_at->format('d.m.Y') }}
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @endforeach
            </x-ui.table-body>
        </x-ui.table>
        <div class="mt-2">{{ $this->cards->links('partials.pagination') }}</div>
    @endif
</div>