<?php

use App\Models\Photo;
use App\Models\PhotoComment;
use App\Notifications\CommentModerated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    public string $statusFilter = 'pending';
    public string $search = '';
    public int $perPage = 5;

    public bool $showPreviewModal = false;
    public ?Photo $previewPhoto = null;

    public function mount(): void
    {
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

    public function previewPhoto(int $photoId): void
    {
        // Подгружаем юзера с 1 аватаркой для модалки
        $this->previewPhoto = Photo::with(['user' => fn($q) => $q->select('id', 'name')->with(['photos' => fn($sq) => $sq->select('id', 'user_id', 'path_thumb', 'path_medium')->take(1)])])->find($photoId);
        $this->showPreviewModal = true;
    }

    // === ДЕЙСТВИЯ ===

    public function approveComment(int $commentId): void
    {
        // Подгружаем parent чтобы проверить бизнес-логику в actionApprove
        $comment = PhotoComment::with('parent')->find($commentId);
        if (!$comment) return;

        $success = $this->actionApprove($comment);

        if (!$success) {
            $this->dispatch('show-toast', type: 'error', message: 'Нельзя одобрить ответ на неодобренный комментарий!');
            return;
        }

        $this->dispatch('show-toast', type: 'success', message: 'Комментарий одобрен');
        $this->dispatch('$refresh');
    }

    public function rejectComment(int $commentId): void
    {
        $comment = PhotoComment::find($commentId);
        if (!$comment) return;

        $this->actionReject($comment);

        $this->dispatch('show-toast', type: 'info', message: 'Комментарий отклонен');
        $this->dispatch('$refresh');
    }

    public function markSpam(int $commentId): void
    {
        $comment = PhotoComment::find($commentId);
        if (!$comment) return;

        $this->actionMarkSpam($comment);

        $this->dispatch('show-toast', type: 'error', message: 'Комментарий помечен как спам');
        $this->dispatch('$refresh');
    }

    public function deleteComment(int $commentId): void
    {
        $comment = PhotoComment::find($commentId);
        if (!$comment) return;

        $this->actionDelete($comment);

        $this->dispatch('show-toast', type: 'success', message: 'Комментарий удален');
        $this->dispatch('$refresh');
    }

    public function restoreComment(int $commentId): void
    {
        $comment = PhotoComment::find($commentId);
        if (!$comment) return;

        $this->actionRestore($comment);

        $this->dispatch('show-toast', type: 'info', message: 'Комментарий возвращен на модерацию');
        $this->dispatch('$refresh');
    }

    public function approveRemaining(int $photoId): void
    {
        // Готовая коллекция с dependency injection для экшена
        $pendingComments = PhotoComment::where('photo_id', $photoId)
            ->where('status', 'pending')
            ->whereHas('user', fn($q) => $q->where('is_admin', false))
            ->with('parent', 'user')
            ->get();

        if ($pendingComments->isEmpty()) {
            $this->dispatch('show-toast', type: 'info', message: 'Нет комментариев для одобрения');
            return;
        }

        $count = $this->actionBulkApprove($pendingComments);

        $this->dispatch('show-toast', type: 'success', message: "Одобрено {$count} комментариев");
        $this->dispatch('$refresh');
    }

    public function rejectRemaining(int $photoId): void
    {
        $pendingComments = PhotoComment::where('photo_id', $photoId)
            ->where('status', 'pending')
            ->whereHas('user', fn($q) => $q->where('is_admin', false))
            ->with('user')
            ->get();

        if ($pendingComments->isEmpty()) {
            $this->dispatch('show-toast', type: 'info', message: 'Нет комментариев для отклонения');
            return;
        }

        $count = $this->actionBulkReject($pendingComments);

        $this->dispatch('show-toast', type: 'info', message: "Отклонено {$count} комментариев");
        $this->dispatch('$refresh');
    }

    public function approveAllPending(): void
    {
        $pendingComments = PhotoComment::where('status', 'pending')
            ->whereHas('user', fn($q) => $q->where('is_admin', false))
            ->with('parent', 'user')
            ->get();

        if ($pendingComments->isEmpty()) {
            $this->dispatch('show-toast', type: 'info', message: 'Нет комментариев для одобрения');
            return;
        }

        $count = $this->actionBulkApprove($pendingComments);

        $this->dispatch('show-toast', type: 'success', message: "Одобрено {$count} комментариев");
        $this->dispatch('$refresh');
    }

    public function rejectAllPending(): void
    {
        $pendingComments = PhotoComment::where('status', 'pending')
            ->whereHas('user', fn($q) => $q->where('is_admin', false))
            ->with('user')
            ->get();

        if ($pendingComments->isEmpty()) {
            $this->dispatch('show-toast', type: 'info', message: 'Нет комментариев для отклонения');
            return;
        }

        $count = $this->actionBulkReject($pendingComments);

        $this->dispatch('show-toast', type: 'info', message: "Отклонено {$count} комментариев");
        $this->dispatch('$refresh');
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

    // === ВЫВОД ДАННЫХ (Оптимизированные запросы) ===

    private function applyCommentFilters($query): void
    {
        $query->whereHas('user', fn($q) => $q->where('is_admin', false));

        if ($this->statusFilter !== 'all') {
            $query->where(function ($sub) {
                $sub->where('status', $this->statusFilter)->orWhereHas('replies', function ($r) {
                    $r->where('status', $this->statusFilter);
                });
            });
        }

        if (!empty($this->search)) {
            $search = '%' . $this->search . '%';
            $query->where(function ($sub) use ($search) {
                $sub->where('content', 'like', $search)
                    ->orWhereHas('user', function ($user) use ($search) {
                        $user->where('name', 'like', $search);
                    })
                    ->orWhereHas('replies', function ($r) use ($search) {
                        $r->where(function ($rr) use ($search) {
                            $rr->where('content', 'like', $search)->orWhereHas('user', function ($user) use ($search) {
                                $user->where('name', 'like', $search);
                            });
                        });
                    });
            });
        }
    }

    private function applyReplyFilters($query): void
    {
        $query->whereHas('user', fn($q) => $q->where('is_admin', false));

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if (!empty($this->search)) {
            $search = '%' . $this->search . '%';
            $query->where(function ($sub) use ($search) {
                $sub->where('content', 'like', $search)->orWhereHas('user', function ($user) use ($search) {
                    $user->where('name', 'like', $search);
                });
            });
        }
    }

    #[Computed]
    public function photos()
    {
        // Универсальный подзапрос для подгрузки аватарок авторов (исключает N+1)
        $userAvatarQuery = fn($q) => $q->select('id', 'name', 'is_banned', 'is_verified', 'is_premium', 'premium_expires_at')
            ->with(['photos' => fn($sq) => $sq->select('id', 'user_id', 'path_thumb', 'path_medium')->where('is_primary', true)->orWhere('status', 'approved')->take(1)]);

        $query = Photo::whereHas('comments', function ($q) {
            $this->applyCommentFilters($q);
        })
        ->with([
            'album:id,name',
            'user:id,name', // Автор фотки (в шапке карточки)
            'comments' => function ($q) use ($userAvatarQuery) {
                $q->whereNull('parent_id');
                $this->applyCommentFilters($q);

                $q->with([
                    'user' => $userAvatarQuery,
                    'replies' => function ($q) use ($userAvatarQuery) {
                        $this->applyReplyFilters($q);
                        $q->with(['parent:id,status', 'user' => $userAvatarQuery])->latest();
                    },
                ])->latest();
            },
        ])
        ->withCount([
            'comments' => function ($q) {
                $this->applyCommentFilters($q);
            },
        ]);

        return $query->latest()->paginate($this->perPage);
    }

    #[Computed]
    public function counts()
    {
        $stats = PhotoComment::whereHas('user', fn($q) => $q->where('is_admin', false))->selectRaw(
            "
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'spam' THEN 1 ELSE 0 END) as spam,
            COUNT(*) as total
        "
        )->first();

        return [
            'pending' => (int) ($stats->pending ?? 0),
            'approved' => (int) ($stats->approved ?? 0),
            'rejected' => (int) ($stats->rejected ?? 0),
            'spam' => (int) ($stats->spam ?? 0),
            'total' => (int) ($stats->total ?? 0),
        ];
    }

    // === БИЗНЕС-ЛОГИКА (Интегрированный Action) ===

    protected function actionApprove(PhotoComment $comment): bool
    {
        if ($comment->parent_id && $comment->parent && $comment->parent->status !== 'approved') {
            return false;
        }

        $comment->update([
            'status' => 'approved',
            'approved_at' => now()
        ]);

        $this->notifyAuthor($comment, 'approved');
        return true;
    }

    protected function actionReject(PhotoComment $comment): void
    {
        $comment->update([
            'status' => 'rejected',
            'rejected_at' => now()
        ]);

        $this->notifyAuthor($comment, 'rejected');
    }

    protected function actionMarkSpam(PhotoComment $comment): void
    {
        $comment->update(['status' => 'spam']);
        $this->notifyAuthor($comment, 'spam');
    }

    protected function actionDelete(PhotoComment $comment): void
    {
        $this->notifyAuthor($comment, 'deleted');
        $comment->delete();
    }

    protected function actionRestore(PhotoComment $comment): void
    {
        $comment->update(['status' => 'pending']);
        $this->notifyAuthor($comment, 'restored');
    }

    protected function actionBulkApprove($comments): int
    {
        $approvedCount = 0;
        foreach ($comments as $comment) {
            if ($this->actionApprove($comment)) {
                $approvedCount++;
            }
        }
        return $approvedCount;
    }

    protected function actionBulkReject($comments): int
    {
        DB::transaction(function () use ($comments) {
            foreach ($comments as $comment) {
                $comment->update([
                    'status' => 'rejected',
                    'rejected_at' => now()
                ]);
                $this->notifyAuthor($comment, 'rejected');
            }
        });
        return $comments->count();
    }

    private function notifyAuthor(PhotoComment $comment, string $status): void
    {
        try {
            if ($comment->user) {
                $comment->user->notify(new CommentModerated($comment, $status));
            }
        } catch (\Exception $e) {
            Log::error('Ошибка уведомления о модерации комментария: ' . $e->getMessage());
        }
    }
};?>

