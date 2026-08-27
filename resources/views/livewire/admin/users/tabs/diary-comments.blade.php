<?php

use App\Models\DiaryComment;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component 
{
    use WithPagination;

    public int $userId;

    #[Url(as: 'made_page')] 
    public int $madePage = 1;
    
    #[Url(as: 'received_page')] 
    public int $receivedPage = 1;

    public function mount(int $userId): void
    {
        $this->userId = $userId;
    }

    private function getAvatarQuery(): \Closure
    {
        return fn($q) => $q->withTrashed()->select('id', 'name', 'email', 'status', 'is_premium', 'premium_expires_at', 'last_seen')
            ->with(['photos' => fn($sq) => $sq->select('id', 'user_id', 'is_primary', 'status', 'path_thumb')->orderByDesc('is_primary')->limit(1)]);
    }

    #[Computed]
    public function commentsMade()
    {
        return DiaryComment::where('user_id', $this->userId)
            ->with([
                'diary' => fn($q) => $q->withTrashed()->select('id', 'title', 'user_id'), 
                'diary.user' => $this->getAvatarQuery()
            ])
            ->latest()
            ->paginate(5, ['*'], 'madePage');
    }

    #[Computed]
    public function commentsReceived()
    {
        return DiaryComment::whereHas('diary', fn($q) => $q->withTrashed()->where('user_id', $this->userId))
            ->with([
                'diary' => fn($q) => $q->withTrashed()->select('id', 'title'), 
                'user' => $this->getAvatarQuery()
            ])
            ->latest()
            ->paginate(5, ['*'], 'receivedPage');
    }

    #[On('user-action-performed')] 
    public function refreshComments(): void
    {
        unset($this->commentsMade);
        unset($this->commentsReceived);
    }
}; 
?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    
    {{-- ЛЕВАЯ КОЛОНКА: НАПИСАЛ САМ --}}
    <div class="space-y-4">
        <h3 class="text-sm font-semibold flex items-center gap-2">
            <x-lucide-message-circle class="w-4 h-4 text-blue-500" /> Написал сам ({{ $this->commentsMade->total() }})
        </h3>

        @if($this->commentsMade->isEmpty())
            <div class="p-4 bg-muted/20 rounded-lg border border-border text-center text-xs text-muted-foreground">
                Пользователь не оставлял комментариев в дневниках.
            </div>
        @else
            <x-ui.table>
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-ui.table-head class="w-16">ID</x-ui.table-head>
                        <x-ui.table-head>Текст / Статус</x-ui.table-head>
                        <x-ui.table-head>Запись</x-ui.table-head>
                    </x-ui.table-row>
                </x-ui.table-header>
                <x-ui.table-body>
                    @foreach($this->commentsMade as $comment)
                        <x-ui.table-row wire:key="made-{{ $comment->id }}">
                            <x-ui.table-cell class="text-xs font-mono whitespace-nowrap">
                                <a href="{{ route('admin.moderation.diary.comments', ['q' => $comment->id]) }}" wire:navigate class="text-blue-500 hover:underline" title="Найти в модерации">
                                    #{{ $comment->id }}
                                </a>
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                <div class="flex flex-col gap-1.5 max-w-[230px]">
                                    <p class="text-xs text-muted-foreground italic break-words whitespace-normal line-clamp-2">"{{ $comment->content }}"</p>
                                    <div class="flex items-center gap-1.5">
                                        @if($comment->status === 'approved') <x-ui.badge variant="success" size="xs">Одобрен</x-ui.badge> @endif
                                        @if($comment->status === 'pending') <x-ui.badge variant="warning" size="xs">Ожидает</x-ui.badge> @endif
                                        @if($comment->status === 'rejected') <x-ui.badge variant="destructive" size="xs">Отклонен</x-ui.badge> @endif
                                        @if($comment->status === 'spam') <x-ui.badge variant="destructive" size="xs">Спам</x-ui.badge> @endif
                                    </div>
                                </div>
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                @if($comment->diary)
                                    <div class="flex flex-col gap-1 min-w-0">
                                        <a href="{{ route('admin.moderation.diary.moderate', $comment->diary->id) }}" wire:navigate class="text-xs font-medium hover:text-primary line-clamp-1">
                                            {{ $comment->diary->title }}
                                        </a>
                                        @if($comment->diary->user)
                                            <div class="flex items-center gap-1 ">
                                                <span class="text-[10px] text-muted-foreground">Автор:</span>
                                                <a href="{{ route('admin.users.show', $comment->diary->user->id) }}" wire:navigate class="text-[10px] text-muted-foreground hover:text-primary flex items-center gap-1">
                                                    <x-user-status-sign :user="$comment->diary->user" />
                                                    <span class="truncate">{{ $comment->diary->user->name }}</span>
                                                    @if($comment->diary->user->has_active_premium)
                                                        <x-lucide-crown class="w-3 h-3 text-yellow-500" />
                                                    @endif
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-xs text-muted-foreground italic">Удалена</span>
                                @endif
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @endforeach
                </x-ui.table-body>
            </x-ui.table>
            <div class="mt-2">{{ $this->commentsMade->links('partials.pagination') }}</div>
        @endif
    </div>

    {{-- ПРАВАЯ КОЛОНКА: НА ЕГО ЗАПИСЯХ --}}
    <div class="space-y-4">
        <h3 class="text-sm font-semibold flex items-center gap-2">
            <x-lucide-inbox class="w-4 h-4 text-destructive" /> На его записях ({{ $this->commentsReceived->total() }})
        </h3>

        @if($this->commentsReceived->isEmpty())
            <div class="p-4 bg-muted/20 rounded-lg border border-border text-center text-xs text-muted-foreground">
                Под записями пользователя нет комментариев.
            </div>
        @else
            <x-ui.table>
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-ui.table-head class="w-16">ID</x-ui.table-head>
                        <x-ui.table-head>Автор</x-ui.table-head>
                        <x-ui.table-head>Текст / Статус</x-ui.table-head>
                    </x-ui.table-row>
                </x-ui.table-header>
                <x-ui.table-body>
                    @foreach($this->commentsReceived as $comment)
                        <x-ui.table-row wire:key="received-{{ $comment->id }}">
                            <x-ui.table-cell class="text-xs font-mono whitespace-nowrap">
                                <a href="{{ route('admin.moderation.diary.comments', ['q' => $comment->id]) }}" wire:navigate class="text-blue-500 hover:underline" title="Найти в модерации">
                                    #{{ $comment->id }}
                                </a>
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                @if($comment->user)
                                    <a href="{{ route('admin.users.show', $comment->user->id) }}" wire:navigate class="flex items-center gap-2 group">
                                        <x-avatar src="{{ $comment->user->avatar_url }}" name="{{ $comment->user->name }}" size="sm" userId="{{ $comment->user->id }}" showStatus="true" :isOnline="$comment->user->is_online"/>
                                        <div class="flex flex-col min-w-0">
                                            <span class="text-sm font-medium group-hover:text-primary flex items-center gap-1.5">
                                                <x-user-status-sign :user="$comment->user" />
                                                <span class="truncate">{{ $comment->user->name }}</span>
                                                @if($comment->user->has_active_premium)
                                                    <x-lucide-crown class="w-3 h-3 text-yellow-500" />
                                                @endif
                                            </span>
                                            <span class="text-xs text-muted-foreground truncate">{{ $comment->user->email }}</span>
                                        </div>
                                    </a>
                                @else
                                    <div class="flex items-center gap-2">
                                        <x-avatar name="Del" size="sm" />
                                        <span class="text-sm text-muted-foreground italic">Удален</span>
                                    </div>
                                @endif
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                <div class="flex flex-col gap-1.5 max-w-[230px]">
                                    <p class="text-xs text-muted-foreground italic break-words whitespace-normal line-clamp-2">"{{ $comment->content }}"</p>
                                    <div class="flex items-center gap-1.5">
                                        @if($comment->status === 'approved') <x-ui.badge variant="success" size="xs">Одобрен</x-ui.badge> @endif
                                        @if($comment->status === 'pending') <x-ui.badge variant="warning" size="xs">Ожидает</x-ui.badge> @endif
                                        @if($comment->status === 'rejected') <x-ui.badge variant="destructive" size="xs">Отклонен</x-ui.badge> @endif
                                        @if($comment->status === 'spam') <x-ui.badge variant="destructive" size="xs">Спам</x-ui.badge> @endif
                                    </div>
                                </div>
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @endforeach
                </x-ui.table-body>
            </x-ui.table>
            <div class="mt-2">{{ $this->commentsReceived->links('partials.pagination') }}</div>
        @endif
    </div>
</div>