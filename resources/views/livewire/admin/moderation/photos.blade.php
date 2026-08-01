<?php

use App\Models\Photo;
use App\Models\User;
use App\Models\AdminLog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    #[Url(as: 'status', except: 'pending')]
    public string $status = 'pending';
    
    #[Url(as: 'type', except: 'profile')]
    public string $type = 'profile'; // profile или verification

    public int $perPage = 5;
    public string $search = '';

    // Состояние модалки отклонения
    public ?int $rejectingPhotoId = null;
    public string $rejectReason = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    // === ДЕЙСТВИЯ ===

    public function approve(int $photoId): void
    {
        $photo = Photo::find($photoId);
        if (!$photo) return;

        $photo->markAsApproved(auth()->id());
        AdminLog::record('photo.approve', $photo, auth()->user());

        $this->dispatch('show-toast', type: 'success', message: 'Фото одобрено');
    }

    public function openRejectModal(int $photoId): void
    {
        $this->rejectingPhotoId = $photoId;
        $this->rejectReason = ''; // Сбрасываем причину
    }

    public function rejectPhoto(): void
    {
        $this->validate(['rejectReason' => 'required|string']);

        $photo = Photo::find($this->rejectingPhotoId);
        if (!$photo) return;

        $photo->markAsRejected(auth()->id(), $this->rejectReason);
        AdminLog::record('photo.reject', $photo, auth()->user(), ['status' => 'pending'], ['status' => 'rejected', 'reason' => $this->rejectReason]);

        $this->rejectingPhotoId = null;
        $this->rejectReason = '';
        $this->dispatch('show-toast', type: 'error', message: 'Фото отклонено');
    }

    public function setPrimary(int $photoId): void
    {
        $photo = Photo::find($photoId);
        if (!$photo || $photo->status !== 'approved') return;

        // Снимаем старую аватарку
        Photo::where('user_id', $photo->user_id)->update(['is_primary' => false]);
        $photo->update(['is_primary' => true]);

        $this->dispatch('show-toast', type: 'success', message: 'Установлено как аватар');
    }

    public function approveAllForUser(int $userId): void
    {
        $count = Photo::where('user_id', $userId)->where('status', 'pending')->where('type', $this->type)->count();
        
        Photo::where('user_id', $userId)->where('status', 'pending')->where('type', $this->type)
            ->each(fn($p) => $p->markAsApproved(auth()->id()));

        AdminLog::record('photo.mass_approve', User::find($userId), auth()->user(), null, ['count' => $count]);

        $this->dispatch('show-toast', type: 'success', message: "Одобрено {$count} фото");
    }

    // === ВЫВОД ДАННЫХ ===

    public function with(): array
    {
        $users = null;
        $photos = collect();

        // Считаем метрики для бейджей
        $counts = Photo::whereHas('user', fn($q) => $q->excludeStaff())
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
            ")->first();

        if ($this->status === 'pending') {
            // ОЧЕРЕДЬ: Группируем по юзерам для удобства
            $users = User::withWhereHas('photos', function ($query) {
                $query->where('status', 'pending')->where('type', $this->type)->oldest();
            })
            ->excludeStaff()
            ->withCount(['photos as pending_photos_count' => fn($q) => $q->where('status', 'pending')->where('type', $this->type)])
            ->with(['photos' => function ($query) {
                $query->where('status', 'pending')->where('type', $this->type)->oldest()->with('album:id,name');
            }])
            ->paginate($this->perPage);
     } else {
            // ИСТОРИЯ: Простая сетка с поиском
            $query = Photo::with([
                'user' => function ($q) {
                    $q->select('id', 'name', 'status', 'is_premium', 'is_verified')
                      ->with(['photos' => function ($sq) {
                          // Подгружаем только одобренные фото, чтобы аксессор avatar_url отработал без N+1
                          $sq->where('status', 'approved')
                             ->orderBy('is_primary', 'desc')
                             ->select('id', 'user_id', 'path_thumb', 'path_medium', 'is_primary')
                             ->limit(1);
                      }]);
                }, 
                'album:id,name'
            ])
            ->whereHas('user', fn($q) => $q->excludeStaff())
            ->where('type', $this->type);

            if ($this->status === 'approved') {
                $query->where('status', 'approved')->latest();
            } else {
                $query->where('status', 'rejected')->latest();
            }

            if (!empty($this->search)) {
                $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
                $query->where(function ($q) use ($operator) {
                    $q->whereHas('user', fn($sub) => $sub->where('name', $operator, "%{$this->search}%"));
                    if (is_numeric($this->search)) $q->orWhere('user_id', $this->search);
                });
            }

            $photos = $query->paginate(24);
        }

        return [
            'users' => $users,
            'photos' => $photos,
            'pendingCount' => (int) ($counts->pending ?? 0),
            'approvedCount' => (int) ($counts->approved ?? 0),
            'rejectedCount' => (int) ($counts->rejected ?? 0),
        ];
    }
}; 
?>

