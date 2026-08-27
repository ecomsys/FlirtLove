<?php

use App\Actions\Admin\ModerateDiaryCommentAction;
use App\Enums\CommentRejectReason;
use App\Models\Diary;
use App\Models\DiaryComment;
use App\Models\AdminLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Enum;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: 'pending')]
    public string $statusFilter = 'pending';
    
    public int $perPage = 5; 

    public string $backUrl = '';
    
    public function mount(): void
    {
        $previousUrl = url()->previous();
        $this->backUrl = ($previousUrl && $previousUrl !== url()->current()) 
            ? $previousUrl 
            : route('admin.moderation.diary.index');

        if (!empty($this->search) && is_numeric($this->search)) {
            $comment = DiaryComment::find((int) $this->search);
            if ($comment) {
                $this->statusFilter = $comment->status;
            } else {
                $this->statusFilter = 'all';
            }
        } elseif (!empty($this->search)) {
            $this->statusFilter = 'all';
        }
    }

    public function updatedSearch(): void 
    { 
        $this->resetPage(); 

        if (is_numeric($this->search) && !empty($this->search)) {
            $comment = DiaryComment::find((int) $this->search);
            if ($comment) {
                $this->statusFilter = $comment->status;
                return;
            }
        }
        
        if (!empty($this->search) && $this->statusFilter !== 'all') {
            $this->statusFilter = 'all';
        }
    }
    
    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->search = '';
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function approveComment(int $commentId, ModerateDiaryCommentAction $action): void
    {
        $comment = DiaryComment::with('parent')->find($commentId);
        if (!$comment) return;

        $success = $action->approve($comment, auth()->user());
        if (!$success) {
            $this->dispatch('show-toast', type: 'error', message: 'Нельзя одобрить ответ на неодобренный комментарий!');
            return;
        }
        $this->dispatch('show-toast', type: 'success', message: 'Комментарий одобрен');
    }

    public function rejectComment(int $commentId, string $reason, ModerateDiaryCommentAction $action): void
    {
        $comment = DiaryComment::find($commentId);
        if (!$comment) return;

        $action->reject($comment, auth()->user(), $reason);
        $this->dispatch('show-toast', type: 'info', message: 'Комментарий отклонен');
    }

    public function markSpam(int $commentId, ModerateDiaryCommentAction $action): void
    {
        $comment = DiaryComment::find($commentId);
        if (!$comment) return;

        $action->markSpam($comment, auth()->user());
        $this->dispatch('show-toast', type: 'error', message: 'Комментарий помечен как спам');
    }

    public function restoreComment(int $commentId, ModerateDiaryCommentAction $action): void
    {
        $comment = DiaryComment::find($commentId);
        if (!$comment) return;

        $action->restore($comment, auth()->user());
        $this->dispatch('show-toast', type: 'info', message: 'Комментарий возвращен на модерацию');
    }

    public function getStatusBadge(string $status): array
    {
        return match ($status) {
            'pending' => ['variant' => 'warning', 'label' => 'Ожидает'],
            'approved' => ['variant' => 'success', 'label' => 'Одобрен'],
            'rejected' => ['variant' => 'destructive', 'label' => 'Отклонен'],
            'spam' => ['variant' => 'destructive', 'label' => 'Спам'],
            default => ['variant' => 'secondary', 'label' => 'Неизвестно'],
        };
    }

    #[Computed]
    public function diaries()
    {
        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
        $avatarQuery = fn($q) => $q->select(['id', 'user_id', 'is_primary', 'status', 'path_thumb', 'path_medium', 'path_large', 'path_original'])->orderByDesc('is_primary')->limit(1);

        $diaries = Diary::query()
            ->whereHas('comments', fn($q) => $this->applyCommentFilters($q))
            ->with([
                'user' => fn($q) => $q->withTrashed()->select('id', 'name', 'status', 'is_premium', 'premium_expires_at', 'is_verified', 'last_seen', 'deleted_at')->with(['photos' => $avatarQuery]), 
                'comments' => function ($q) use ($avatarQuery) {
                    $this->applyCommentFilters($q);
                    $q->with([
                        'user' => fn($uq) => $uq->withTrashed()->select('id', 'name', 'status', 'is_premium', 'premium_expires_at', 'is_verified', 'last_seen', 'deleted_at')->with(['photos' => $avatarQuery]),
                        'replies' => function ($q) use ($avatarQuery) {
                            if ($this->statusFilter !== 'all') {
                                $q->where('status', $this->statusFilter);
                            }
                            $q->with(['parent:id,status', 'user' => fn($uq) => $uq->withTrashed()->select('id', 'name', 'status', 'is_premium', 'premium_expires_at', 'is_verified', 'last_seen', 'deleted_at')->with(['photos' => $avatarQuery])])->latest();
                        },
                    ])->latest();
                },
            ]);

        if (!empty($this->search)) {
            $diaries->where(function ($q) use ($operator) {
                $search = $this->search;
                
                // 1. Ищем по названию дневника
                $q->where('title', $operator, "%{$search}%")
                  // 2. Ищем по имени автора дневника (вкл. удаленных)
                  ->orWhereHas('user', fn($uq) => $uq->withTrashed()->where('name', $operator, "%{$search}%"))
                  // 3. Ищем по имени автора комментария (вкл. удаленных)
                  ->orWhereHas('comments.user', fn($cuq) => $cuq->withTrashed()->where('name', $operator, "%{$search}%"))
                  // 4. Ищем по имени автора ответа (вкл. удаленных)
                  ->orWhereHas('comments.replies.user', fn($ruq) => $ruq->withTrashed()->where('name', $operator, "%{$search}%"));
                
                // 5. Если ввели цифры, ищем по ID комментария или ответа
                if (is_numeric($search)) {
                    $q->orWhereHas('comments', fn($cq) => $cq->where('id', (int)$search))
                      ->orWhereHas('comments.replies', fn($rq) => $rq->where('id', (int)$search));
                }
            });
        }
        
        return $diaries->latest()->paginate($this->perPage);
    }

    private function applyCommentFilters($query)
    {
        $query->whereNull('parent_id');
        
        if ($this->statusFilter !== 'all') {
            $query->where(function ($sub) {
                $sub->where('status', $this->statusFilter)
                    ->orWhereHas('replies', fn($r) => $r->where('status', $this->statusFilter));
            });
        }
    }

    #[Computed]
    public function counts(): array
    {
        $stats = DiaryComment::selectRaw("
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'spam' THEN 1 ELSE 0 END) as spam,
            COUNT(*) as total
        ")->first();

        return [
            'pending' => (int) ($stats->pending ?? 0),
            'approved' => (int) ($stats->approved ?? 0),
            'rejected' => (int) ($stats->rejected ?? 0),
            'spam' => (int) ($stats->spam ?? 0),
            'total' => (int) ($stats->total ?? 0),
        ];
    }
}; 
?>

