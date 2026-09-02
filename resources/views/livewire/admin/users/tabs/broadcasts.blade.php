<?php

use App\Models\User;
use App\Models\Broadcast;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component 
{
    use WithPagination;

    public int $userId;

    // Фильтр: all, in_app, push, email
    public string $broadcastFilter = 'all';

    #[Url(as: 'broadcast_page')] 
    public int $broadcastPage = 1;

    public function mount(int $userId): void
    {
        $this->userId = $userId;
    }

    public function setBroadcastFilter(string $filter): void
    {
        $this->broadcastFilter = $filter;
        $this->resetPage();
        unset($this->broadcasts);
    }

    #[Computed]
    public function user(): User
    {
        return User::withTrashed()->findOrFail($this->userId);
    }

    #[Computed]
    public function broadcasts()
    {
        // Ищем рассылки, где в JSON target_audience есть ключ user_id равный ID нашего юзера
        return Broadcast::where('target_audience->user_id', $this->userId)
            ->when($this->broadcastFilter !== 'all', fn($q) => $q->where('type', $this->broadcastFilter))
            ->latest()
            ->paginate(15, ['*'], 'broadcastPage');
    }

    #[On('user-action-performed')] 
    public function refreshBroadcasts(): void
    {
        unset($this->broadcasts);
    }
}; 
?>

