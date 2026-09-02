<?php

use App\Models\ChatParticipant;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component 
{
    use WithPagination;

    public int $userId;

    #[Url(as: 'chat_page')] 
    public int $chatPage = 1;

    public function mount(int $userId): void
    {
        $this->userId = $userId;
    }

    #[Computed]
    public function user(): User
    {
        return User::withTrashed()->findOrFail($this->userId);
    }

    // Хелпер для жадной загрузки аватарок собеседников
    private function getAvatarQuery(): \Closure
    {
        return fn($q) => $q->withTrashed()->select('id', 'name', 'email', 'status', 'is_premium', 'premium_expires_at', 'last_seen')
            ->with(['photos' => fn($sq) => $sq->select('id', 'user_id', 'is_primary', 'status', 'path_thumb')->orderByDesc('is_primary')->limit(1)]);
    }

       #[Computed]
    public function userChats()
    {
        // Берем участие юзера в чатах, жадно грузим сам чат, других участников и последнее сообщение
        return ChatParticipant::where('user_id', $this->userId)
            ->with([
                // ФИКС: Убрали withTrashed() у чата, так как чаты не удаляются (soft deletes)
                'chat' => fn($q) => $q->select('id', 'type', 'last_message_at', 'is_locked'),
                'chat.participants' => fn($q) => $q->select('id', 'chat_id', 'user_id'), 
                // А вот юзеры могут быть удалены, тут withTrashed() остается!
                'chat.participants.user' => $this->getAvatarQuery(),
                'chat.messages' => fn($q) => $q->latest()->limit(1)->select('id', 'chat_id', 'sender_id', 'body', 'type')
            ])
            ->latest('last_read_at')
            ->paginate(10, ['*'], 'chatPage');
    }

    #[On('user-action-performed')] 
    public function refreshChats(): void
    {
        unset($this->userChats);
    }
}; 
?>

<div class="space-y-4">
    
    @if($this->userChats->isEmpty())
        <div class="p-4 bg-muted/20 rounded-lg border border-border text-center text-xs text-muted-foreground">
            Пользователь пока не начал ни одного диалога.
        </div>
    @else
        <x-ui.table>
            <x-ui.table-header>
                <x-ui.table-row>
                    <x-ui.table-head class="w-16">ID</x-ui.table-head>
                    <x-ui.table-head>Собеседник</x-ui.table-head>
                    <x-ui.table-head>Тип</x-ui.table-head>
                    <x-ui.table-head>Последнее сообщение</x-ui.table-head>
                    <x-ui.table-head>Статусы</x-ui.table-head>
                    <x-ui.table-head>Дата</x-ui.table-head>
                </x-ui.table-row>
            </x-ui.table-header>
            <x-ui.table-body>
                @foreach($this->userChats as $participant)
                    @php 
                        $chat = $participant->chat;
                        if (!$chat) continue;
                        
                        // Находим собеседника (исключаем ID текущего юзера)
                        $partnerParticipant = $chat->participants->firstWhere('user_id', '!=', $this->userId);
                        $partner = $partnerParticipant?->user;
                        
                        $lastMsg = $chat->messages->first();
                    @endphp
                    <x-ui.table-row wire:key="user-chat-{{ $participant->chat_id }}">
                        <x-ui.table-cell class="text-xs font-mono whitespace-nowrap">
                            @php 
                                // ФИКС: Передаем параметр 'q' вместо 'chat', чтобы он попал в поле поиска!
                                $chatRoute = $chat->type === 'support' 
                                    ? route('admin.communication.support', ['q' => $chat->id]) 
                                    : route('admin.communication.chats', ['q' => $chat->id]);
                            @endphp
                            <a href="{{ $chatRoute }}" wire:navigate class="text-blue-500 hover:underline font-medium" title="Открыть переписку">
                                #{{ $chat->id }}
                            </a>
                        </x-ui.table-cell>
                        
                        <x-ui.table-cell>
                            @if($partner)
                                <a href="{{ route('admin.users.show', $partner->id) }}" wire:navigate class="flex items-center gap-2 group">
                                    <x-avatar src="{{ $partner->avatar_url }}" name="{{ $partner->name }}" size="sm" userId="{{ $partner->id }}" showStatus="true" :isOnline="$partner->is_online"/>
                                    <div class="flex flex-col min-w-0">
                                        <span class="text-sm font-medium group-hover:text-primary flex items-center gap-1.5">
                                            <x-user-status-sign :user="$partner" />
                                            <span class="truncate">{{ $partner->name }}</span>
                                            @if($partner->has_active_premium)
                                                <x-lucide-crown class="w-3 h-3 text-yellow-500" />
                                            @endif
                                        </span>
                                        <span class="text-xs text-muted-foreground truncate">{{ $partner->email }}</span>
                                    </div>
                                </a>
                            @else
                                <div class="flex items-center gap-2">
                                    <x-avatar name="Del" size="sm" />
                                    <span class="text-sm text-muted-foreground italic">Собеседник удален</span>
                                </div>
                            @endif
                        </x-ui.table-cell>

                        <x-ui.table-cell>
                            @if($chat->type === 'support')
                                <x-ui.badge variant="info" size="xs">Поддержка</x-ui.badge>
                            @else
                                <x-ui.badge variant="secondary" size="xs">Приватный</x-ui.badge>
                            @endif
                        </x-ui.table-cell>

                        <x-ui.table-cell>
                            <div class="flex flex-col max-w-[200px]">
                                @if($lastMsg)
                                    <p class="text-xs text-muted-foreground italic break-words whitespace-normal line-clamp-1">
                                        "{{ $lastMsg->type === 'system' ? 'Системное сообщение' : $lastMsg->body }}"
                                    </p>
                                @else
                                    <span class="text-xs text-muted-foreground/50 italic">Нет сообщений</span>
                                @endif
                            </div>
                        </x-ui.table-cell>

                        <x-ui.table-cell>
                            <div class="flex flex-wrap items-center gap-1.5">
                                @if($participant->unread_count > 0)
                                    <x-ui.badge variant="destructive" size="xs">{{ $participant->unread_count }} непроч.</x-ui.badge>
                                @endif
                                @if($participant->is_hidden)
                                    <x-ui.badge variant="secondary" size="xs" title="Чат скрыт (в архиве)"><x-lucide-eye-off class="w-3 h-3 inline" /> Архив</x-ui.badge>
                                @endif
                                @if($participant->is_muted)
                                    <x-ui.badge variant="warning" size="xs" title="Уведомления отключены"><x-lucide-bell-off class="w-3 h-3 inline" /> Мьют</x-ui.badge>
                                @endif
                                @if($participant->is_blocked)
                                    <x-ui.badge variant="destructive" size="xs" title="Юзер заблокировал собеседника"><x-lucide-ban class="w-3 h-3 inline" /> Блок</x-ui.badge>
                                @endif
                                @if($chat->is_locked)
                                    <x-ui.badge variant="destructive" size="xs" title="Чат заблокирован администрацией"><x-lucide-lock class="w-3 h-3 inline" />Чат заблок.</x-ui.badge>
                                @endif
                            </div>
                        </x-ui.table-cell>

                        <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap">
                            {{ $chat->last_message_at ? $chat->last_message_at->diffForHumans() : '—' }}
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @endforeach
            </x-ui.table-body>
        </x-ui.table>
        <div class="mt-2">{{ $this->userChats->links('partials.pagination') }}</div>
    @endif
</div>