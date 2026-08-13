<?php

use App\Actions\Admin\ModerateCommentAction;
use App\Enums\CommentRejectReason;
use App\Models\Photo;
use App\Models\PhotoComment;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    public string $statusFilter = 'pending';
    
    #[Url(as: 'q', except: '')]
    public string $search = '';
    
    public int $perPage = 5;

    public function mount(): void
    {
        if (request()->has('q')) {
            $this->statusFilter = 'all';
            return;
        }

        $saved = session('moderate_photo_comments', []);
        if (isset($saved['statusFilter'])) $this->statusFilter = $saved['statusFilter'];
        if (isset($saved['search'])) $this->search = $saved['search'];
    }

    public function updatedSearch(): void
    {
        session(['moderate_photo_comments.search' => $this->search]);
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        session(['moderate_photo_comments.statusFilter' => $this->statusFilter]);
        $this->resetPage();
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        session(['moderate_photo_comments.statusFilter' => $status]);
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter']);
        $this->statusFilter = 'pending';
        session()->forget('moderate_photo_comments');
        $this->resetPage();
    }

    // ============================================
    // ДЕЙСТВИЯ (ДЕЛЕГИРУЕМ В ACTION)
    // ============================================

    public function approveComment(int $commentId, ModerateCommentAction $action): void
    {
        $comment = PhotoComment::with('parent')->find($commentId);
        if (!$comment) return;

        $success = $action->approve($comment, auth()->user());

        if (!$success) {
            $this->dispatch('show-toast', type: 'error', message: 'Нельзя одобрить ответ на неодобренный комментарий!');
            return;
        }

        $this->dispatch('show-toast', type: 'success', message: 'Комментарий одобрен');
    }

    public function rejectComment(int $commentId, string $reason, ModerateCommentAction $action): void
    {
        $comment = PhotoComment::find($commentId);
        if (!$comment) return;

        $action->reject($comment, auth()->user(), $reason);
        $this->dispatch('show-toast', type: 'info', message: 'Комментарий отклонен');
    }

    public function markSpam(int $commentId, ModerateCommentAction $action): void
    {
        $comment = PhotoComment::find($commentId);
        if (!$comment) return;

        $action->markSpam($comment, auth()->user());
        $this->dispatch('show-toast', type: 'error', message: 'Комментарий помечен как спам');
    }

    public function restoreComment(int $commentId, ModerateCommentAction $action): void
    {
        $comment = PhotoComment::find($commentId);
        if (!$comment) return;

        $action->restore($comment, auth()->user());
        $this->dispatch('show-toast', type: 'info', message: 'Комментарий возвращен на модерацию');
    }

    public function approveRemaining(int $photoId, ModerateCommentAction $action): void
    {
        $pendingComments = PhotoComment::where('photo_id', $photoId)
            ->where('status', 'pending')
            ->with('parent', 'user')
            ->get();

        if ($pendingComments->isEmpty()) {
            $this->dispatch('show-toast', type: 'info', message: 'Нет комментариев для одобрения');
            return;
        }

        $count = $action->bulkApprove($pendingComments, auth()->user());
        $this->dispatch('show-toast', type: 'success', message: "Одобрено {$count} комментариев");
    }

    public function rejectRemaining(int $photoId, ModerateCommentAction $action): void
    {
        $pendingComments = PhotoComment::where('photo_id', $photoId)
            ->where('status', 'pending')
            ->with('user')
            ->get();

        if ($pendingComments->isEmpty()) {
            $this->dispatch('show-toast', type: 'info', message: 'Нет комментариев для отклонения');
            return;
        }

        $count = $action->bulkReject($pendingComments, auth()->user(), 'mass_reject');
        $this->dispatch('show-toast', type: 'info', message: "Отклонено {$count} комментариев");
    }

    public function approveAllPending(ModerateCommentAction $action): void
    {
        $pendingComments = PhotoComment::where('status', 'pending')->with('parent', 'user')->get();

        if ($pendingComments->isEmpty()) {
            $this->dispatch('show-toast', type: 'info', message: 'Нет комментариев для одобрения');
            return;
        }

        $count = $action->bulkApprove($pendingComments, auth()->user());
        $this->dispatch('show-toast', type: 'success', message: "Одобрено {$count} комментариев");
    }

    public function rejectAllPending(ModerateCommentAction $action): void
    {
        $pendingComments = PhotoComment::where('status', 'pending')->with('user')->get();

        if ($pendingComments->isEmpty()) {
            $this->dispatch('show-toast', type: 'info', message: 'Нет комментариев для отклонения');
            return;
        }

        $count = $action->bulkReject($pendingComments, auth()->user(), 'mass_reject');
        $this->dispatch('show-toast', type: 'info', message: "Отклонено {$count} комментариев");
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

    // ============================================
    // ВЫВОД ДАННЫХ (ОПТИМИЗИРОВАННЫЕ ЗАПРОСЫ)
    // ============================================

    private function applyCommentFilters($query): void
    {
        $query->whereNull('parent_id'); 
        
        if ($this->statusFilter !== 'all') {
            $query->where(function ($sub) {
                $sub->where('status', $this->statusFilter)
                    ->orWhereHas('replies', fn($r) => $r->where('status', $this->statusFilter));
            });
        }

        if (!empty($this->search)) {
            $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
            $search = '%' . $this->search . '%';
            $query->where(function ($sub) use ($search, $operator) {
                $sub->where('content', $operator, $search)
                    ->orWhereRaw("CAST(id AS TEXT) {$operator} ?", [$search])
                    ->orWhereHas('user', fn($user) => $user->where('name', $operator, $search))
                    ->orWhereHas('replies', function ($r) use ($search, $operator) {
                        $r->where('content', $operator, $search)
                          ->orWhereRaw("CAST(id AS TEXT) {$operator} ?", [$search])
                          ->orWhereHas('user', fn($user) => $user->where('name', $operator, $search));
                    });
            });
        }
    }

    #[Computed]
    public function photos()
    {
        // ФИКС: withTrashed() для юзеров, чтобы видеть имена и аватарки удаленных авторов
        $userAvatarQuery = fn($q) => $q->withTrashed()->select('id', 'name', 'status', 'is_premium', 'premium_expires_at', 'is_verified', 'last_seen')
            ->with(['photos' => fn($sq) => $sq->select('id', 'user_id', 'is_primary', 'status', 'path_thumb', 'path_medium', 'path_large', 'path_original')->orderByDesc('is_primary')->limit(1)]);

        // ФИКС: Photo::withTrashed(), чтобы выводить комменты под удаленными фото
        $query = Photo::withTrashed()->whereHas('comments', fn($q) => $this->applyCommentFilters($q))
        ->with([
            'album:id,name',
            'user' => fn($q) => $q->withTrashed()->select('id', 'name', 'status', 'is_premium', 'premium_expires_at', 'is_verified', 'last_seen'), 
            'comments' => function ($q) use ($userAvatarQuery) {
                $this->applyCommentFilters($q);
                $q->with([
                    'user' => $userAvatarQuery,
                    'replies' => function ($q) use ($userAvatarQuery) {
                        $q->with(['parent:id,status', 'user' => $userAvatarQuery])->latest();
                    },
                ])->latest();
            },
        ]);

        return $query->latest()->paginate($this->perPage);
    }

    #[Computed]
    public function counts()
    {
        // ФИКС: Считаем только те комменты, у которых есть фото (даже если оно в корзине)
        // И исключаем "сирот" (ответы на удаленные комменты), чтобы счетчик совпадал со списком
        $stats = PhotoComment::whereHas('photo', fn($q) => $q->withTrashed())
            ->where(function ($q) {
                $q->whereNull('parent_id')
                  ->orWhereHas('parent'); 
            })
            ->selectRaw("
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
            @php
                // Защита от зацикливания кнопки "Назад"
                $previousUrl = url()->previous();
                $backUrl = ($previousUrl && $previousUrl !== url()->current()) 
                    ? $previousUrl 
                    : route('admin.dashboard'); // Фоллбэк на главную админки
            @endphp

            <a href="{{ $backUrl }}" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-semibold flex items-center gap-2">
                    <x-lucide-message-circle class="w-6 h-6" />
                    Модерация комментариев
                    @if ($this->counts['pending'] > 0)
                        <x-ui.badge variant="destructive" size="sm">{{ $this->counts['pending'] }} новых</x-ui.badge>
                    @endif
                </h1>
                <p class="text-sm text-muted-foreground">Всего комментариев: {{ $this->counts['total'] }}</p>
            </div>
         </div>

        @if ($this->counts['pending'] > 0)
            <div class="flex items-center gap-2">
                <x-ui.alert-dialog wire:key="approve-all-dialog">
                    <x-ui.alert-dialog-trigger>
                        <x-ui.button wire:loading.attr="disabled" wire:target="approveAllPending" variant="success" size="sm" class="gap-2">
                            <span wire:loading.remove wire:target="approveAllPending"><x-lucide-check class="w-4 h-4" /></span>
                            <span wire:loading wire:target="approveAllPending"><x-lucide-loader-2 class="w-4 h-4 animate-spin" /></span>
                            Одобрить все
                        </x-ui.button>
                    </x-ui.alert-dialog-trigger>
                    <x-ui.alert-dialog-content>
                        <x-ui.alert-dialog-header>
                            <x-ui.alert-dialog-title>Одобрить все комментарии</x-ui.alert-dialog-title>
                            <x-ui.alert-dialog-description>Вы уверены? Будут одобрены все {{ $this->counts['pending'] }} комментариев.</x-ui.alert-dialog-description>
                        </x-ui.alert-dialog-header>
                        <x-ui.alert-dialog-footer>
                            <x-ui.alert-dialog-cancel>Отмена</x-ui.alert-dialog-cancel>
                            <x-ui.alert-dialog-action wire:click="approveAllPending">Одобрить все</x-ui.alert-dialog-action>
                        </x-ui.alert-dialog-footer>
                    </x-ui.alert-dialog-content>
                </x-ui.alert-dialog>

                <x-ui.alert-dialog wire:key="reject-all-dialog">
                    <x-ui.alert-dialog-trigger>
                        <x-ui.button wire:loading.attr="disabled" wire:target="rejectAllPending" variant="destructive" size="sm" class="gap-2">
                            <span wire:loading.remove wire:target="rejectAllPending"><x-lucide-x class="w-4 h-4" /></span>
                            <span wire:loading wire:target="rejectAllPending"><x-lucide-loader-2 class="w-4 h-4 animate-spin" /></span>
                            Отклонить все
                        </x-ui.button>
                    </x-ui.alert-dialog-trigger>
                    <x-ui.alert-dialog-content>
                        <x-ui.alert-dialog-header>
                            <x-ui.alert-dialog-title>Отклонить все комментарии</x-ui.alert-dialog-title>
                            <x-ui.alert-dialog-description>
                                Вы уверены? Будут отклонены все {{ $this->counts['pending'] }} комментариев.<br>
                                <strong class="text-destructive">Это действие нельзя отменить.</strong>
                            </x-ui.alert-dialog-description>
                        </x-ui.alert-dialog-header>
                        <x-ui.alert-dialog-footer>
                            <x-ui.alert-dialog-cancel>Отмена</x-ui.alert-dialog-cancel>
                            <x-ui.alert-dialog-action wire:click="rejectAllPending">Отклонить все</x-ui.alert-dialog-action>
                        </x-ui.alert-dialog-footer>
                    </x-ui.alert-dialog-content>
                </x-ui.alert-dialog>
            </div>
        @endif
    </div>

    <!-- Фильтры -->
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex flex-wrap gap-1.5">
            <x-ui.button wire:click="setStatusFilter('pending')" variant="{{ $statusFilter === 'pending' ? 'default' : 'secondary' }}" size="sm">Ожидают <x-ui.badge size="xs" variant="warning">{{ $this->counts['pending'] }}</x-ui.badge></x-ui.button>
            <x-ui.button wire:click="setStatusFilter('all')" variant="{{ $statusFilter === 'all' ? 'default' : 'secondary' }}" size="sm">Все <x-ui.badge size="xs">{{ $this->counts['total'] }}</x-ui.badge></x-ui.button>
            <x-ui.button wire:click="setStatusFilter('approved')" variant="{{ $statusFilter === 'approved' ? 'default' : 'secondary' }}" size="sm">Одобрены <x-ui.badge size="xs" variant="success">{{ $this->counts['approved'] }}</x-ui.badge></x-ui.button>
            <x-ui.button wire:click="setStatusFilter('rejected')" variant="{{ $statusFilter === 'rejected' ? 'default' : 'secondary' }}" size="sm">Отклонены <x-ui.badge size="xs" variant="destructive">{{ $this->counts['rejected'] }}</x-ui.badge></x-ui.button>
            <x-ui.button wire:click="setStatusFilter('spam')" variant="{{ $statusFilter === 'spam' ? 'default' : 'secondary' }}" size="sm">Спам <x-ui.badge size="xs" variant="destructive">{{ $this->counts['spam'] }}</x-ui.badge></x-ui.button>
        </div>
        <div class="flex items-center gap-2 ml-auto">
            <div class="relative w-64">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск по тексту или автору..." class="pl-9 pr-8" />
                <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                @if (!empty($search))
                    <button wire:click="$set('search', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"><x-lucide-x class="w-4 h-4" /></button>
                @endif
            </div>
        </div>
    </div>

    @if ($this->photos->isEmpty())
        <div class="bg-card border border-border rounded-lg p-16 text-center">
            <div class="w-16 h-16 mx-auto rounded-full bg-muted flex items-center justify-center mb-4">
                <x-lucide-check-circle class="w-8 h-8 text-muted-foreground" />
            </div>
            <h3 class="text-lg font-medium">Нет комментариев</h3>
            <p class="text-muted-foreground mt-1">
                @if (!empty($search)) По запросу "{{ $search }}" ничего не найдено @else Все комментарии проверены. Отличная работа! @endif
            </p>
            @if (!empty($search) || $statusFilter !== 'pending')
                <x-ui.button wire:click="resetFilters" variant="outline" class="mt-4">Сбросить фильтры</x-ui.button>
            @endif
        </div>
    @else
        <div class="space-y-6">
            @foreach ($this->photos as $photo)
                @php
                      $imgSrc = $photo->thumb_url ?: asset('images/no-image-placeholder.png');
                      $fullSrc = $photo->original_url ?: $imgSrc; // Для лайтбокса берем оригинал
                    $pendingCount = $photo->comments->where('status', 'pending')->count() + $photo->comments->flatMap->replies->where('status', 'pending')->count();
                @endphp

                <div class="bg-card border border-border rounded-lg overflow-hidden" wire:key="photo-{{ $photo->id }}">
                    <div class="p-4 border-b border-border flex items-center justify-between bg-muted/20">
                        <p class="font-semibold text-foreground flex items-center gap-2 flex-wrap">
                            Фото #{{ $photo->id }}
                            
                            <!-- Унифицированный блок: Автор фото -->
                            <span class="text-xs text-muted-foreground font-normal flex items-center gap-1">
                                от
                                @if($photo->user)
                                    <x-user-status-sign :user="$photo->user" />
                                    <a href="{{ route('admin.users.show', $photo->user->id) }}" wire:navigate class="hover:text-primary">{{ $photo->user->name }}</a>
                                    @if($photo->user->has_active_premium)<x-lucide-crown class="w-3 h-3 text-yellow-500" />@endif                                    
                                @else 
                                    <span class="text-muted-foreground">Удален</span>
                                @endif
                            </span>

                            @if ($photo->album)
                                <span class="text-xs text-muted-foreground font-normal">в альбоме «{{ $photo->album->name }}»</span>
                            @endif
                        </p>

                        @if ($pendingCount > 0 && $statusFilter === 'pending')
                            <div class="flex gap-2">
                                <x-ui.button wire:click="approveRemaining({{ $photo->id }})" variant="success" size="sm">Одобрить все ({{ $pendingCount }})</x-ui.button>
                                <x-ui.button wire:click="rejectRemaining({{ $photo->id }})" variant="destructive" size="sm">Отклонить все</x-ui.button>
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-col md:flex-row">
                        <div class="md:w-64 lg:w-80 shrink-0 border-r border-border bg-muted/10 p-4 flex items-center justify-center">
                           <div class="relative aspect-square bg-muted group overflow-hidden rounded-lg">
                                <a href="{{ $fullSrc }}" data-fancybox="gallery-comments" data-caption="Фото #{{ $photo->id }}" class="block w-full max-w-[200px] aspect-square bg-muted rounded-lg overflow-hidden cursor-pointer hover:opacity-90 transition-opacity">
                                    <img src="{{ $imgSrc }}" alt="Photo" class="w-full h-full object-cover">
                                </a>
                                
                                <div class="absolute top-2 left-2 z-10 flex flex-col gap-1">
                                    @if ($photo->is_primary) <x-ui.badge size="xs">Аватар</x-ui.badge> @endif
                                    @if ($photo->is_intimate) <x-ui.badge variant="destructive" size="xs">18+</x-ui.badge> @endif
                                </div>

                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center pointer-events-none">
                                    <x-lucide-maximize-2 class="w-8 h-8 text-white drop-shadow-lg" />
                                </div>
                            </div>
                        </div>

                        <div class="flex-1 p-4 space-y-3 max-h-[500px] overflow-y-auto">
                            @foreach ($photo->comments as $comment)
                                @php 
                                    $commentDimmed = $this->statusFilter !== 'all' && $comment->status !== $this->statusFilter; 
                                    $rejectEnum = $comment->reject_reason ? \App\Enums\CommentRejectReason::tryFrom($comment->reject_reason) : null;      
                                @endphp

                                <div class="flex items-start gap-3 p-3 {{ $comment->status === 'pending' ? 'bg-yellow-500/5 border border-yellow-500/20' : 'bg-muted/10 border border-border' }} rounded-lg {{ $commentDimmed ? 'opacity-50' : '' }}" wire:key="comment-{{ $comment->id }}-{{ $comment->status }}">
                                    <x-avatar src="{{ $comment->user?->avatar_url }}" name="{{ $comment->user?->name ?? 'Удален' }}" size="sm" userId="{{ $comment->user?->id }}" showStatus="true" :isOnline="$comment->user?->is_online"/>

                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <!-- Унифицированный блок: Автор комментария -->
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
                                        <p class="text-sm mt-0.5">{{ $comment->content }}</p>
                                    </div>

                                    <div class="flex gap-1 shrink-0">
                                        @if ($comment->status === 'pending')
                                            <x-ui.button wire:click="approveComment({{ $comment->id }})" variant="ghost" size="icon-xs" title="Одобрить"><x-lucide-check class="w-4 h-4 text-green-500" /></x-ui.button>
                                            
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

                                            <x-ui.button wire:click="markSpam({{ $comment->id }})" variant="ghost" size="icon-xs" title="Пометить спамом"><x-lucide-alert-circle class="w-4 h-4 text-red-500" /></x-ui.button>
                                        @elseif($comment->status === 'approved')
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
                                            <x-ui.button wire:click="markSpam({{ $comment->id }})" variant="ghost" size="icon-xs" title="Пометить спамом"><x-lucide-alert-circle class="w-4 h-4 text-red-500" /></x-ui.button>
                                        @else
                                            <x-ui.button wire:click="restoreComment({{ $comment->id }})" variant="ghost" size="icon-xs" title="Восстановить"><x-lucide-rotate-ccw class="w-4 h-4 text-blue-500" /></x-ui.button>
                                        @endif
                                    </div>
                                </div>

                                @if ($comment->replies->count() > 0)
                                    <div class="pl-12 border-l-2 border-border space-y-2 -mt-2">
                                        @foreach ($comment->replies as $reply)
                                            @php 
                                            $replyDimmed = $this->statusFilter !== 'all' && $reply->status !== $this->statusFilter;
                                            $replyRejectEnum = $reply->reject_reason ? \App\Enums\CommentRejectReason::tryFrom($reply->reject_reason) : null;
                                            @endphp
                                            <div class="flex items-start gap-2 p-2 {{ $reply->status === 'pending' ? 'bg-yellow-500/5 border border-yellow-500/20' : 'bg-muted/5 border border-border' }} rounded-lg {{ $replyDimmed ? 'opacity-50' : '' }}" wire:key="reply-{{ $reply->id }}-{{ $reply->status }}">
                                                <x-avatar src="{{ $reply->user?->avatar_url }}" name="{{ $reply->user?->name ?? 'Удален' }}" size="xs" userId="{{ $reply->user?->id }}" showStatus="true" :isOnline="$reply->user?->is_online" />

                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center gap-2 flex-wrap">
                                                        <!-- Унифицированный блок: Автор ответа -->
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
                                                    <p class="text-xs mt-0.5">{{ $reply->content }}</p>
                                                </div>

                                                <div class="flex gap-1 shrink-0">
                                                    @if ($reply->status === 'pending')
                                                        <x-ui.button wire:click="approveComment({{ $reply->id }})" variant="ghost" size="icon-xs" title="Одобрить"><x-lucide-check class="w-4 h-4 text-green-500" /></x-ui.button>
                                                        
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

                                                        <x-ui.button wire:click="markSpam({{ $reply->id }})" variant="ghost" size="icon-xs" title="Пометить спамом"><x-lucide-alert-circle class="w-4 h-4 text-red-500" /></x-ui.button>
                                                    @elseif($reply->status === 'approved')
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
                                                        <x-ui.button wire:click="markSpam({{ $reply->id }})" variant="ghost" size="icon-xs" title="Пометить спамом"><x-lucide-alert-circle class="w-4 h-4 text-red-500" /></x-ui.button>
                                                    @else
                                                        <x-ui.button wire:click="restoreComment({{ $reply->id }})" variant="ghost" size="icon-xs" title="Восстановить"><x-lucide-rotate-ccw class="w-4 h-4 text-blue-500" /></x-ui.button>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">{{ $this->photos->links('partials.pagination') }}</div>
    @endif

    <script>
    // Безопасная инициализация Fancybox
    document.addEventListener('livewire:navigated', () => {
        if (typeof Fancybox !== 'undefined') {
            Fancybox.defaults.Hash = false; 
            Fancybox.bind('[data-fancybox]'); 
        }
    });
    </script>
</div>