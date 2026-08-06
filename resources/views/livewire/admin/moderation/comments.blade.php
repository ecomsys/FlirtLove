<?php

use App\Models\Photo;
use App\Models\PhotoComment;
use App\Models\AdminLog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    #[Url(as: 'status', except: 'pending')]
    public string $statusFilter = 'pending';
    
    public string $search = '';
    public int $perPage = 5;

    // Состояние модалки отклонения
    public ?int $rejectingCommentId = null;
    public string $rejectReason = '';

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    // === ДЕЙСТВИЯ ===

    public function approve(int $commentId): void
    {
        $comment = PhotoComment::find($commentId);
        if (!$comment) return;

        $comment->markAsApproved(auth()->id());
        AdminLog::record('comment.approve', $comment, auth()->user());

        $this->dispatch('show-toast', type: 'success', message: 'Комментарий одобрен');
    }

    public function openRejectModal(int $commentId): void
    {
        $this->rejectingCommentId = $commentId;
        $this->rejectReason = '';
    }

    public function rejectComment(): void
    {
        $this->validate(['rejectReason' => 'required|string']);

        $comment = PhotoComment::find($this->rejectingCommentId);
        if (!$comment) return;

        $comment->markAsRejected(auth()->id(), $this->rejectReason);
        AdminLog::record('comment.reject', $comment, auth()->user(), ['status' => 'pending'], ['status' => 'rejected', 'reason' => $this->rejectReason]);

        $this->rejectingCommentId = null;
        $this->rejectReason = '';
        $this->dispatch('show-toast', type: 'error', message: 'Комментарий отклонен');
    }

    public function markSpam(int $commentId): void
    {
        $comment = PhotoComment::find($commentId);
        if (!$comment) return;

        $comment->markAsSpam(auth()->id());
        AdminLog::record('comment.spam', $comment, auth()->user());

        $this->dispatch('show-toast', type: 'warning', message: 'Помечен как спам');
    }

    public function approveAllForPhoto(int $photoId): void
    {
        $count = PhotoComment::where('photo_id', $photoId)->where('status', 'pending')->count();
        
        PhotoComment::where('photo_id', $photoId)->where('status', 'pending')
            ->each(fn($c) => $c->markAsApproved(auth()->id()));

        AdminLog::record('comment.mass_approve', Photo::find($photoId), auth()->user(), null, ['count' => $count]);

        // ИСПРАВЛЕНО: двойные кавычки для парсинга $count
        $this->dispatch('show-toast', type: 'success', message: "Одобрено {$count} комментариев");
    }

    // === ВЫВОД ДАННЫХ ===

    public function with(): array
    {
        $counts = PhotoComment::whereHas('user', fn($q) => $q->excludeStaff())
            ->selectRaw("
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'spam' THEN 1 ELSE 0 END) as spam,
                COUNT(*) as total
            ")->first();

        $photos = Photo::whereHas('comments', function ($q) {
            $q->whereHas('user', fn($uq) => $uq->excludeStaff());
            
            if ($this->statusFilter !== 'all') {
                $q->where('status', $this->statusFilter);
            }

            if (!empty($this->search)) {
                $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
                $q->where('content', $operator, "%{$this->search}%")
                  ->orWhereHas('user', fn($sub) => $sub->where('name', $operator, "%{$this->search}%"));
            }
        })
        ->with([
            'user:id,name', 
            'comments' => function ($q) {
                $q->whereHas('user', fn($uq) => $uq->excludeStaff());
                
                if ($this->statusFilter !== 'all') {
                    $q->where('status', $this->statusFilter);
                }

                if (!empty($this->search)) {
                    $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
                    $q->where(function ($subQ) use ($operator) {
                        $subQ->where('content', $operator, "%{$this->search}%")
                             ->orWhereHas('user', fn($sub) => $sub->where('name', $operator, "%{$this->search}%"));
                    });
                }

                // ИСПРАВЛЕНО: Жадная загрузка photos для аватара, чтобы не было N+1
                $q->with(['user' => fn($uq) => $uq->with(['photos' => fn($puq) => $puq->orderByDesc('is_primary')->limit(1)])])->latest();
            }
        ])
        ->latest()
        ->paginate($this->perPage);

        return [
            'photos' => $photos,
            'pendingCount' => (int) ($counts->pending ?? 0),
            'approvedCount' => (int) ($counts->approved ?? 0),
            'rejectedCount' => (int) ($counts->rejected ?? 0),
            'spamCount' => (int) ($counts->spam ?? 0),
        ];
    }
}; 
?>

