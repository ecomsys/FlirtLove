<?php

use App\Actions\Admin\ModeratePhotoAction;
use App\Enums\PhotoRejectReason;
use App\Models\AdminLog;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Enum;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    #[Url(as: 'status', except: 'pending')]
    public string $status = 'pending';

    public int $perPage = 5; 
    public int $perPhotos = 24; 
    
    #[Url(as: 'q', except: '')]
    public string $search = '';

    public bool $isRejectModalVisible = false;
    public ?int $rejectingPhotoId = null;
    public string $rejectReason = '';

    public function mount(): void
    {
        if (request()->has('q')) {
            $this->status = 'approved';
            return;
        }
        $this->status = session('moderate_photos.status', 'pending');
    }

    public function updatedStatus(): void { $this->resetPage(); }
    public function updatedSearch(): void { $this->resetPage(); }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->search = '';
        session(['moderate_photos.status' => $status]);
        $this->resetPage();
    }

    public function approve(int $photoId, ModeratePhotoAction $action): void
    {
        $photo = Photo::find($photoId);
        if (!$photo) return;
        $action->approve($photo, auth()->user());
        $this->dispatch('show-toast', type: 'success', message: 'Фото одобрено. Запущена обработка...');
    }

    public function openRejectModal(int $photoId): void
    {
        $this->rejectingPhotoId = $photoId;
        $this->rejectReason = '';
        $this->isRejectModalVisible = true;
    }

    public function closeRejectModal(): void
    {
        $this->isRejectModalVisible = false;
        $this->rejectingPhotoId = null;
    }

    public function rejectPhoto(ModeratePhotoAction $action): void
    {
        $this->validate([
            'rejectReason' => ['required', new Enum(PhotoRejectReason::class)],
        ]);

        $photo = Photo::find($this->rejectingPhotoId);
        if (!$photo) {
            $this->closeRejectModal();
            return;
        }

        $action->reject($photo, auth()->user(), $this->rejectReason);

        $this->closeRejectModal();
        $this->dispatch('show-toast', type: 'error', message: 'Фото отклонено');
    }

    public function setPrimary(int $photoId, ModeratePhotoAction $action): void
    {
        $photo = Photo::find($photoId);
        if (!$photo) return;
        $action->setPrimary($photo, auth()->user());
        $this->dispatch('show-toast', type: 'success', message: 'Установлено как аватар');
    }

    public function softDelete(int $photoId): void
    {
        $photo = Photo::find($photoId);
        if (!$photo) return;

        $photo->delete();
        AdminLog::record('photo.soft_delete', $photo, auth()->user(), ['deleted_at' => null], ['deleted_at' => now()]);
        $this->dispatch('show-toast', type: 'warning', message: 'Фото перемещено в карантин');
    }

    public function restorePhoto(int $photoId): void
    {
        $photo = Photo::withTrashed()->find($photoId);
        if (!$photo) return;

        DB::transaction(function () use ($photo) {
            $photo->restore();
            $photo->update([
                'status' => 'pending',
                'reject_reason' => null,
                'moderated_by' => null,
                'moderated_at' => null,
            ]);
        });

        AdminLog::record('photo.restore', $photo, auth()->user(), ['status' => 'rejected', 'deleted_at' => 'set'], ['status' => 'pending', 'deleted_at' => null]);
        $this->dispatch('show-toast', type: 'success', message: 'Фото восстановлено в очередь');
    }

    public function destroy(int $photoId, ModeratePhotoAction $action): void
    {
        $photo = Photo::withTrashed()->find($photoId);
        if (!$photo) return;

        $action->destroy($photo, auth()->user());
        $this->dispatch('show-toast', type: 'success', message: 'Фото навсегда удалено.');
    }

    public function approveAllForUser(int $userId, ModeratePhotoAction $action): void
    {
        $user = User::find($userId);
        if (!$user) return;
        $count = $action->approveAllForUser($user, auth()->user());
        $this->dispatch('show-toast', type: $count > 0 ? 'success' : 'info', message: $count > 0 ? "Одобрено {$count} фото" : 'Нет фото для одобрения.');
    }

    public function rejectAllForUser(int $userId, ModeratePhotoAction $action): void
    {
        $user = User::find($userId);
        if (!$user) return;
        $count = $action->rejectAllForUser($user, auth()->user());
        $this->dispatch('show-toast', type: $count > 0 ? 'error' : 'info', message: $count > 0 ? "Отклонено {$count} фото" : 'Нет фото для отклонения.');
    }

    public function with(): array
    {
        $users = collect();
        $photos = collect();

        // Обновленный подсчет счетчиков (добавлен quarantine)
        $counts = Photo::withTrashed()
            ->whereHas('user', fn($q) => $q->excludeStaff())
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' AND deleted_at IS NULL THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' AND deleted_at IS NULL THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' AND deleted_at IS NULL THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) as quarantine
            ")->first();

        $isSearching = !empty($this->search);

        if ($this->status === 'pending' && !$isSearching) {
            $users = User::withWhereHas('photos', function ($query) {
                $query->where('status', 'pending');
            })
            ->excludeStaff()
            ->withCount(['photos as pending_photos_count' => fn($q) => $q->where('status', 'pending')])
            ->with(['photos' => function ($query) {
                $query->whereIn('status', ['pending', 'approved'])
                      ->orderBy('is_primary', 'desc')
                      ->oldest()
                      ->with('album:id,name'); 
            }])
            ->orderByDesc(function ($query) {
                $query->selectRaw('MAX(created_at)')
                      ->from('photos')
                      ->whereColumn('photos.user_id', 'users.id')
                      ->where('photos.status', 'pending');
            })
            ->paginate($this->perPage);
        } else {
            $query = Photo::withTrashed()->with([
                'user' => function ($q) {
                    $q->select('id', 'name', 'status', 'is_premium', 'premium_expires_at', 'is_verified', 'last_seen')
                      ->with(['photos' => fn($sq) => $sq->select('id', 'user_id', 'status', 'is_primary', 'path_thumb', 'path_medium', 'path_large', 'path_original')->orderByDesc('is_primary')->limit(1)]);
                }, 
                'album:id,name'
            ])
            ->whereHas('user', fn($q) => $q->excludeStaff());

            if (!$isSearching) {
                if ($this->status === 'quarantine') {
                    // ФИКС: Карантин - это всё, где есть deleted_at
                    $query->whereNotNull('deleted_at');
                } else {
                    // ФИКС: Для остальных вкладок ищем строго статус и отсутствие deleted_at
                    $query->where('status', $this->status)->whereNull('deleted_at');
                }
            }

            if ($isSearching) {
                $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
                $query->where(function ($q) use ($operator) {
                    if (is_numeric($this->search)) {
                        $q->where('id', $this->search)->orWhere('user_id', $this->search);
                    } else {
                        $q->whereHas('user', fn($sub) => $sub->where('name', $operator, "%{$this->search}%"));
                    }
                });
            }

            $photos = $query->latest()->paginate($this->perPhotos);
        }

        return [
            'users' => $users,
            'photos' => $photos,
            'pendingCount' => (int) ($counts->pending ?? 0),
            'approvedCount' => (int) ($counts->approved ?? 0),
            'rejectedCount' => (int) ($counts->rejected ?? 0),
            'quarantineCount' => (int) ($counts->quarantine ?? 0),
            'totalCount' => (int) ($counts->total ?? 0),
        ];
    }
}; 
?>