<div class="space-y-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Модерация фотографий</h1>
        @if ($pendingCount > 0)
            <span class="bg-destructive/10 text-destructive px-3 py-1 rounded-full text-sm font-medium animate-pulse">
                В очереди: {{ $pendingCount }} шт.
            </span>
        @endif
    </div>

    <!-- Фильтры -->
    <div class="flex flex-wrap items-center gap-3">
        <!-- Тип фото -->
        <div class="flex gap-2">
            <x-ui.button wire:click="$set('type', 'profile')" variant="{{ $type == 'profile' ? 'default' : 'secondary' }}">
                Анкеты
            </x-ui.button>
            <x-ui.button wire:click="$set('type', 'verification')" variant="{{ $type == 'verification' ? 'default' : 'secondary' }}">
                Верификация 🛡️
            </x-ui.button>
        </div>

        <div class="h-6 w-px bg-border"></div>

        <!-- Статус -->
        <div class="flex gap-2">
            <x-ui.button wire:click="$set('status', 'pending')" variant="{{ $status == 'pending' ? 'default' : 'secondary' }}">
                Ожидают <x-ui.badge>{{ $pendingCount }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="$set('status', 'approved')" variant="{{ $status == 'approved' ? 'default' : 'secondary' }}">
                Одобрены <x-ui.badge>{{ $approvedCount }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="$set('status', 'rejected')" variant="{{ $status == 'rejected' ? 'default' : 'secondary' }}">
                Отклонены <x-ui.badge>{{ $rejectedCount }}</x-ui.badge>
            </x-ui.button>
        </div>

        @if ($status != 'pending')
            <div class="ml-auto relative">
                <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Поиск по имени или ID..."
                    class="pl-9 pr-3 py-2 text-sm bg-card border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 w-64" />
            </div>
        @endif
    </div>

    <!-- КОНТЕНТ ОЧЕРЕДИ (Pending) -->
    @if ($status == 'pending')
        @if ($users->isEmpty())
            <div class="bg-card border border-border rounded-lg p-16 text-center">
                <x-lucide-check-circle class="w-12 h-12 mx-auto text-muted-foreground mb-4" />
                <h3 class="text-lg font-medium">Очередь пуста!</h3>
                <p class="text-muted-foreground mt-1">Все {{ $type === 'verification' ? 'верификации' : 'фотографии' }} проверены.</p>
            </div>
        @else
            <div class="space-y-6">
                @foreach ($users as $user)
                    <div wire:key="user-{{ $user->id }}" class="bg-card border border-border rounded-lg overflow-hidden">
                        <!-- Шапка юзера -->
                        <div class="p-4 border-b border-border flex items-center justify-between bg-muted/20">
                            <div class="flex items-center gap-3">
                                <x-avatar src="{{ $user->avatar_url }}" name="{{ $user->name }}" size="lg" />
                                <div>
                                    <a href="{{ route('admin.users.show', $user->id) }}" wire:navigate class="font-semibold text-foreground hover:text-primary flex items-center gap-2">
                                        {{ $user->name }}
                                        @if($user->is_verified) <x-lucide-badge-check class="w-4 h-4 text-blue-500" /> @endif
                                    </a>
                                    <div class="text-xs text-muted-foreground">
                                        ID: {{ $user->id }} • Ожидают: {{ $user->pending_photos_count }} шт.
                                    </div>
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <x-ui.button wire:click="approveAllForUser({{ $user->id }})" variant="success" size="sm">
                                    <x-lucide-check class="w-4 h-4" /> Одобрить все
                                </x-ui.button>
                            </div>
                        </div>

                        <!-- Сетка фото -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 p-4">
                            @foreach ($user->photos as $photo)
                                <div wire:key="photo-{{ $photo->id }}" class="relative aspect-square bg-muted group overflow-hidden rounded-lg">
                                    <a href="{{ $photo->original_url }}" data-fancybox="gallery-{{ $user->id }}" class="block w-full h-full">
                                        <img src="{{ $photo->medium_url }}" alt="Photo" loading="lazy" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                    </a>

                                    <!-- Бейджи -->
                                    <div class="absolute top-2 left-2 z-10 flex flex-col gap-1">
                                        @if ($photo->is_primary) <x-ui.badge size="xs">Аватар</x-ui.badge> @endif
                                        @if ($photo->is_intimate) <x-ui.badge variant="destructive" size="xs">18+</x-ui.badge> @endif
                                    </div>

                                    <!-- Кнопки действий (Появляются при ховере) -->
                                    <div class="absolute bottom-0 left-0 right-0 z-10 p-2 bg-gradient-to-t from-black/80 to-transparent flex gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <x-ui.button wire:click="approve({{ $photo->id }})" variant="success" class="flex-1 h-8 text-xs">
                                            <x-lucide-check class="w-3.5 h-3.5" /> Да
                                        </x-ui.button>

                                        <x-ui.button wire:click="openRejectModal({{ $photo->id }})" variant="destructive" class="flex-1 h-8 text-xs">
                                            <x-lucide-x class="w-3.5 h-3.5" /> Нет
                                        </x-ui.button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-6">{{ $users->links('partials.pagination') }}</div>
        @endif

    <!-- КОНТЕНТ ИСТОРИИ (Approved / Rejected) -->
    @else
        @if ($photos->isEmpty())
            <div class="bg-card border border-border rounded-lg p-16 text-center">
                <h3 class="text-lg font-medium">Ничего не найдено</h3>
            </div>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                @foreach ($photos as $photo)
                    <div wire:key="photo-{{ $photo->id }}" class="bg-card border border-border rounded-lg overflow-hidden">
                        <div class="relative aspect-square bg-muted group overflow-hidden">
                            <a href="{{ $photo->original_url }}" data-fancybox="history-gallery" class="block w-full h-full">
                                <img src="{{ $photo->medium_url }}" alt="Photo" loading="lazy" class="absolute inset-0 w-full h-full object-cover">
                            </a>
                            @if ($photo->status === 'rejected' && $photo->reject_reason)
                                <div class="absolute top-0 left-0 right-0 bg-destructive/90 text-white text-xs p-1 text-center">
                                    {{ $photo->reject_reason }}
                                </div>
                            @endif
                        </div>
                        <div class="p-2 text-xs text-muted-foreground truncate">
                            <a href="{{ route('admin.users.show', $photo->user_id) }}" wire:navigate class="hover:text-primary">
                                {{ $photo->user->name ?? 'Deleted' }} (ID: {{ $photo->user_id }})
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-6">{{ $photos->links('partials.pagination') }}</div>
        @endif
    @endif

    <!-- МОДАЛКА ОТКЛОНЕНИЯ (Alpine + Livewire) -->
    <div x-data="{ show: @entangle('rejectingPhotoId') }" x-show="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" style="display: none;">
        <div class="bg-card border border-border rounded-lg p-6 w-full max-w-md shadow-xl">
            <h3 class="text-lg font-semibold mb-4">Причина отклонения</h3>
            
            <div class="space-y-2 mb-4">
                <select wire:model="rejectReason" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm">
                    <option value="">Выберите причину...</option>
                    <option value="porn">Порнография</option>
                    <option value="minor">Несовершеннолетний</option>
                    <option value="ad">Реклама / Контакты</option>
                    <option value="stolen">Чужое фото</option>
                    <option value="low_quality">Плохое качество</option>
                    <option value="other">Другое</option>
                </select>

                @error('rejectReason') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end gap-2">
                <x-ui.button wire:click="$set('rejectingPhotoId', null)">Отмена</x-ui.button>
                <x-ui.button wire:click="rejectPhoto" variant="destructive">Отклонить фото</x-ui.button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('livewire:init', () => {
            if (typeof Fancybox !== 'undefined') Fancybox.defaults.Hash = false;
            Livewire.hook('morph.updated', ({ el }) => {
                if (typeof Fancybox !== 'undefined' && el.querySelector && el.querySelector('[data-fancybox]')) {
                    Fancybox.unbind(el);
                    Fancybox.bind(el);
                }
            });
        });
    </script>
@endpush