<div class="space-y-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <h1 class="text-2xl font-semibold">Модерация комментариев</h1>
        @if ($pendingCount > 0)
            <span class="bg-yellow-500/10 text-yellow-600 px-3 py-1 rounded-full text-sm font-medium">
                В очереди: {{ $pendingCount }}
            </span>
        @endif
    </div>

    <!-- Фильтры -->
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex flex-wrap gap-2">
            <x-ui.button wire:click="$set('statusFilter', 'pending')" variant="{{ $statusFilter == 'pending' ? 'default' : 'secondary' }}">
                Ожидают <x-ui.badge>{{ $pendingCount }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="$set('statusFilter', 'approved')" variant="{{ $statusFilter == 'approved' ? 'default' : 'secondary' }}">
                Одобрены <x-ui.badge>{{ $approvedCount }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="$set('statusFilter', 'rejected')" variant="{{ $statusFilter == 'rejected' ? 'default' : 'secondary' }}">
                Отклонены <x-ui.badge>{{ $rejectedCount }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="$set('statusFilter', 'spam')" variant="{{ $statusFilter == 'spam' ? 'default' : 'secondary' }}">
                Спам <x-ui.badge>{{ $spamCount }}</x-ui.badge>
            </x-ui.button>
        </div>

        <div class="ml-auto relative">
            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Текст или автор..."
                class="pl-9 pr-3 py-2 text-sm bg-card border border-border rounded-lg focus:outline-none w-64" />
        </div>
    </div>

    <!-- Список фото с комментариями -->
    @if($photos->isEmpty())
        <div class="bg-card border border-border rounded-lg p-16 text-center">
            <x-lucide-check-circle class="w-12 h-12 mx-auto text-muted-foreground mb-4" />
            <h3 class="text-lg font-medium">Очередь пуста!</h3>
            <p class="text-muted-foreground mt-1">Все комментарии проверены.</p>
        </div>
    @else
        <div class="space-y-6">
            @foreach ($photos as $photo)
                @php $pendingOnPhoto = $photo->comments->where('status', 'pending')->count(); @endphp

                <div wire:key="photo-{{ $photo->id }}" class="bg-card border border-border rounded-lg overflow-hidden">
                    <!-- Шапка фото -->
                    <div class="p-4 border-b border-border flex items-center justify-between bg-muted/20">
                        <div class="flex items-center gap-3">
                            <a href="{{ $photo->original_url ?: $photo->medium_url }}" data-fancybox="comment-photos" class="w-12 h-12 rounded-md overflow-hidden bg-muted shrink-0">
                                <!-- ИСПРАВЛЕНО: Fallback на original_url, если thumb пустой -->
                                <img src="{{ $photo->thumb_url ?: $photo->original_url }}" class="w-full h-full object-cover">
                            </a>
                            <div>
                                <p class="font-semibold text-sm">Фото #{{ $photo->id }}</p>
                                <p class="text-xs text-muted-foreground">От {{ $photo->user->name ?? 'Удален' }} • Комментариев: {{ $photo->comments->count() }}</p>
                            </div>
                        </div>

                        @if($pendingOnPhoto > 0)
                            <x-ui.button wire:click="approveAllForPhoto({{ $photo->id }})" variant="success" size="sm">
                                <x-lucide-check class="w-4 h-4" /> Одобрить все ({{ $pendingOnPhoto }})
                            </x-ui.button>
                        @endif
                    </div>

                    <!-- Список комментариев -->
                    <div class="divide-y divide-border">
                        @foreach ($photo->comments as $comment)
                            <div wire:key="comment-{{ $comment->id }}" class="p-4 flex items-start gap-3 {{ $comment->status === 'pending' ? 'bg-yellow-500/5' : '' }}">
                                <x-avatar src="{{ $comment->user?->avatar_url }}" name="{{ $comment->user?->name }}" size="sm" userId="{{ $comment->user?->id }}" :isOnline="$comment->user?->is_online" />
                                
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <a href="{{ route('admin.users.show', $comment->user_id) }}" wire:navigate class="font-medium text-sm hover:text-primary">
                                            {{ $comment->user?->name ?? 'Удален' }}
                                        </a>
                                        <span class="text-xs text-muted-foreground">{{ $comment->created_at->diffForHumans() }}</span>
                                        
                                        @php 
                                            // ИСПРАВЛЕНО: Безопасный инлайн-маппинг бейджа
                                            $statusMap = [
                                                'pending'  => ['variant' => 'warning', 'label' => 'Ожидает'],
                                                'approved' => ['variant' => 'success', 'label' => 'Одобрен'],
                                                'rejected' => ['variant' => 'destructive', 'label' => 'Отклонен'],
                                                'spam'     => ['variant' => 'secondary', 'label' => 'Спам'],
                                            ];
                                            $badge = $statusMap[$comment->status] ?? ['variant' => 'secondary', 'label' => 'Неизвестно'];
                                        @endphp
                                        <x-ui.badge variant="{{ $badge['variant'] }}" size="xs">{{ $badge['label'] }}</x-ui.badge>
                                    </div>
                                    <p class="text-sm mt-1">{{ $comment->content }}</p>
                                    
                                    @if($comment->reject_reason)
                                        <p class="text-xs text-destructive mt-1">Причина: {{ $comment->reject_reason }}</p>
                                    @endif
                                </div>

                                <!-- Кнопки действий -->
                                @if($comment->status === 'pending')
                                    <div class="flex gap-1 shrink-0">
                                        <x-ui.button wire:click="approve({{ $comment->id }})" variant="ghost" size="icon-xs" title="Одобрить"><x-lucide-check class="w-4 h-4 text-green-500" /></x-ui.button>
                                        <x-ui.button wire:click="openRejectModal({{ $comment->id }})" variant="ghost" size="icon-xs" title="Отклонить"><x-lucide-x class="w-4 h-4 text-yellow-500" /></x-ui.button>
                                        <x-ui.button wire:click="markSpam({{ $comment->id }})" variant="ghost" size="icon-xs" title="Спам"><x-lucide-alert-circle class="w-4 h-4 text-red-500" /></x-ui.button>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">{{ $photos->links('partials.pagination') }}</div>
    @endif

    <!-- МОДАЛКА ОТКЛОНЕНИЯ КОММЕНТАРИЯ -->
    <div wire:key="reject-comment-modal-{{ $rejectingCommentId ?? 'none' }}" x-data="{ show: @entangle('rejectingCommentId') }" x-show="show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4" style="display: none;">
        <div class="bg-card border border-border rounded-lg p-6 w-full max-w-md shadow-xl" @click.outside="$wire.rejectingCommentId = null">
            <h3 class="text-lg font-semibold mb-4">Причина отклонения комментария</h3>
            
            <div class="space-y-2 mb-4">
                <select wire:model="rejectReason" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm">
                    <option value="">Выберите причину...</option>
                    <option value="mat">Мат / Оскорбление</option>
                    <option value="spam">Спам / Реклама</option>
                    <option value="insult">Троллинг</option>
                    <option value="other">Другое</option>
                </select>
                @error('rejectReason') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end gap-2">
                <x-ui.button @click="$wire.rejectingCommentId = null" variant="outline">Отмена</x-ui.button>
                <x-ui.button wire:click="rejectComment" variant="destructive">Отклонить</x-ui.button>
            </div>
        </div>
    </div>
</div>

@script
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof Fancybox !== 'undefined') {
            Fancybox.defaults.Hash = false; 
            Fancybox.bind(document, '[data-fancybox]'); 
        }
    });
</script>
@endscript