<div class="space-y-6">
    <!-- Заголовок -->
    <div class="flex items-center gap-4">
        @php
            $previousUrl = url()->previous();
            $backUrl = ($previousUrl && $previousUrl !== url()->current()) 
                ? $previousUrl 
                : route('admin.dashboard');
        @endphp

        <a href="{{ $backUrl }}" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors">
            <x-lucide-arrow-left class="w-5 h-5" />
        </a>
        <h1 class="text-2xl font-semibold flex items-center gap-2">Модерация фотографий</h1>
        @if ($pendingCount > 0)
            <span class="bg-destructive/10 text-destructive px-3 py-1 rounded-full text-sm font-medium animate-pulse">
                В очереди: {{ $pendingCount }} шт.
            </span>
        @endif
    </div>

    <!-- Фильтры -->
    <div class="flex flex-wrap items-center gap-3">
         <div class="flex gap-2">
            <x-ui.button wire:click="setStatus('pending')" variant="{{ $status == 'pending' ? 'default' : 'secondary' }}">
                Ожидают <x-ui.badge>{{ $pendingCount }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="setStatus('approved')" variant="{{ $status == 'approved' ? 'default' : 'secondary' }}">
                Одобрены <x-ui.badge>{{ $approvedCount }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="setStatus('rejected')" variant="{{ $status == 'rejected' ? 'default' : 'secondary' }}">
                Отклонены <x-ui.badge>{{ $rejectedCount }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="setStatus('quarantine')" variant="{{ $status == 'quarantine' ? 'default' : 'secondary' }}">
                Карантин <x-ui.badge>{{ $quarantineCount }}</x-ui.badge>
            </x-ui.button>
        </div>

        @if ($status != 'pending')
            <div class="flex items-center gap-5 ml-auto">
                @if (!empty($search))
                    <span class="text-xs text-muted-foreground whitespace-nowrap">Найдено: {{ $photos->total() }}</span>
                @endif
                <div class="relative w-64">
                    <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground z-10" />
                    <x-ui.input wire:model.live.debounce.300ms="search" type="text" placeholder="Поиск по имени или ID..." class="pl-9 pr-8" />
                    @if (!empty($search))
                        <button wire:click="clearSearch" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
                            <x-lucide-x class="w-4 h-4" />
                        </button>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <!-- КОНТЕНТ ОЧЕРЕДИ (Pending) - КАРТОЧКИ ЮЗЕРОВ -->
    @if ($status == 'pending')
        @if ($users->isEmpty())
            <div class="bg-card border border-border rounded-lg p-16 text-center">
                <div class="w-16 h-16 mx-auto rounded-full bg-muted flex items-center justify-center mb-4">
                    <x-lucide-check-circle class="w-8 h-8 text-muted-foreground" />
                </div>
                <h3 class="text-lg font-medium">Очередь пуста!</h3>
                <p class="text-muted-foreground mt-1">Все фотографии проверены. Отличная работа.</p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-6">
                @foreach ($users as $user)
                    <div wire:key="user-{{ $user->id }}" class="bg-card border border-border rounded-xl shadow-sm overflow-hidden flex flex-col">
                        <div class="p-4 bg-muted/30 border-b border-border flex items-center justify-between gap-4 flex-wrap">
                            <div class="flex items-center gap-3">
                                <x-avatar src="{{ $user->avatar_url }}" name="{{ $user->name }}" size="md" userId="{{ $user->id }}" showStatus="true" :isOnline="$user->is_online"/>
                                <div>
                                    <a href="{{ route('admin.users.show', $user->id) }}" wire:navigate class="font-semibold text-foreground hover:text-primary flex items-center gap-2">                                        
                                        <span>
                                            <x-user-status-sign :user="$user" />
                                            {{ $user->name }}
                                        </span>
                                        @if($user->has_active_premium)
                                            <x-lucide-crown class="w-4 h-4 text-yellow-500" />
                                        @endif
                                        @if($user->status === 'banned') <x-ui.badge variant="destructive" size="xs">Бан</x-ui.badge> @endif
                                    </a>
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground mt-1">
                                        <span>ID: {{ $user->id }}</span>
                                        <span>•</span>
                                        <span>В очереди: {{ $user->pending_photos_count }}</span>
                                        <span>•</span>
                                        <span>{{ $user->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex gap-2 ml-auto">
                                <x-ui.button wire:click="approveAllForUser({{ $user->id }})" wire:loading.attr="disabled" wire:target="approveAllForUser({{ $user->id }})" variant="success" size="sm">
                                    <span wire:loading.remove wire:target="approveAllForUser({{ $user->id }})">
                                        <x-lucide-check class="w-4 h-4 inline" /> Принять все
                                    </span>
                                    <x-lucide-loader-2 wire:loading wire:target="approveAllForUser({{ $user->id }})" class="w-4 h-4 animate-spin" />
                                </x-ui.button>

                                <x-ui.alert-dialog>
                                    <x-ui.alert-dialog-trigger>
                                        <x-ui.button variant="destructive" size="sm" wire:loading.attr="disabled" wire:target="rejectAllForUser({{ $user->id }})">
                                            <span wire:loading.remove wire:target="rejectAllForUser({{ $user->id }})">
                                                <x-lucide-x class="w-4 h-4 inline" /> Отклонить все
                                            </span>
                                            <x-lucide-loader-2 wire:loading wire:target="rejectAllForUser({{ $user->id }})" class="w-4 h-4 animate-spin" />
                                        </x-ui.button>
                                    </x-ui.alert-dialog-trigger>
                                    <x-ui.alert-dialog-content>
                                        <x-ui.alert-dialog-header>
                                            <x-ui.alert-dialog-title>Отклонить все фото?</x-ui.alert-dialog-title>
                                            <x-ui.alert-dialog-description>
                                                Вы уверены, что хотите отклонить все фото пользователя <strong>{{ $user->name }}</strong>?
                                            </x-ui.alert-dialog-description>
                                        </x-ui.alert-dialog-header>
                                        <x-ui.alert-dialog-footer>
                                            <x-ui.alert-dialog-cancel>Отмена</x-ui.alert-dialog-cancel>
                                            <x-ui.alert-dialog-action wire:click="rejectAllForUser({{ $user->id }})">Отклонить все</x-ui.alert-dialog-action>
                                        </x-ui.alert-dialog-footer>
                                    </x-ui.alert-dialog-content>
                                </x-ui.alert-dialog>
                            </div>
                        </div>

                        <div class="p-4 grid grid-cols-3 md:grid-cols-5 gap-3 flex-1 bg-card">
                            @foreach ($user->photos->where('status', 'pending') as $photo)
                                @php 
                                    $imgSrc = $photo->medium_url ?: $photo->original_url ?: asset('images/no-image-placeholder.png');
                                    $fullSrc = $photo->original_url ?: $photo->medium_url ?: '#';
                                @endphp
                                <div wire:key="photo-{{ $photo->id }}" class="relative aspect-square bg-muted group overflow-hidden rounded-lg">
                                    <a href="{{ $fullSrc }}" data-fancybox="gallery-{{ $user->id }}" data-caption="{{ $user->name }}" class="block w-full h-full">                                        
                                        <img src="{{ $imgSrc }}" alt="Photo" loading="lazy" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                    </a>

                                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center pointer-events-none">
                                        <x-lucide-maximize-2 class="w-6 h-6 text-white drop-shadow-lg" />
                                    </div>

                                    <div class="absolute top-2 right-2 z-10 inline-flex flex-col gap-1">                                
                                        <span class="bg-black/60 text-white text-[0.625rem] px-1.5 py-0.5 rounded font-medium">#{{ $photo->id }}</span>
                                    </div>      
                                    
                                    <div class="absolute top-2 left-2 z-10 flex flex-col gap-1">                                       
                                        @if ($photo->is_primary) <x-ui.badge size="xs">Аватар</x-ui.badge> @endif
                                        @if ($photo->is_intimate) <x-ui.badge variant="destructive" size="xs">18+</x-ui.badge> @endif
                                        @if ($photo->album) <x-ui.badge variant="secondary" size="xs">{{ $photo->album->name }}</x-ui.badge> @endif
                                    </div>

                                    <div class="absolute bottom-2 left-2 right-2 z-10 flex gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <x-ui.button wire:click="approve({{ $photo->id }})" wire:loading.attr="disabled" wire:target="approve({{ $photo->id }})" variant="success" class="flex-1 h-8 text-xs">
                                            <span wire:loading.remove wire:target="approve({{ $photo->id }})"><x-lucide-check class="w-3.5 h-3.5 inline" /> Да</span>
                                            <x-lucide-loader-2 wire:loading wire:target="approve({{ $photo->id }})" class="w-3.5 h-3.5 animate-spin inline" />
                                        </x-ui.button>

                                        <x-ui.button wire:click="openRejectModal({{ $photo->id }})" variant="destructive" class="flex-1 h-8 text-xs">
                                            <x-lucide-x class="w-3.5 h-3.5 inline" /> Нет
                                        </x-ui.button>

                                        <x-ui.button wire:click="setPrimary({{ $photo->id }})" wire:loading.attr="disabled" wire:target="setPrimary({{ $photo->id }})" variant="icon" title="Сделать основным" class="h-8 w-8">
                                            <span wire:loading.remove wire:target="setPrimary({{ $photo->id }})"><x-lucide-star class="w-3.5 h-3.5 inline" /></span>
                                            <x-lucide-loader-2 wire:loading wire:target="setPrimary({{ $photo->id }})" class="w-3.5 h-3.5 animate-spin inline" />
                                        </x-ui.button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-6" wire:key="pagination-pending">{{ $users->links('partials.pagination') }}</div>
        @endif

    <!-- КОНТЕНТ ИСТОРИИ (Approved / Rejected) -->
    @else
        @if ($photos->isEmpty())
            <div class="bg-card border border-border rounded-lg p-16 text-center">
                <div class="w-16 h-16 mx-auto rounded-full bg-muted flex items-center justify-center mb-4">
                    <x-lucide-check-circle class="w-8 h-8 text-muted-foreground" />
                </div>
                <h3 class="text-lg font-medium">
                    @if (!empty($search)) Ничего не найдено @else Нет фотографий @endif
                </h3>
                <p class="text-muted-foreground mt-1">
                    @if (!empty($search)) Попробуйте изменить поисковый запрос @else Фотографии с таким статусом отсутствуют. @endif
                </p>
                @if (!empty($search))
                    <x-ui.button wire:click="clearSearch" variant="outline" class="mt-4">Очистить поиск</x-ui.button>
                @endif
            </div>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-6">
                @foreach ($photos as $photo)
                    @php 
                        $imgSrc = $photo->medium_url ?: $photo->original_url ?: asset('images/no-image-placeholder.png');
                        $fullSrc = $photo->original_url ?: $photo->medium_url ?: '#';
                        $isRejected = $photo->status === 'rejected';
                    @endphp
                    <div wire:key="photo-{{ $photo->id }}" class="bg-card border border-border rounded-lg overflow-hidden flex flex-col">
                        <div class="relative aspect-square bg-muted group overflow-hidden">
                            <a href="{{ $fullSrc }}" data-fancybox="gallery-{{ $photo->user_id }}" data-caption="{{ $photo->user?->name }}" class="block w-full h-full">
                                <img src="{{ $imgSrc }}" alt="Photo" loading="lazy" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-300 {{ $photo->trashed() ? 'opacity-50 grayscale' : '' }}">
                            </a>

                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center pointer-events-none">
                                <x-lucide-maximize-2 class="w-8 h-8 text-white drop-shadow-lg" />
                            </div>

                            <div class="absolute top-2 left-2 z-10 flex flex-col gap-1">                                
                                @if ($photo->is_primary) <x-ui.badge size="xs">Аватар</x-ui.badge> @endif
                                @if ($photo->is_intimate) <x-ui.badge variant="destructive" size="xs">18+</x-ui.badge> @endif
                                @if ($photo->album) <x-ui.badge variant="secondary" size="xs">{{ $photo->album->name }}</x-ui.badge> @endif
                            </div>

                            <div class="absolute top-2 right-2 z-10 inline-flex flex-col gap-1">                                
                                <span class="bg-black/60 text-white text-[0.625rem] px-1.5 py-0.5 rounded font-medium">#{{ $photo->id }}</span>
                            </div>            

                            @if ($photo->status === 'rejected' && $photo->reject_reason && !$photo->trashed())
                                @php
                                    $reasonLabel = \App\Enums\PhotoRejectReason::tryFrom($photo->reject_reason)?->label() ?? $photo->reject_reason;
                                @endphp
                                <div class="absolute bottom-2 left-2 bg-destructive/90 text-white text-[0.625rem] px-2 py-0.5 m-1 rounded-sm font-medium">
                                    {{ $reasonLabel }}
                                </div>
                            @endif
                        </div>

                        <div class="p-3 border-b border-border flex items-center gap-2">                            
                            @if($photo->user)
                                <x-avatar src="{{ $photo->user->avatar_url }}" name="{{ $photo->user->name }}" size="sm" userId="{{ $photo->user->id }}" showStatus="true" :isOnline="$photo->user->is_online"/>
                                <div class="text-sm overflow-hidden">
                                    <p class="font-medium text-foreground truncate">
                                        <a href="{{ route('admin.users.show', $photo->user_id) }}" wire:navigate class="hover:text-primary flex gap-2 items-center">                                        
                                            <span>
                                                <x-user-status-sign :user="$photo->user" />
                                                <span title="{{ $photo->user->name }}">{{ $photo->user->name }}</span>
                                            </span>                                        
                                            @if($photo->user->has_active_premium)
                                                <x-lucide-crown class="w-3 h-3 text-yellow-500" />
                                            @endif                                                                          
                                        </a>
                                    </p>
                                    <p class="text-xs text-muted-foreground">ID: {{ $photo->user_id }}</p>
                                </div>
                            @else
                                <div class="text-sm overflow-hidden flex items-center gap-2">
                                    <x-avatar name="Deleted" size="sm" />
                                    <p class="font-medium text-muted-foreground truncate">Пользователь удален</p>
                                </div>
                            @endif
                        </div>    
                        
                        {{-- КНОПКИ ДЕЙСТВИЙ В ЗАВИСИМОСТИ ОТ СТАТУСА --}}
                        <div class="p-2 flex gap-2">
                            @if ($photo->trashed())
                                {{-- В КАРТАНТИНЕ: Можно только вернуть или сжечь --}}
                                <x-ui.button wire:click="restorePhoto({{ $photo->id }})" wire:target="restorePhoto({{ $photo->id }})" variant="success" size="sm" class="flex-1 h-8 text-xs">
                                    <span wire:loading.remove wire:target="restorePhoto({{ $photo->id }})">Вернуть</span>
                                    <x-lucide-loader-2 wire:loading wire:target="restorePhoto({{ $photo->id }})" class="w-4 h-4 animate-spin" />
                                </x-ui.button>
                                <x-ui.button wire:click="destroy({{ $photo->id }})" wire:confirm="Удалить фото НАВСЕГДА?" wire:target="destroy({{ $photo->id }})" variant="destructive" size="sm" class="flex-1 h-8 text-xs">
                                    <span wire:loading.remove wire:target="destroy({{ $photo->id }})">Удалить навсегда</span>
                                    <x-lucide-loader-2 wire:loading wire:target="destroy({{ $photo->id }})" class="w-4 h-4 animate-spin" />
                                </x-ui.button>
                            @elseif ($photo->status === 'approved')
                                {{-- ОДОБРЕННЫЕ: Можно отклонить или удалить в карантин --}}
                                <x-ui.button wire:click="openRejectModal({{ $photo->id }})" variant="warning" size="sm" class="flex-1 h-8 text-xs">Отклонить</x-ui.button>
                                <x-ui.button wire:click="softDelete({{ $photo->id }})" wire:confirm="Переместить в карантин?" wire:target="softDelete({{ $photo->id }})" variant="destructive" size="sm" class="flex-1 h-8 text-xs">
                                    <span wire:loading.remove wire:target="softDelete({{ $photo->id }})">Удалить</span>
                                    <x-lucide-loader-2 wire:loading wire:target="softDelete({{ $photo->id }})" class="w-4 h-4 animate-spin" />
                                </x-ui.button>
                            @elseif ($photo->status === 'rejected')
                                {{-- ОТКЛОНЕННЫЕ: Можно переодобрить или удалить в карантин --}}
                                <x-ui.button wire:click="approve({{ $photo->id }})" wire:target="approve({{ $photo->id }})" variant="success" size="sm" class="flex-1 h-8 text-xs">
                                    <span wire:loading.remove wire:target="approve({{ $photo->id }})">Одобрить</span>
                                    <x-lucide-loader-2 wire:loading wire:target="approve({{ $photo->id }})" class="w-4 h-4 animate-spin" />
                                </x-ui.button>
                                <x-ui.button wire:click="softDelete({{ $photo->id }})" wire:confirm="Переместить в карантин?" wire:target="softDelete({{ $photo->id }})" variant="destructive" size="sm" class="flex-1 h-8 text-xs">
                                    <span wire:loading.remove wire:target="softDelete({{ $photo->id }})">Удалить</span>
                                    <x-lucide-loader-2 wire:loading wire:target="softDelete({{ $photo->id }})" class="w-4 h-4 animate-spin" />
                                </x-ui.button>
                            @endif
                        </div>                                                               
                    </div>
                @endforeach
            </div>
            <div class="mt-6" wire:key="pagination-history">{{ $photos->links('partials.pagination') }}</div>
        @endif
    @endif

    <!-- МОДАЛКА ОТКЛОНЕНИЯ (Починенная) -->
    <div x-data="{ show: @entangle('isRejectModalVisible') }" x-show="show" x-cloak 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" 
         style="display: none;"
         @click.self="$wire.closeRejectModal()"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         @keydown.escape.window="$wire.closeRejectModal()">
         
        <div class="relative bg-card border border-border rounded-lg shadow-2xl max-w-md w-full mx-4 overflow-hidden">
            <div class="p-6 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-destructive/10 rounded-full">
                        <x-lucide-shield-x class="w-6 h-6 text-destructive" />
                    </div>
                    <h2 class="text-lg font-semibold">Отклонить фото?</h2>
                </div>
                <p class="text-sm text-muted-foreground">Выберите причину отклонения. Пользователь получит уведомление.</p>

                <div class="space-y-2">
                    <x-ui.label for="rejectReason" class="text-xs">Причина отклонения</x-ui.label>
                    
                    <x-ui.select wire:model="rejectReason">
                        <x-ui.select-trigger class="w-full"><x-ui.select-value placeholder="Выберите причину..." /></x-ui.select-trigger>
                        <x-ui.select-content>
                            <x-ui.select-item value="">Выберите причину...</x-ui.select-item>
                            @foreach (\App\Enums\PhotoRejectReason::options() as $value => $label)
                                <x-ui.select-item value="{{ $value }}" wire:key="reason-{{ $value }}">{{ $label }}</x-ui.select-item>
                            @endforeach
                        </x-ui.select-content>
                    </x-ui.select>

                    @error('rejectReason') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 p-4 border-t border-border bg-muted/20">
                <x-ui.button @click="$wire.closeRejectModal()" variant="outline" size="sm">Отмена</x-ui.button>
                <x-ui.button wire:click="rejectPhoto" variant="destructive" size="sm" wire:loading.attr="disabled" wire:target="rejectPhoto">
                    <span wire:loading.remove wire:target="rejectPhoto">Отклонить фото</span>
                    <x-lucide-loader-2 wire:loading wire:target="rejectPhoto" class="w-4 h-4 animate-spin" />
                </x-ui.button>
            </div>
        </div>
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