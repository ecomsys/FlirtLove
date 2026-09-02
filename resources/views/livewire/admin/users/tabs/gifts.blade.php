<?php

use App\Models\User;
use App\Models\UserGift;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component 
{
    use WithPagination;

    public int $userId;

    // Фильтр: all, sent, received
    public string $giftFilter = 'all';

    #[Url(as: 'gift_page')] 
    public int $giftPage = 1;

    public function mount(int $userId): void
    {
        $this->userId = $userId;
    }

    public function setGiftFilter(string $filter): void
    {
        $this->giftFilter = $filter;
        $this->resetPage();
        unset($this->gifts);
    }

    #[Computed]
    public function user(): User
    {
        return User::withTrashed()->findOrFail($this->userId);
    }

    // Хелпер для жадной загрузки аватарок
    private function getAvatarQuery(): \Closure
    {
        return fn($q) => $q->withTrashed()->select('id', 'name', 'email', 'status', 'is_premium', 'premium_expires_at', 'last_seen')
            ->with(['photos' => fn($sq) => $sq->select('id', 'user_id', 'is_primary', 'status', 'path_thumb')->orderByDesc('is_primary')->limit(1)]);
    }

    // Единый источник подарков с фильтрацией
    #[Computed]
    public function gifts()
    {
        $query = UserGift::withTrashed();

        if ($this->giftFilter === 'sent') {
            $query->where('sender_id', $this->userId);
        } elseif ($this->giftFilter === 'received') {
            $query->where('receiver_id', $this->userId);
        } else {
            // Режим "Все" — ищем и отправленные, и полученные
            $query->where(function ($q) {
                $q->where('sender_id', $this->userId)
                  ->orWhere('receiver_id', $this->userId);
            });
        }

        return $query->with([
                'sender' => $this->getAvatarQuery(), 
                'receiver' => $this->getAvatarQuery(), 
                'gift:id,image_url'
            ])
            ->latest()
            ->paginate(15, ['*'], 'giftPage');
    }

    #[On('user-action-performed')] 
    public function refreshGifts(): void
    {
        unset($this->gifts);
    }
}; 
?>

