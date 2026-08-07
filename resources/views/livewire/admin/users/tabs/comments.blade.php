<?php

use App\Enums\CommentRejectReason;
use App\Models\PhotoComment;
use App\Models\User;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component 
{
    use WithPagination;

    public User $user;

    // Раздельные страницы для независимой пагинации
    #[Url(as: 'made_page')] 
    public int $madePage = 1;
    
    #[Url(as: 'received_page')] 
    public int $receivedPage = 1;

    public function with(): array
    {
        $avatarQuery = fn($q) => $q->select('id', 'name', 'email', 'status', 'is_premium', 'premium_expires_at', 'last_seen')
            ->with(['photos' => fn($sq) => $sq->select('id', 'user_id', 'is_primary', 'status', 'path_thumb', 'path_medium', 'path_large', 'path_original')->orderByDesc('is_primary')->limit(1)]);

        // 1. Комменты, которые написал этот юзер (под чужими/своими фото)
        // ФИКС: Подгружаем пути к файлам фото (thumb, medium, original)
        $commentsMade = PhotoComment::where('user_id', $this->user->id)
            ->with(['photo:id,user_id,path_thumb,path_medium,path_original', 'photo.user' => $avatarQuery])
            ->latest()
            ->paginate(5, ['*'], 'madePage');

        // 2. Комменты, написанные под фото этого юзера (другими людьми)
        $commentsReceived = PhotoComment::whereHas('photo', fn($q) => $q->where('user_id', $this->user->id))
            ->with(['photo:id,user_id', 'user' => $avatarQuery])
            ->latest()
            ->paginate(5, ['*'], 'receivedPage');

        return [
            'commentsMade' => $commentsMade,
            'commentsReceived' => $commentsReceived
        ];
    }
}; 
?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    
    {{-- ЛЕВАЯ КОЛОНКА: НАПИСАЛ САМ --}}
    <div class="space-y-4">
        <h3 class="text-sm font-semibold flex items-center gap-2">
            <x-lucide-message-circle class="w-4 h-4 text-blue-500" /> Написал сам ({{ $commentsMade->total() }})
        </h3>

        @if($commentsMade->isEmpty())
            <div class="p-4 bg-muted/20 rounded-lg border border-border text-center text-xs text-muted-foreground">
                Пользователь не оставлял комментариев.
            </div>
        @else
            <x-ui.table>
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-ui.table-head class="w-16">ID</x-ui.table-head>
                        <x-ui.table-head>Текст / Статус</x-ui.table-head>
                        <x-ui.table-head>Оставлен под</x-ui.table-head>
                        <x-ui.table-head>Дата</x-ui.table-head>
                    </x-ui.table-row>
                </x-ui.table-header>
                <x-ui.table-body>
                    @foreach($commentsMade as $comment)
                        @php 
                            $statusBadge = match($comment->status) {
                                'pending' => ['variant' => 'warning', 'label' => 'Ожидает'],
                                'approved' => ['variant' => 'success', 'label' => 'Одобрен'],
                                'rejected' => ['variant' => 'destructive', 'label' => 'Отклонен'],
                                'spam' => ['variant' => 'destructive', 'label' => 'Спам'],
                                default => ['variant' => 'secondary', 'label' => $comment->status]
                            };
                            $reasonEnum = $comment->reject_reason ? CommentRejectReason::tryFrom($comment->reject_reason) : null;
                        @endphp
                        <x-ui.table-row wire:key="made-{{ $comment->id }}">
                            <x-ui.table-cell class="text-muted-foreground text-xs font-mono">
                                <a href="{{ route('admin.moderation.comments', ['q' => $comment->id]) }}" wire:navigate class="hover:text-primary" title="Найти в модерации">
                                    #{{ $comment->id }}
                                </a>
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                <div class="flex flex-col gap-1.5 max-w-[200px]">
                                    <p class="text-xs text-muted-foreground italic truncate">"{{ $comment->content }}"</p>
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <x-ui.badge variant="{{ $statusBadge['variant'] }}" size="xs">{{ $statusBadge['label'] }}</x-ui.badge>
                                        @if($reasonEnum)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $reasonEnum->color() }}">{{ $reasonEnum->label() }}</span>
                                        @endif
                                    </div>
                                </div>
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                @if($comment->photo)
                                    @php $photoSrc = $comment->photo->thumb_url ?: $comment->photo->medium_url ?: asset('images/no-image-placeholder.png'); @endphp
                                    <div class="flex items-center gap-2">
                                        <a href="{{ $comment->photo->original_url ?: $comment->photo->medium_url ?: '#' }}" data-fancybox="made-comments-gallery" class="block w-10 h-10 overflow-hidden bg-muted shrink-0">
                                            <img src="{{ $photoSrc }}" class="w-full h-full object-cover" alt="photo">
                                        </a>
                                       <div class="flex flex-col gap-0.5 min-w-0">
                                            <a href="{{ route('admin.moderation.photos', ['q' => $comment->photo->id]) }}" wire:navigate class="text-xs fomt-medium text-muted-foreground hover:text-primary" title="Найти в модерации">
                                                Фото #{{ $comment->photo->id }}
                                            </a>
                                            @if($comment->photo->user)
                                                <a href="{{ route('admin.users.show', $comment->photo->user->id) }}" wire:navigate class="text-xs font-medium hover:text-primary flex items-center gap-1">
                                                    <x-user-status-sign :user="$comment->photo->user" />
                                                    {{ $comment->photo->user->name }}
                                                </a>
                                            @else
                                                <span class="text-[10px] text-muted-foreground italic">Юзер удален</span>
                                            @endif
                                        </div>
                                    </div>
                                @else
                                    <span class="text-xs text-muted-foreground italic">Фото удалено</span>
                                @endif
                            </x-ui.table-cell>
                            <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap">
                                {{ $comment->created_at->diffForHumans() }}
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @endforeach
                </x-ui.table-body>
            </x-ui.table>
            <div class="mt-2">{{ $commentsMade->links('partials.pagination') }}</div>
        @endif
    </div>

    {{-- ПРАВАЯ КОЛОНКА: НА ЕГО ФОТО --}}
    <div class="space-y-4">
        <h3 class="text-sm font-semibold flex items-center gap-2">
            <x-lucide-inbox class="w-4 h-4 text-destructive" /> На его фото ({{ $commentsReceived->total() }})
        </h3>

        @if($commentsReceived->isEmpty())
            <div class="p-4 bg-muted/20 rounded-lg border border-border text-center text-xs text-muted-foreground">
                Под фото пользователя нет комментариев.
            </div>
        @else
            <x-ui.table>
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-ui.table-head class="w-16">ID</x-ui.table-head>
                        <x-ui.table-head>Автор</x-ui.table-head>
                        <x-ui.table-head>Текст / Статус</x-ui.table-head>
                        <x-ui.table-head>Дата</x-ui.table-head>
                    </x-ui.table-row>
                </x-ui.table-header>
                <x-ui.table-body>
                    @foreach($commentsReceived as $comment)
                        @php 
                            $statusBadge = match($comment->status) {
                                'pending' => ['variant' => 'warning', 'label' => 'Ожидает'],
                                'approved' => ['variant' => 'success', 'label' => 'Одобрен'],
                                'rejected' => ['variant' => 'destructive', 'label' => 'Отклонен'],
                                'spam' => ['variant' => 'destructive', 'label' => 'Спам'],
                                default => ['variant' => 'secondary', 'label' => $comment->status]
                            };
                            $reasonEnum = $comment->reject_reason ? CommentRejectReason::tryFrom($comment->reject_reason) : null;
                        @endphp
                        <x-ui.table-row wire:key="received-{{ $comment->id }}">
                            <x-ui.table-cell class="text-muted-foreground text-xs font-mono">
                                <a href="{{ route('admin.moderation.comments', ['q' => $comment->id]) }}" wire:navigate class="hover:text-primary" title="Найти в модерации">
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
                                                {{ $comment->user->name }}
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
                                <div class="flex flex-col gap-1.5 max-w-[200px]">
                                    <p class="text-xs text-muted-foreground italic truncate">"{{ $comment->content }}"</p>
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <x-ui.badge variant="{{ $statusBadge['variant'] }}" size="xs">{{ $statusBadge['label'] }}</x-ui.badge>
                                        @if($reasonEnum)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $reasonEnum->color() }}">{{ $reasonEnum->label() }}</span>
                                        @endif
                                    </div>
                                </div>
                            </x-ui.table-cell>
                            <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap">
                                {{ $comment->created_at->diffForHumans() }}
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @endforeach
                </x-ui.table-body>
            </x-ui.table>
            <div class="mt-2">{{ $commentsReceived->links('partials.pagination') }}</div>
        @endif
    </div>
     <script>
    document.addEventListener('livewire:navigated', () => {
        if (typeof Fancybox !== 'undefined') {
            Fancybox.defaults.Hash = false; 
            Fancybox.bind('[data-fancybox]'); 
        }
    });
    </script>
</div>