<div class="space-y-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-4">
            <a href="{{ $backUrl }}" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-semibold flex items-center gap-2">
                    <x-lucide-message-square-text class="w-6 h-6" />
                    Комментарии к дневникам
                </h1>
                <p class="text-sm text-muted-foreground">Модерация и ответы на отзывы пользователей</p>
            </div>
        </div>
    </div>

    <!-- Фильтры -->
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex flex-wrap gap-1.5">
            <x-ui.button wire:click="setStatusFilter('all')" variant="{{ $statusFilter === 'all' ? 'default' : 'secondary' }}" size="sm">
                Все <x-ui.badge size="xs">{{ $this->counts['total'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="setStatusFilter('pending')" variant="{{ $statusFilter === 'pending' ? 'default' : 'secondary' }}" size="sm">
                Ожидают <x-ui.badge size="xs" variant="warning">{{ $this->counts['pending'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="setStatusFilter('approved')" variant="{{ $statusFilter === 'approved' ? 'default' : 'secondary' }}" size="sm">
                Одобрены <x-ui.badge size="xs" variant="success">{{ $this->counts['approved'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="setStatusFilter('rejected')" variant="{{ $statusFilter === 'rejected' ? 'default' : 'secondary' }}" size="sm">
                Отклонены <x-ui.badge size="xs" variant="destructive">{{ $this->counts['rejected'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="setStatusFilter('spam')" variant="{{ $statusFilter === 'spam' ? 'default' : 'secondary' }}" size="sm">
                Спам <x-ui.badge size="xs" variant="destructive">{{ $this->counts['spam'] }}</x-ui.badge>
            </x-ui.button>
        </div>

        <div class="flex items-center gap-2 ml-auto">
            <div class="relative w-64">
                <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground z-10" />
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск по тексту или id..." class="pl-9 pr-8" />
                @if (!empty($search))
                    <button wire:click="clearSearch" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground z-10">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                @endif
            </div>
        </div>
    </div>

    @if ($this->diaries->isEmpty())
        <div class="bg-card border border-border rounded-lg p-16 text-center">
            <div class="w-16 h-16 mx-auto rounded-full bg-muted flex items-center justify-center mb-4">
                <x-lucide-check-circle class="w-8 h-8 text-muted-foreground" />
            </div>
            <h3 class="text-lg font-medium">Комментариев не найдено</h3>
            <p class="text-muted-foreground mt-1">Все проверки пройдены.</p>
        </div>
    @else
        <div class="space-y-6">
            @foreach ($this->diaries as $diary)
                <div class="bg-card border border-border rounded-xl shadow-sm overflow-hidden flex flex-col" wire:key="diary-{{ $diary->id }}">
                    <div class="p-4 bg-muted/30 border-b border-border flex items-center justify-between gap-4 flex-wrap">
                        <div class="flex items-center gap-3">
                            <x-avatar src="{{ $diary->user?->avatar_url }}" name="{{ $diary->user?->name }}" size="sm" userId="{{ $diary->user?->id }}" showStatus="true" :isOnline="$diary->user?->is_online" />
                            <div>
                                {{-- ФИКС: Защита от 500 ошибки, если автор дневника удален --}}
                                @if($diary->user)
                                    <a href="{{ route('admin.users.show', $diary->user->id) }}" wire:navigate class="font-semibold text-foreground hover:text-primary flex items-center gap-2">
                                        <span>
                                            <x-user-status-sign :user="$diary->user" />
                                            {{ $diary->user->name }}
                                        </span>
                                        @if($diary->user->has_active_premium)
                                            <x-lucide-crown class="w-4 h-4 text-yellow-500" />
                                        @endif
                                    </a>
                                @else
                                    <span class="font-semibold text-muted-foreground flex items-center gap-2">Удален</span>
                                @endif
                                <div class="text-xs text-muted-foreground mt-1">
                                    Запись: <a href="{{ route('admin.moderation.diary.moderate', $diary->id) }}" wire:navigate class="font-medium text-foreground/80 hover:text-primary transition-colors">{{ $diary->title }}</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 grid grid-cols-1 gap-3 flex-1 bg-card">
                        @foreach ($diary->comments as $comment)
                            @php 
                                $commentDimmed = $this->statusFilter !== 'all' && $comment->status !== $this->statusFilter; 
                                $rejectEnum = $comment->reject_reason ? \App\Enums\CommentRejectReason::tryFrom($comment->reject_reason) : null;
                                $isHighlighted = is_numeric($this->search) && $comment->id == (int)$this->search;      
                            @endphp

                            <div 
                                class="flex items-start gap-3 p-3 {{ $comment->status === 'pending' ? 'bg-yellow-500/5 border border-yellow-500/20' : 'bg-muted/10 border border-border' }} rounded-lg {{ $commentDimmed ? 'opacity-50' : '' }} {{ $isHighlighted ? 'ring-4 ring-primary/60 shadow-lg' : '' }}" 
                                wire:key="comment-{{ $comment->id }}-{{ $comment->status }}"
                                @if($isHighlighted) x-data x-init="setTimeout(() => { $el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 100)" @endif
                            >
                                <x-avatar src="{{ $comment->user?->avatar_url }}" name="{{ $comment->user?->name }}" size="sm" userId="{{ $comment->user?->id }}" showStatus="true" :isOnline="$comment->user?->is_online" />

                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-[10px] text-muted-foreground font-mono py-0.5 px-1 rounded-sm bg-muted">#{{ $comment->id }}</span>
                                        
                                        @if($comment->user)
                                            <x-user-status-sign :user="$comment->user" />
                                            <a href="{{ route('admin.users.show', $comment->user->id) }}" wire:navigate class="font-medium text-sm hover:text-primary">{{ $comment->user->name }}</a>
                                            @if($comment->user->has_active_premium)<x-lucide-crown class="w-3 h-3 text-yellow-500" />@endif
                                        @else
                                            <span class="font-medium text-sm text-muted-foreground">Удален</span>
                                        @endif
                                        <span class="text-xs text-muted-foreground">{{ $comment->created_at->diffForHumans() }}</span>
                                        @php $badge = $this->getStatusBadge($comment->status); @endphp
                                        <x-ui.badge variant="{{ $badge['variant'] }}" size="xs">{{ $badge['label'] }}</x-ui.badge>
                                        
                                        @if($rejectEnum)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $rejectEnum->color() }}">{{ $rejectEnum->label() }}</span>
                                        @endif
                                    </div>
                                    <p class="text-sm mt-0.5 text-foreground/90 break-words">{{ $comment->content }}</p>
                                </div>

                                <div class="flex gap-1 shrink-0">
                                    @if ($comment->status === 'pending')
                                        <x-ui.button wire:click="approveComment({{ $comment->id }})" wire:target="approveComment({{ $comment->id }})" variant="ghost" size="icon-xs" title="Одобрить">
                                            <x-lucide-check class="w-4 h-4 text-green-500" wire:loading.remove wire:target="approveComment({{ $comment->id }})" />
                                            <x-lucide-loader-2 class="w-4 h-4 animate-spin hidden" wire:loading wire:target="approveComment({{ $comment->id }})" />
                                        </x-ui.button>
                                        
                                        <x-ui.dropdown-menu>
                                            <x-ui.dropdown-menu-trigger>
                                                <x-ui.button variant="ghost" size="icon-xs" title="Отклонить"><x-lucide-x class="w-4 h-4 text-yellow-500" /></x-ui.button>
                                            </x-ui.dropdown-menu-trigger>
                                            <x-ui.dropdown-menu-content align="end">
                                                <x-ui.dropdown-menu-label>Причина отклонения</x-ui.dropdown-menu-label>
                                                <x-ui.dropdown-menu-separator></x-ui.dropdown-menu-separator>
                                                @foreach (\App\Enums\CommentRejectReason::options() as $value => $label)
                                                    <x-ui.dropdown-menu-item wire:click="rejectComment({{ $comment->id }}, '{{ $value }}')">{{ $label }}</x-ui.dropdown-menu-item>
                                                @endforeach
                                            </x-ui.dropdown-menu-content>
                                        </x-ui.dropdown-menu>

                                        <x-ui.button wire:click="markSpam({{ $comment->id }})" wire:target="markSpam({{ $comment->id }})" variant="ghost" size="icon-xs" title="Спам">
                                            <x-lucide-alert-circle class="w-4 h-4 text-red-500" wire:loading.remove wire:target="markSpam({{ $comment->id }})" />
                                            <x-lucide-loader-2 class="w-4 h-4 animate-spin hidden" wire:loading wire:target="markSpam({{ $comment->id }})" />
                                        </x-ui.button>
                                    @elseif($comment->status !== 'approved')
                                        <x-ui.button wire:click="restoreComment({{ $comment->id }})" wire:target="restoreComment({{ $comment->id }})" variant="ghost" size="icon-xs" title="Восстановить">
                                            <x-lucide-rotate-ccw class="w-4 h-4 text-blue-500" wire:loading.remove wire:target="restoreComment({{ $comment->id }})" />
                                            <x-lucide-loader-2 class="w-4 h-4 animate-spin hidden" wire:loading wire:target="restoreComment({{ $comment->id }})" />
                                        </x-ui.button>
                                    @endif
                                </div>
                            </div>

                            @if ($comment->replies->count() > 0)
                                <div class="pl-12 border-l-2 border-border space-y-2 -mt-2 ml-4">
                                    @foreach ($comment->replies as $reply)
                                        @php 
                                            $replyDimmed = $this->statusFilter !== 'all' && $reply->status !== $this->statusFilter;
                                            $replyRejectEnum = $reply->reject_reason ? \App\Enums\CommentRejectReason::tryFrom($reply->reject_reason) : null;
                                            $isReplyHighlighted = is_numeric($this->search) && $reply->id == (int)$this->search;
                                        @endphp
                                        <div 
                                            class="flex items-start gap-2 p-2 {{ $reply->status === 'pending' ? 'bg-yellow-500/5 border border-yellow-500/20' : 'bg-muted/5 border border-border' }} rounded-lg {{ $replyDimmed ? 'opacity-50' : '' }} {{ $isReplyHighlighted ? 'ring-4 ring-primary/60 shadow-lg' : '' }}" 
                                            wire:key="reply-{{ $reply->id }}-{{ $reply->status }}"
                                            @if($isReplyHighlighted) x-data x-init="setTimeout(() => { $el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 100)" @endif
                                        >
                                            <x-avatar src="{{ $reply->user?->avatar_url }}" name="{{ $reply->user?->name }}" size="xs" userId="{{ $reply->user?->id }}" showStatus="true" :isOnline="$reply->user?->is_online" />

                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="text-[10px] text-muted-foreground font-mono">#{{ $reply->id }}</span>
                                                    
                                                    @if($reply->user)
                                                        <x-user-status-sign :user="$reply->user" />
                                                        <a href="{{ route('admin.users.show', $reply->user->id) }}" wire:navigate class="font-medium text-xs hover:text-primary">{{ $reply->user->name }}</a>
                                                        @if($reply->user->has_active_premium)<x-lucide-crown class="w-3 h-3 text-yellow-500" />@endif
                                                    @else
                                                        <span class="font-medium text-xs text-muted-foreground">Удален</span>
                                                    @endif
                                                    <span class="text-[10px] text-muted-foreground">{{ $reply->created_at->diffForHumans() }}</span>
                                                    @php $replyBadge = $this->getStatusBadge($reply->status); @endphp
                                                    <x-ui.badge variant="{{ $replyBadge['variant'] }}" size="xs">{{ $replyBadge['label'] }}</x-ui.badge>
                                                    @if($replyRejectEnum)
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $replyRejectEnum->color() }}">{{ $replyRejectEnum->label() }}</span>
                                                    @endif
                                                </div>
                                                <p class="text-xs mt-0.5 text-foreground/80 break-words">{{ $reply->content }}</p>
                                            </div>

                                            <div class="flex gap-1 shrink-0">
                                                @if ($reply->status === 'pending')
                                                    <x-ui.button wire:click="approveComment({{ $reply->id }})" wire:target="approveComment({{ $reply->id }})" variant="ghost" size="icon-xs" title="Одобрить">
                                                        <x-lucide-check class="w-4 h-4 text-green-500" wire:loading.remove wire:target="approveComment({{ $reply->id }})" />
                                                        <x-lucide-loader-2 class="w-4 h-4 animate-spin hidden" wire:loading wire:target="approveComment({{ $reply->id }})" />
                                                    </x-ui.button>
                                                    
                                                    <x-ui.dropdown-menu>
                                                        <x-ui.dropdown-menu-trigger>
                                                            <x-ui.button variant="ghost" size="icon-xs" title="Отклонить"><x-lucide-x class="w-4 h-4 text-yellow-500" /></x-ui.button>
                                                        </x-ui.dropdown-menu-trigger>
                                                        <x-ui.dropdown-menu-content align="end">
                                                            <x-ui.dropdown-menu-label>Причина отклонения</x-ui.dropdown-menu-label>
                                                            <x-ui.dropdown-menu-separator></x-ui.dropdown-menu-separator>
                                                            @foreach (\App\Enums\CommentRejectReason::options() as $value => $label)
                                                                <x-ui.dropdown-menu-item wire:click="rejectComment({{ $reply->id }}, '{{ $value }}')">{{ $label }}</x-ui.dropdown-menu-item>
                                                            @endforeach
                                                        </x-ui.dropdown-menu-content>
                                                    </x-ui.dropdown-menu>

                                                    <x-ui.button wire:click="markSpam({{ $reply->id }})" wire:target="markSpam({{ $reply->id }})" variant="ghost" size="icon-xs" title="Спам">
                                                        <x-lucide-alert-circle class="w-4 h-4 text-red-500" wire:loading.remove wire:target="markSpam({{ $reply->id }})" />
                                                        <x-lucide-loader-2 class="w-4 h-4 animate-spin hidden" wire:loading wire:target="markSpam({{ $reply->id }})" />
                                                    </x-ui.button>
                                                @elseif($reply->status !== 'approved')
                                                    <x-ui.button wire:click="restoreComment({{ $reply->id }})" wire:target="restoreComment({{ $reply->id }})" variant="ghost" size="icon-xs" title="Восстановить">
                                                        <x-lucide-rotate-ccw class="w-4 h-4 text-blue-500" wire:loading.remove wire:target="restoreComment({{ $reply->id }})" />
                                                        <x-lucide-loader-2 class="w-4 h-4 animate-spin hidden" wire:loading wire:target="restoreComment({{ $reply->id }})" />
                                                    </x-ui.button>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $this->diaries->links('partials.pagination') }}
        </div>
    @endif
</div>