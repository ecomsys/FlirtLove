<?php

use App\Models\Chat;
use App\Models\AdminLog;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    public string $search = '';
    public ?int $activeChatId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->activeChatId = null;
    }

    public function selectChat(int $chatId): void
    {
        $this->activeChatId = $chatId;
    }

    public function deleteChat(int $chatId): void
    {
        $chat = Chat::find($chatId);
        if (!$chat) return;

        // В нашей архитектуре мы не удаляем чат физически, а скрываем его для юзеров
        // или помечаем как удаленный админом. Для базы делаем softDelete.
        $chat->delete(); 

        AdminLog::record('chat.delete', $chat, auth()->user());

        if ($this->activeChatId === $chatId) {
            $this->activeChatId = null;
        }

        $this->dispatch('show-toast', type: 'success', message: 'Чат удален');
    }

    public function with(): array
    {
        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

        // Список чатов (только приватные)
        $chats = Chat::where('type', 'private')
            ->whereHas('participants', fn($q) => $q->whereHas('user', fn($uq) => $uq->excludeStaff()))
            ->with([
                'participants.user', 
                'messages' => fn($q) => $q->latest()->limit(1) // Последнее сообщение для превью
            ])
            ->when($this->search, function ($query) use ($operator) {
                $query->whereHas('participants.user', function ($q) use ($operator) {
                    $q->where('name', $operator, "%{$this->search}%");
                });
            })
            ->orderByDesc('last_message_at')
            ->paginate(20);

        // Данные активного чата
        $activeChat = null;
        if ($this->activeChatId) {
            $activeChat = Chat::with([
                'participants.user', 
                'messages.sender'
            ])->find($this->activeChatId);
        }

        return [
            'chats' => $chats,
            'activeChat' => $activeChat,
        ];
    }
}; 
?>