<div class="space-y-4">

    {{-- Кнопки фильтров --}}
    <div class="flex flex-wrap gap-1.5 mb-2">
        <x-ui.button wire:click="setGiftFilter('all')" variant="{{ $giftFilter === 'all' ? 'default' : 'secondary' }}" size="sm">
            Все подарки
        </x-ui.button>
        <x-ui.button wire:click="setGiftFilter('sent')" variant="{{ $giftFilter === 'sent' ? 'default' : 'secondary' }}" size="sm">
            <x-lucide-arrow-up-right class="w-3.5 h-3.5" /> Отправленные
        </x-ui.button>
        <x-ui.button wire:click="setGiftFilter('received')" variant="{{ $giftFilter === 'received' ? 'default' : 'secondary' }}" size="sm">
            <x-lucide-arrow-down-left class="w-3.5 h-3.5" /> Полученные
        </x-ui.button>
    </div>

    @if($this->gifts->isEmpty())
        <div class="p-4 bg-muted/20 rounded-lg border border-border text-center text-xs text-muted-foreground">
            @if($giftFilter === 'sent')
                Пользователь еще никому не дарил подарков.
            @elseif($giftFilter === 'received')
                Пользователь не получал подарков.
            @else
                У пользователя нет подарков.
            @endif
        </div>
    @else
        <x-ui.table>
            <x-ui.table-header>
                <x-ui.table-row>
                    <x-ui.table-head class="w-16">ID</x-ui.table-head>
                    <x-ui.table-head>Подарок (снапшот)</x-ui.table-head>
                    <x-ui.table-head>Направление</x-ui.table-head>
                    <x-ui.table-head>Цена</x-ui.table-head>
                    <x-ui.table-head>Статус</x-ui.table-head>
                    <x-ui.table-head>Дата</x-ui.table-head>
                </x-ui.table-row>
            </x-ui.table-header>
            <x-ui.table-body>
                @foreach($this->gifts as $gift)
                    @php 
                        $isSent = $gift->sender_id === $this->userId;
                        $partner = $isSent ? $gift->receiver : $gift->sender;
                    @endphp
                    <x-ui.table-row wire:key="gift-{{ $gift->id }}" class="{{ $gift->trashed() ? 'opacity-50' : '' }}">
                        <x-ui.table-cell class="text-xs font-mono whitespace-nowrap">
                            <a href="{{ route('admin.finances.gifts', ['history_search' => $gift->id]) }}" wire:navigate class="text-blue-500 hover:underline font-medium" title="Найти в истории дарений">
                                #{{ $gift->id }}
                            </a>
                        </x-ui.table-cell>
                        
                        <x-ui.table-cell>
                            <div class="flex items-center gap-3">                               
                                    @if($gift->image_url)
                                        <div class="w-14 h-14 overflow-hidden rounded-md bg-muted">
                                            <x-media-image src="{{ $gift->image_url }}" alt="{{ $gift->snapshot_name ?? 'Подарок' }}" class="w-full h-full object-cover"/>
                                        </div>
                                    @else
                                        <div class="w-14 h-14 flex items-center justify-center rounded-md bg-muted border border-dashed border-border">
                                            <x-lucide-image-off class="w-4 h-4 text-muted-foreground/50" />
                                        </div>
                                    @endif                               
                                <div class="flex flex-col min-w-0">
                                    <span class="text-sm font-medium truncate">{{ $gift->snapshot_name ?? 'Подарок удален' }}</span>
                                    @if($gift->message)
                                        <span class="text-[10px] text-muted-foreground italic truncate max-w-[200px]" title="{{ $gift->message }}"><span class="text-white/40">Сообщение:</span> "{{ $gift->message }}"</span>
                                    @endif
                                </div>
                            </div>
                        </x-ui.table-cell>

                        <x-ui.table-cell>
                            <div class="flex items-center gap-2 min-w-[200px]">
                                @if($isSent)
                                    <div class="flex items-center gap-1 text-blue-500 text-xs font-medium shrink-0">
                                        <x-lucide-arrow-up-right class="w-4 h-4" /> Кому:
                                    </div>
                                @else
                                    <div class="flex items-center gap-1 text-destructive text-xs font-medium shrink-0">
                                        <x-lucide-arrow-down-left class="w-4 h-4" /> От кого:
                                    </div>
                                @endif

                                @if($partner)
                                    <a href="{{ route('admin.users.show', $partner->id) }}" wire:navigate class="flex items-center gap-2 group">
                                        <x-avatar src="{{ $partner->avatar_url }}" name="{{ $partner->name }}" size="xs" userId="{{ $partner->id }}" showStatus="true" :isOnline="$partner->is_online"/>
                                        <div class="flex flex-col min-w-0">
                                            <span class="text-sm font-medium group-hover:text-primary flex items-center gap-1.5">
                                                <x-user-status-sign :user="$partner" />
                                                {{ $partner->name }}
                                                @if($partner->has_active_premium)
                                                    <x-lucide-crown class="w-3 h-3 text-yellow-500" />
                                                @endif
                                            </span>
                                              <!-- ФИКС: Добавили email -->
                                            <span class="text-xs text-muted-foreground truncate">{{ $partner->email }}</span> 
                                        </div>
                                    </a>                                     
                                @else
                                    <span class="text-xs text-muted-foreground italic">Система/Удален</span>
                                @endif
                            </div>
                        </x-ui.table-cell>

                        <x-ui.table-cell>
                            <span class="text-sm font-medium text-yellow-600 dark:text-yellow-500">{{ $gift->snapshot_price ?? 0 }} 💎</span>
                        </x-ui.table-cell>

                        <x-ui.table-cell>
                            <div class="flex flex-col gap-1">
                                @if($isSent)
                                    @if($gift->is_private)
                                        <x-ui.badge variant="secondary" size="xs">Приватный</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="success" size="xs">Публичный</x-ui.badge>
                                    @endif
                                @else
                                    @if($gift->is_read)
                                        <x-ui.badge variant="secondary" size="xs">Прочитан</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="warning" size="xs">Не прочитан</x-ui.badge>
                                    @endif
                                @endif
                                
                                @if($gift->trashed())
                                    <x-ui.badge variant="destructive" size="xs">Скрыт юзером</x-ui.badge>
                                @endif
                            </div>
                        </x-ui.table-cell>

                        <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap">
                            {{ $gift->created_at->diffForHumans() }}
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @endforeach
            </x-ui.table-body>
        </x-ui.table>
        <div class="mt-2">{{ $this->gifts->links('partials.pagination') }}</div>
    @endif
</div>