<div class="space-y-4">

    {{-- Кнопки фильтров по типу канала --}}
    <div class="flex flex-wrap gap-1.5 mb-2">
        <x-ui.button wire:click="setBroadcastFilter('all')" variant="{{ $broadcastFilter === 'all' ? 'default' : 'secondary' }}" size="sm">
            Все уведомления
        </x-ui.button>
        <x-ui.button wire:click="setBroadcastFilter('in_app')" variant="{{ $broadcastFilter === 'in_app' ? 'default' : 'secondary' }}" size="sm">
            <x-lucide-bell class="w-3.5 h-3.5" /> Site
        </x-ui.button>
        <x-ui.button wire:click="setBroadcastFilter('email')" variant="{{ $broadcastFilter === 'email' ? 'default' : 'secondary' }}" size="sm">
            <x-lucide-mail class="w-3.5 h-3.5" /> Email
        </x-ui.button>
        <x-ui.button wire:click="setBroadcastFilter('push')" variant="{{ $broadcastFilter === 'push' ? 'default' : 'secondary' }}" size="sm">
            <x-lucide-smartphone class="w-3.5 h-3.5" /> Push
        </x-ui.button>
    </div>

    @if($this->broadcasts->isEmpty())
        <div class="p-4 bg-muted/20 rounded-lg border border-border text-center text-xs text-muted-foreground">
            @if($broadcastFilter === 'in_app')
                Нет уведомлений в приложении.
            @elseif($broadcastFilter === 'email')
                Нет отправленных Email-писем.
            @elseif($broadcastFilter === 'push')
                Нет отправленных Push-уведомлений.
            @else
                Пользователь не получал рассылок.
            @endif
        </div>
    @else
        <x-ui.table>
            <x-ui.table-header>
                <x-ui.table-row>
                    <x-ui.table-head class="w-16">ID</x-ui.table-head>
                    <x-ui.table-head class="w-32">Тип</x-ui.table-head>
                    <x-ui.table-head>Содержание</x-ui.table-head>
                    <x-ui.table-head class="w-28">Статус</x-ui.table-head>
                    <x-ui.table-head class="w-32">Дата</x-ui.table-head>
                </x-ui.table-row>
            </x-ui.table-header>
            <x-ui.table-body>
                @foreach($this->broadcasts as $broadcast)
                    <x-ui.table-row wire:key="broadcast-{{ $broadcast->id }}">
                        
                        {{-- ID (Числовой, кликабельный) --}}
                        <x-ui.table-cell class="text-xs font-mono whitespace-nowrap">
                            <a href="{{ route('admin.system.broadcasts.index', ['q' => $broadcast->id]) }}" wire:navigate class="text-blue-500 hover:underline font-medium" title="Найти в журнале рассылок">
                                #{{ $broadcast->id }}
                            </a>
                        </x-ui.table-cell>
                        
                        {{-- Тип --}}
                        <x-ui.table-cell>
                            @if($broadcast->type === 'in_app')
                                <x-ui.badge variant="secondary" size="xs"><x-lucide-bell class="w-3 h-3 inline mr-1" />Site</x-ui.badge>
                            @elseif($broadcast->type === 'email')
                                <x-ui.badge variant="warning" size="xs"><x-lucide-mail class="w-3 h-3 inline mr-1" />Email</x-ui.badge>
                            @else
                                <x-ui.badge variant="info" size="xs"><x-lucide-smartphone class="w-3 h-3 inline mr-1" />Push</x-ui.badge>
                            @endif
                        </x-ui.table-cell>

                        {{-- Содержание (С ограничением и line-clamp-2) --}}
                        <x-ui.table-cell>
                            <div class="flex flex-col gap-1 max-w-[400px]">
                                @if($broadcast->title)
                                    <span class="text-sm font-medium line-clamp-1 truncate break-words">{{ $broadcast->title }}</span>
                                @endif
                                @if($broadcast->message)
                                    <span class="text-xs text-muted-foreground italic line-clamp-1 truncate break-words" title="{{ $broadcast->message }}">
                                        "{{ $broadcast->message }}"
                                    </span>
                                @endif
                            </div>
                        </x-ui.table-cell>

                        {{-- Статус отправки --}}                    

                             <x-ui.table-cell class="w-30">
                                @if ($broadcast->status === 'draft')
                                    <x-ui.badge variant="warning" size="sm">Черновик</x-ui.badge>
                                @elseif($broadcast->status === 'scheduled')
                                    <x-ui.badge variant="info" size="sm">Запланировано</x-ui.badge>
                                    <div class="text-xs text-muted-foreground mt-1">{{ $broadcast->scheduled_at?->format('d.m.Y H:i') }}</div>
                                @elseif($broadcast->status === 'sending')
                                    <div class="flex items-center gap-2 text-blue-500 font-medium text-sm mb-1">
                                        Отправка... ({{ $broadcast->progress }}%)
                                    </div>
                                    <div class="w-full bg-muted rounded-full h-1.5">
                                        <div class="bg-blue-500 h-1.5 rounded-full transition-all duration-500" style="width: {{ $broadcast->progress }}%"></div>
                                    </div>
                                    <div class="text-[10px] text-muted-foreground mt-1 text-center">
                                        {{ $broadcast->sent_count }} / {{ $broadcast->total_recipients }}
                                    </div>
                                @elseif($broadcast->status === 'sent')
                                    <x-ui.badge variant="success" size="sm">Отправлено</x-ui.badge>
                                    <div class="text-xs text-muted-foreground mt-1">
                                        {{ $broadcast->sent_count }} доставлено 
                                    </div>
                                    @if($broadcast->failed_count > 0)
                                        <div class="text-xs text-destructive">
                                            {{ $broadcast->failed_count }} ошибок
                                        </div>
                                    @endif
                                @elseif($broadcast->status === 'failed')
                                    <x-ui.badge variant="destructive" size="sm">Ошибка</x-ui.badge>
                                    <div class="text-xs text-destructive mt-1">
                                        Упало: {{ $broadcast->failed_count }}
                                    </div>
                                @endif
                            </x-ui.table-cell>

                        {{-- Дата --}}
                        <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap">
                            {{ ($broadcast->sent_at ?? $broadcast->created_at)->diffForHumans() }}
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @endforeach
            </x-ui.table-body>
        </x-ui.table>
        <div class="mt-2">{{ $this->broadcasts->links('partials.pagination') }}</div>
    @endif
</div>