<div class="space-y-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <h1 class="text-2xl font-semibold">Чаты пользователей</h1>

        <div class="relative w-72">
            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Поиск по имени участника..."
                class="pl-9 pr-3 py-2 text-sm bg-card border border-border rounded-lg focus:outline-none w-full" />
        </div>
    </div>

    <!-- Интерфейс чата (Список + Переписка) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 bg-card border border-border rounded-lg p-4 min-h-[calc(100vh-20rem)]">

        <!-- Левая панель: Список чатов -->
        <div wire:poll.15s class="lg:col-span-1 border-r border-border pr-4 flex flex-col h-[calc(100vh-17rem)]">
            <div class="flex-1 min-h-0 overflow-y-auto space-y-2 pr-1">
                @forelse ($chats as $chat)
                    @php 
                        // Достаем участников из новой связи
                        $participant1 = $chat->participants->get(0);
                        $participant2 = $chat->participants->get(1);
                        $user1 = $participant1?->user;
                        $user2 = $participant2?->user;
                        $lastMsg = $chat->messages->first();
                    @endphp

                    <div wire:click="selectChat({{ $chat->id }})"
                        class="p-3 rounded-lg cursor-pointer transition-colors {{ $this->activeChatId === $chat->id ? 'bg-primary/10 border border-primary/30' : 'bg-muted/50 hover:bg-muted border border-transparent' }}"
                        wire:key="chat-list-{{ $chat->id }}">
                        <div class="flex items-center gap-3">
                            <div class="relative shrink-0">
                                <x-avatar src="{{ $user1?->avatar_url }}" name="{{ $user1?->name }}" size="sm" />
                                <x-avatar src="{{ $user2?->avatar_url }}" name="{{ $user2?->name }}" size="sm"
                                    class="absolute -bottom-2 -right-2 w-6 h-6 rounded-full border-2 border-card" />
                            </div>
                            
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-start gap-2">
                                    <div class="flex flex-col gap-0.5 min-w-0">
                                        <div class="flex items-center gap-1">
                                            <span class="font-medium text-sm truncate">{{ $user1?->name ?? 'Удален' }}</span>
                                            @if($user1?->status === 'banned') <x-ui.badge variant="destructive" size="xs">Бан</x-ui.badge> @endif
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <span class="font-medium text-sm truncate">{{ $user2?->name ?? 'Удален' }}</span>
                                            @if($user2?->status === 'banned') <x-ui.badge variant="destructive" size="xs">Бан</x-ui.badge> @endif
                                        </div>
                                    </div>

                                    <div class="flex flex-col items-end gap-1 shrink-0">
                                        @if ($chat->last_message_at)
                                            <span class="text-[10px] text-muted-foreground whitespace-nowrap">{{ $chat->last_message_at->diffForHumans() }}</span>
                                        @endif
                                    </div>
                                </div>
                                <p class="text-xs text-muted-foreground truncate mt-1">
                                    @if ($lastMsg)
                                        @if ($lastMsg->type === 'system')
                                            <span class="text-blue-500 font-medium">Системное</span>
                                        @else
                                            {{ Str::limit($lastMsg->body, 30) }}
                                        @endif
                                    @else
                                        <span class="italic">Нет сообщений</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12 text-muted-foreground text-sm">
                        <p>Чаты не найдены</p>
                    </div>
                @endforelse
            </div>

            <div class="shrink-0 pt-4 mt-2 border-t border-border">
                {{ $chats->links('partials.pagination') }}
            </div>
        </div>

        <!-- Правая панель: Переписка -->
        <div class="lg:col-span-2 flex flex-col bg-muted/10 rounded-lg p-4">
            @if ($activeChat)
                @php 
                    $p1 = $activeChat->participants->get(0);
                    $p2 = $activeChat->participants->get(1);
                    $u1 = $p1?->user;
                    $u2 = $p2?->user;
                @endphp

                <!-- Шапка чата -->
                <div class="flex items-start justify-between border-b border-border pb-3 mb-4">
                    <div class="flex items-center gap-6">
                        <!-- User 1 -->
                        <div class="flex items-center gap-2">
                            <x-avatar src="{{ $u1?->avatar_url }}" name="{{ $u1?->name }}" size="sm" />
                            <a href="{{ route('admin.users.show', $u1?->id) }}" wire:navigate class="font-medium text-sm hover:text-primary">{{ $u1?->name }}</a>
                            @if($u1?->status === 'banned') <x-ui.badge variant="destructive" size="xs">Бан</x-ui.badge> @endif
                        </div>

                        <span class="text-muted-foreground">&</span>

                        <!-- User 2 -->
                        <div class="flex items-center gap-2">
                            <x-avatar src="{{ $u2?->avatar_url }}" name="{{ $u2?->name }}" size="sm" />
                            <a href="{{ route('admin.users.show', $u2?->id) }}" wire:navigate class="font-medium text-sm hover:text-primary">{{ $u2?->name }}</a>
                            @if($u2?->status === 'banned') <x-ui.badge variant="destructive" size="xs">Бан</x-ui.badge> @endif
                        </div>
                    </div>

                    <x-ui.button wire:click="deleteChat({{ $activeChat->id }})" wire:confirm="Удалить этот чат?" variant="destructive" size="sm">
                        <x-lucide-trash-2 class="w-4 h-4" />
                    </x-ui.button>
                </div>

                <!-- Лента сообщений -->
                <div wire:poll.10s x-data x-init="setTimeout(() => { $el.scrollTop = $el.scrollHeight; }, 50)"
                    class="flex-1 overflow-y-auto space-y-4 pr-2 max-h-[calc(100vh-23rem)] flex flex-col-reverse">
                    
                    @foreach ($activeChat->messages as $message)
                        @if ($message->type === 'system')
                            <div class="flex justify-center" wire:key="msg-{{ $message->id }}">
                                <div class="bg-blue-500/10 text-blue-600 text-xs font-medium px-4 py-2 rounded-lg text-center max-w-md border border-blue-500/20">
                                    {{ $message->body }}
                                </div>
                            </div>
                        @else
                            @php
                                // Определяем, кто отправитель (участник 1 или 2)
                                $isUser1 = $message->sender_id === $u1?->id;
                                $sender = $isUser1 ? $u1 : $u2;
                            @endphp
                            <div class="flex items-end gap-2 {{ $isUser1 ? 'justify-start' : 'justify-end' }}" wire:key="msg-{{ $message->id }}">
                                @if ($isUser1)
                                    <x-avatar src="{{ $sender?->avatar_url }}" name="{{ $sender?->name }}" size="xs" />
                                @endif
                                <div class="max-w-[70%]">
                                    <div class="text-left whitespace-pre-line break-words {{ $isUser1 ? 'bg-muted text-foreground' : 'bg-primary text-primary-foreground' }} rounded-2xl px-4 py-2 text-sm">
                                        {{ $message->body }}
                                    </div>
                                    <div class="text-[10px] text-muted-foreground mt-1 {{ $isUser1 ? 'text-left' : 'text-right' }}">
                                        {{ $message->created_at->format('d.m H:i') }}
                                    </div>
                                </div>
                                @if (!$isUser1)
                                    <x-avatar src="{{ $sender?->avatar_url }}" name="{{ $sender?->name }}" size="xs" />
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>
            @else
                <div class="flex-1 flex flex-col items-center justify-center text-muted-foreground">
                    <x-lucide-message-circle class="w-16 h-16 mb-4 opacity-20" />
                    <h3 class="text-lg font-medium">Выберите чат</h3>
                    <p class="text-sm">Нажмите на диалог слева, чтобы просмотреть переписку.</p>
                </div>
            @endif
        </div>
    </div>
</div>