<!-- ========================================== -->
<!-- ШАБЛОН                                     -->
<!-- ========================================== -->
<div class="space-y-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h1 class="text-2xl font-semibold flex items-center gap-2">
                <x-lucide-message-circle class="w-6 h-6" />
                Модерация комментариев
                @if ($this->counts['pending'] > 0)
                    <x-ui.badge variant="destructive" size="sm">
                        {{ $this->counts['pending'] }} новых
                    </x-ui.badge>
                @endif
            </h1>
            <p class="text-sm text-muted-foreground">
                Всего комментариев: {{ $this->counts['total'] }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            @if ($this->counts['pending'] > 0)
                <x-ui.alert-dialog wire:key="approve-all-dialog">
                    <x-ui.alert-dialog-trigger>
                        <x-ui.button wire:loading.attr="disabled" variant="success" size="sm" class="gap-2">
                            <span wire:loading.remove wire:target="approveAllPending"><x-lucide-check
                                    class="w-4 h-4" /></span>
                            <span wire:loading wire:target="approveAllPending"><x-ui.spinner class="w-4 h-4" /></span>
                            <span wire:loading.remove wire:target="approveAllPending">Одобрить все</span>
                            <span wire:loading wire:target="approveAllPending">Одобрение...</span>
                        </x-ui.button>
                    </x-ui.alert-dialog-trigger>
                    <x-ui.alert-dialog-content>
                        <x-ui.alert-dialog-header>
                            <x-ui.alert-dialog-title>Одобрить все комментарии</x-ui.alert-dialog-title>
                            <x-ui.alert-dialog-description>
                                Вы уверены? Будут одобрены все {{ $this->counts['pending'] }} комментариев.
                            </x-ui.alert-dialog-description>
                        </x-ui.alert-dialog-header>
                        <x-ui.alert-dialog-footer>
                            <x-ui.alert-dialog-cancel>Отмена</x-ui.alert-dialog-cancel>
                            <x-ui.alert-dialog-action wire:click="approveAllPending">
                                <x-lucide-check class="w-4 h-4" /> Одобрить все
                            </x-ui.alert-dialog-action>
                        </x-ui.alert-dialog-footer>
                    </x-ui.alert-dialog-content>
                </x-ui.alert-dialog>

                <x-ui.alert-dialog wire:key="reject-all-dialog">
                    <x-ui.alert-dialog-trigger>
                        <x-ui.button wire:loading.attr="disabled" variant="destructive" size="sm" class="gap-2">
                            <span wire:loading.remove wire:target="rejectAllPending"><x-lucide-x
                                    class="w-4 h-4" /></span>
                            <span wire:loading wire:target="rejectAllPending"><x-ui.spinner class="w-4 h-4" /></span>
                            <span wire:loading.remove wire:target="rejectAllPending">Отклонить все</span>
                            <span wire:loading wire:target="rejectAllPending">Отклонение...</span>
                        </x-ui.button>
                    </x-ui.alert-dialog-trigger>
                    <x-ui.alert-dialog-content>
                        <x-ui.alert-dialog-header>
                            <x-ui.alert-dialog-title>Отклонить все комментарии</x-ui.alert-dialog-title>
                            <x-ui.alert-dialog-description>
                                Вы уверены? Будут отклонены все {{ $this->counts['pending'] }} комментариев.
                                <br><strong class="text-destructive">Это действие нельзя отменить.</strong>
                            </x-ui.alert-dialog-description>
                        </x-ui.alert-dialog-header>
                        <x-ui.alert-dialog-footer>
                            <x-ui.alert-dialog-cancel>Отмена</x-ui.alert-dialog-cancel>
                            <x-ui.alert-dialog-action wire:click="rejectAllPending">
                                <x-lucide-x class="w-4 h-4" /> Отклонить все
                            </x-ui.alert-dialog-action>
                        </x-ui.alert-dialog-footer>
                    </x-ui.alert-dialog-content>
                </x-ui.alert-dialog>
            @endif
        </div>
    </div>

    <!-- Фильтры -->
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex flex-wrap gap-1.5">
            <x-ui.button wire:click="setStatusFilter('pending')"
                variant="{{ $statusFilter === 'pending' ? 'default' : 'secondary' }}" size="sm"
                wire:key="filter-pending">
                Ожидают <x-ui.badge size="xs" variant="warning">{{ $this->counts['pending'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="setStatusFilter('all')"
                variant="{{ $statusFilter === 'all' ? 'default' : 'secondary' }}" size="sm" wire:key="filter-all">
                Все <x-ui.badge size="xs">{{ $this->counts['total'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="setStatusFilter('approved')"
                variant="{{ $statusFilter === 'approved' ? 'default' : 'secondary' }}" size="sm"
                wire:key="filter-approved">
                Одобрены <x-ui.badge size="xs" variant="success">{{ $this->counts['approved'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="setStatusFilter('rejected')"
                variant="{{ $statusFilter === 'rejected' ? 'default' : 'secondary' }}" size="sm"
                wire:key="filter-rejected">
                Отклонены <x-ui.badge size="xs" variant="destructive">{{ $this->counts['rejected'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="setStatusFilter('spam')"
                variant="{{ $statusFilter === 'spam' ? 'default' : 'secondary' }}" size="sm"
                wire:key="filter-spam">
                Спам <x-ui.badge size="xs" variant="destructive">{{ $this->counts['spam'] }}</x-ui.badge>
            </x-ui.button>
        </div>

        <div class="flex items-center gap-2 ml-auto">
            <div class="relative w-64" wire:key="search-wrapper">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search"
                    placeholder="Поиск по тексту или автору..." class="pl-9 pr-8" />
                <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                @if (!empty($search))
                    <button wire:click="$set('search', '')"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Список фото -->
    @if ($this->photos->isEmpty())
        <div class="bg-card border border-border rounded-lg p-16 text-center" wire:key="empty-state">
            <div class="w-16 h-16 mx-auto rounded-full bg-muted flex items-center justify-center mb-4">
                <x-lucide-check-circle class="w-8 h-8 text-muted-foreground" />
            </div>
            <h3 class="text-lg font-medium text-foreground">Нет комментариев</h3>
            <p class="text-muted-foreground mt-1">
                @if (!empty($search))
                    По запросу "{{ $search }}" ничего не найдено
                @else
                    Все комментарии проверены. Отличная работа!
                @endif
            </p>
            @if (!empty($search) || $statusFilter !== 'pending')
                <x-ui.button wire:click="resetFilters" variant="outline" class="mt-4">
                    Сбросить фильтры
                </x-ui.button>
            @endif
        </div>
    @else
        <div class="space-y-6">
            @foreach ($this->photos as $photo)
                @php
                    $allComments = $photo->comments->concat($photo->comments->flatMap(fn($c) => $c->replies));
                    $totalComments = $allComments->count();
                    $pendingCount = $allComments->where('status', 'pending')->count();
                    $hasPending = $pendingCount > 0;
                @endphp

                <div class="bg-card border border-border rounded-lg overflow-hidden"
                    wire:key="photo-{{ $photo->id }}">
                    <!-- Заголовок -->
                    <div class="p-4 border-b border-border flex items-center justify-between bg-muted/20">
                        <div>
                            <p class="font-semibold text-foreground">
                                Фото #{{ $photo->id }}
                                <span class="text-xs text-muted-foreground font-normal">от
                                    @if($photo->user)
                                        <a href="{{ route('admin.users.show', $photo->user->id) }}" wire:navigate class="hover:text-primary">{{ $photo->user->name }}</a>
                                    @else
                                        Удален
                                    @endif
                                </span>
                                @if ($photo->album)
                                    <span class="text-xs text-muted-foreground font-normal">в альбоме
                                        «{{ $photo->album->name }}»</span>
                                @endif
                            </p>
                            <div class="flex items-center gap-3 text-xs text-muted-foreground">
                                @if ($hasPending)
                                    <span class="text-yellow-500">Ожидают: {{ $pendingCount }}</span>
                                    <span>•</span>
                                @endif
                                <span>Всего: {{ $totalComments }} </span>
                            </div>
                        </div>

                        @if ($hasPending)
                            <div class="flex gap-2">
                                <x-ui.button wire:click="approveRemaining({{ $photo->id }})"
                                    wire:loading.attr="disabled" variant="success" size="sm" class="gap-2"
                                    wire:key="approve-remaining-{{ $photo->id }}">
                                    <span wire:loading.remove
                                        wire:target="approveRemaining({{ $photo->id }})"><x-lucide-check
                                            class="w-4 h-4" /></span>
                                    <span wire:loading
                                        wire:target="approveRemaining({{ $photo->id }})"><x-ui.spinner
                                            class="w-4 h-4" /></span>
                                    <span wire:loading.remove
                                        wire:target="approveRemaining({{ $photo->id }})">Одобрить все
                                        ({{ $pendingCount }})</span>
                                    <span wire:loading
                                        wire:target="approveRemaining({{ $photo->id }})">Одобрение...</span>
                                </x-ui.button>

                                <x-ui.alert-dialog wire:key="reject-remaining-dialog-{{ $photo->id }}">
                                    <x-ui.alert-dialog-trigger>
                                        <x-ui.button wire:loading.attr="disabled" variant="destructive"
                                            size="sm" class="gap-2">
                                            <span wire:loading.remove
                                                wire:target="rejectRemaining({{ $photo->id }})"><x-lucide-x
                                                    class="w-4 h-4" /></span>
                                            <span wire:loading
                                                wire:target="rejectRemaining({{ $photo->id }})"><x-ui.spinner
                                                    class="w-4 h-4" /></span>
                                            <span wire:loading.remove
                                                wire:target="rejectRemaining({{ $photo->id }})">Отклонить все
                                                ({{ $pendingCount }})</span>
                                            <span wire:loading
                                                wire:target="rejectRemaining({{ $photo->id }})">Отклонение...</span>
                                        </x-ui.button>
                                    </x-ui.alert-dialog-trigger>
                                    <x-ui.alert-dialog-content>
                                        <x-ui.alert-dialog-header>
                                            <x-ui.alert-dialog-title>Отклонить все комментарии</x-ui.alert-dialog-title>
                                            <x-ui.alert-dialog-description>
                                                Вы уверены? Будут отклонены все {{ $pendingCount }} комментариев к
                                                этому фото.
                                                <br><strong class="text-destructive">Это действие нельзя
                                                    отменить.</strong>
                                            </x-ui.alert-dialog-description>
                                        </x-ui.alert-dialog-header>
                                        <x-ui.alert-dialog-footer>
                                            <x-ui.alert-dialog-cancel>Отмена</x-ui.alert-dialog-cancel>
                                            <x-ui.alert-dialog-action
                                                wire:click="rejectRemaining({{ $photo->id }})">Отклонить
                                                все</x-ui.alert-dialog-action>
                                        </x-ui.alert-dialog-footer>
                                    </x-ui.alert-dialog-content>
                                </x-ui.alert-dialog>
                            </div>
                        @endif
                    </div>

                    <!-- Фото + Комментарии -->
                    <div class="flex flex-col md:flex-row">
                        <div
                            class="md:w-64 lg:w-80 shrink-0 border-r border-border bg-muted/10 p-4 flex items-center justify-center">
                            <div class="relative aspect-square bg-muted group overflow-hidden"
                                wire:key="photo-image-{{ $photo->id }}">
                                <a href="{{ $photo->large_url }}"
                                    data-fancybox="gallery-{{ $this->photos->currentPage() }}"
                                    data-caption="Фото #{{ $photo->id }} - {{ $photo->user?->name ?? 'Удален' }}"
                                    class="block w-full max-w-[200px] aspect-square bg-muted rounded-lg overflow-hidden cursor-pointer hover:opacity-90 transition-opacity">
                                    <img src="{{ $photo->thumb_url }}" alt="Photo"
                                        class="w-full h-full object-cover">
                                </a>

                                <div
                                    class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center pointer-events-none">
                                    <x-lucide-maximize-2 class="w-8 h-8 text-white drop-shadow-lg" />
                                </div>

                                <div class="absolute top-2 left-2 z-10 flex flex-col gap-1">
                                    @if ($photo->is_primary)
                                        <x-ui.badge>Аватар</x-ui.badge>
                                    @endif
                                    @if ($photo->is_intimate)
                                        <x-ui.badge variant="destructive">18+</x-ui.badge>
                                    @endif
                                    @if ($photo->album)
                                        <x-ui.badge variant="secondary"
                                            size="xs">{{ $photo->album->name }}</x-ui.badge>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex-1 p-4 space-y-3 max-h-[500px] overflow-y-auto">
                            @foreach ($photo->comments as $comment)
                                @php $commentDimmed = $this->statusFilter !== 'all' && $comment->status !== $this->statusFilter; @endphp

                                <div class="flex items-start gap-3 p-3 {{ $comment->status === 'pending' ? 'bg-yellow-500/5 border border-yellow-500/20' : 'bg-muted/10 border border-border' }} rounded-lg {{ $commentDimmed ? 'opacity-50' : '' }}"
                                    wire:key="comment-{{ $comment->id }}">
                                    <x-avatar src="{{ $comment->user?->avatar_url }}"
                                        name="{{ $comment->user?->name ?? 'Удален' }}" size="sm"
                                        class="shrink-0" />

                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            @if($comment->user)
                                                <a href="{{ route('admin.users.show', $comment->user->id) }}" wire:navigate class="font-medium text-sm hover:text-primary">{{ $comment->user->name }}</a>
                                                @if($comment->user->has_active_premium)
                                                    <x-ui.badge variant="warning" size="xs" class="p-1 flex items-center gap-1">
                                                        <x-lucide-crown class="w-3 h-3" />
                                                    </x-ui.badge>
                                                @endif
                                                @if($comment->user->is_banned)
                                                    <x-ui.badge variant="destructive" size="xs">Бан</x-ui.badge>
                                                @endif
                                            @else
                                                <span class="font-medium text-sm text-muted-foreground">Удален</span>
                                            @endif
                                            <span class="text-xs text-muted-foreground">{{ $comment->created_at->diffForHumans() }}</span>
                                            @php $badge = $this->getStatusBadge($comment->status); @endphp
                                            <x-ui.badge :variant="$badge['variant']" size="xs">{{ $badge['label'] }}</x-ui.badge>
                                        </div>
                                        <p class="text-sm mt-0.5">{{ $comment->content }}</p>
                                    </div>

                                    @if ($comment->status === 'pending')
                                        <div class="flex gap-1 shrink-0">
                                            <x-ui.button wire:click="approveComment({{ $comment->id }})"
                                                variant="ghost" size="icon-xs" title="Одобрить"
                                                wire:key="approve-{{ $comment->id }}"><x-lucide-check
                                                    class="w-4 h-4 text-green-500" /></x-ui.button>
                                            <x-ui.button wire:click="rejectComment({{ $comment->id }})"
                                                variant="ghost" size="icon-xs" title="Отклонить"
                                                wire:key="reject-{{ $comment->id }}"><x-lucide-x
                                                    class="w-4 h-4 text-yellow-500" /></x-ui.button>
                                            <x-ui.button wire:click="markSpam({{ $comment->id }})" variant="ghost"
                                                size="icon-xs" title="Пометить спамом"
                                                wire:key="spam-{{ $comment->id }}"><x-lucide-alert-circle
                                                    class="w-4 h-4 text-red-500" /></x-ui.button>
                                            <x-ui.button wire:click="deleteComment({{ $comment->id }})"
                                                variant="ghost" size="icon-xs" title="Удалить"
                                                wire:key="delete-{{ $comment->id }}"><x-lucide-trash-2
                                                    class="w-4 h-4 text-destructive" /></x-ui.button>
                                        </div>
                                    @elseif($comment->status === 'rejected' || $comment->status === 'spam')
                                        <div class="flex gap-1 shrink-0">
                                            <x-ui.button wire:click="restoreComment({{ $comment->id }})"
                                                variant="ghost" size="icon-xs" title="Восстановить"
                                                wire:key="restore-{{ $comment->id }}"><x-lucide-rotate-ccw
                                                    class="w-4 h-4 text-blue-500" /></x-ui.button>
                                            <x-ui.button wire:click="deleteComment({{ $comment->id }})"
                                                variant="ghost" size="icon-xs" title="Удалить"
                                                wire:key="delete-{{ $comment->id }}"><x-lucide-trash-2
                                                    class="w-4 h-4 text-destructive" /></x-ui.button>
                                        </div>
                                    @else
                                        <div class="flex gap-1 shrink-0">
                                            <x-ui.button wire:click="deleteComment({{ $comment->id }})"
                                                variant="ghost" size="icon-xs" title="Удалить"
                                                wire:key="delete-{{ $comment->id }}"><x-lucide-trash-2
                                                    class="w-4 h-4 text-destructive" /></x-ui.button>
                                        </div>
                                    @endif
                                </div>

                                @if ($comment->replies->count() > 0)
                                    <div class="pl-12 border-l-2 border-border space-y-2 -mt-2">
                                        @foreach ($comment->replies as $reply)
                                            <div class="flex items-start gap-2 p-2 {{ $reply->status === 'pending' ? 'bg-yellow-500/5 border border-yellow-500/20' : 'bg-muted/5 border border-border' }} rounded-lg"
                                                wire:key="reply-{{ $reply->id }}">
                                                <x-avatar src="{{ $reply->user?->avatar_url }}"
                                                    name="{{ $reply->user?->name ?? 'Удален' }}" size="xs"
                                                    class="shrink-0" />

                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center gap-2 flex-wrap">
                                                        @if($reply->user)
                                                            <a href="{{ route('admin.users.show', $reply->user->id) }}" wire:navigate class="font-medium text-xs hover:text-primary">{{ $reply->user->name }}</a>
                                                            @if($reply->user->has_active_premium)
                                                                <x-ui.badge variant="warning" size="xs" class="p-1 flex items-center gap-1">
                                                                    <x-lucide-crown class="w-3 h-3" />
                                                                </x-ui.badge>
                                                            @endif
                                                            @if($reply->user->is_banned)
                                                                <x-ui.badge variant="destructive" size="xs">Бан</x-ui.badge>
                                                            @endif
                                                        @else
                                                            <span class="font-medium text-xs text-muted-foreground">Удален</span>
                                                        @endif
                                                        <span class="text-[10px] text-muted-foreground">{{ $reply->created_at->diffForHumans() }}</span>
                                                        @php $replyBadge = $this->getStatusBadge($reply->status); @endphp
                                                        <x-ui.badge :variant="$replyBadge['variant']" size="xs">{{ $replyBadge['label'] }}</x-ui.badge>
                                                    </div>
                                                    <p class="text-xs mt-0.5">{{ $reply->content }}</p>
                                                </div>

                                                @if ($reply->status === 'pending')
                                                    <div class="flex gap-1 shrink-0">
                                                        <x-ui.button wire:click="approveComment({{ $reply->id }})"
                                                            variant="ghost" size="icon-xs" title="Одобрить"
                                                            wire:key="approve-reply-{{ $reply->id }}"><x-lucide-check
                                                                class="w-4 h-4 text-green-500" /></x-ui.button>
                                                        <x-ui.button wire:click="rejectComment({{ $reply->id }})"
                                                            variant="ghost" size="icon-xs" title="Отклонить"
                                                            wire:key="reject-reply-{{ $reply->id }}"><x-lucide-x
                                                                class="w-4 h-4 text-yellow-500" /></x-ui.button>
                                                        <x-ui.button wire:click="markSpam({{ $reply->id }})"
                                                            variant="ghost" size="icon-xs" title="Пометить спамом"
                                                            wire:key="spam-reply-{{ $reply->id }}"><x-lucide-alert-circle
                                                                class="w-4 h-4 text-red-500" /></x-ui.button>
                                                        <x-ui.button wire:click="deleteComment({{ $reply->id }})"
                                                            variant="ghost" size="icon-xs" title="Удалить"
                                                            wire:key="delete-reply-{{ $reply->id }}"><x-lucide-trash-2
                                                                class="w-4 h-4 text-destructive" /></x-ui.button>
                                                    </div>
                                                @elseif($reply->status === 'rejected' || $reply->status === 'spam')
                                                    <div class="flex gap-1 shrink-0">
                                                        <x-ui.button wire:click="restoreComment({{ $reply->id }})"
                                                            variant="ghost" size="icon-xs" title="Восстановить"
                                                            wire:key="restore-reply-{{ $reply->id }}"><x-lucide-rotate-ccw
                                                                class="w-4 h-4 text-blue-500" /></x-ui.button>
                                                        <x-ui.button wire:click="deleteComment({{ $reply->id }})"
                                                            variant="ghost" size="icon-xs" title="Удалить"
                                                            wire:key="delete-reply-{{ $reply->id }}"><x-lucide-trash-2
                                                                class="w-4 h-4 text-destructive" /></x-ui.button>
                                                    </div>
                                                @else
                                                    <div class="flex gap-1 shrink-0">
                                                        <x-ui.button wire:click="deleteComment({{ $reply->id }})"
                                                            variant="ghost" size="icon-xs" title="Удалить"
                                                            wire:key="delete-reply-{{ $reply->id }}"><x-lucide-trash-2
                                                                class="w-4 h-4 text-destructive" /></x-ui.button>
                                                    </div>
                                                @endif
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

        <div class="mt-6">
            {{ $this->photos->links('partials.pagination') }}
        </div>
    @endif

    <!-- Модалка превью -->
    @if ($showPreviewModal && $previewPhoto)
        <div x-data="{ open: true }" x-show="open" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100" @keydown.escape.window="open = false"
            wire:key="preview-modal-{{ $previewPhoto->id }}">

            <div class="absolute inset-0" @click="open = false"></div>

            <div class="relative max-w-3xl w-full">
                <button @click="open = false"
                    class="absolute -top-12 right-0 text-white hover:text-gray-300 transition-colors">
                    <x-lucide-x class="w-6 h-6" />
                </button>
                <img src="{{ $previewPhoto->large_url }}" alt="Photo preview"
                    class="w-full rounded-lg shadow-2xl" />
                <div class="absolute bottom-4 left-4 right-4 text-white text-sm bg-black/50 p-2 rounded-lg">
                    <p class="font-medium">Фото #{{ $previewPhoto->id }}</p>
                    <p class="text-gray-300 text-xs">{{ $previewPhoto->user?->name ?? 'Удален' }}</p>
                </div>
            </div>
        </div>
    @endif
</div>

@push('scripts')
    <script>
        document.addEventListener('livewire:init', () => {
            if (typeof Fancybox !== 'undefined') {
                Fancybox.defaults.Hash = false;
            }

            Livewire.hook('morph.updated', ({ el }) => {
                if (typeof Fancybox !== 'undefined' && el.querySelector && el.querySelector('[data-fancybox]')) {
                    Fancybox.unbind(el);
                    Fancybox.bind(el);
                }
            });
        });
    </script>
@endpush