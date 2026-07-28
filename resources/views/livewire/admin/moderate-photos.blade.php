<?php

use App\Models\Photo;
use App\Models\User;
use App\Jobs\ProcessApprovedPhoto;
use App\Notifications\PhotoModerated;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

/**
 * Компонент модерации фотографий.
 * Отвечает за просмотр ожидающих/одобренных фото, массовую обработку,
 * удаление файлов с диска и отправку уведомлений пользователям.
 */
new #[Layout('layouts.admin')] class extends Component {
    use WithPagination;

    /** @var string Текущий статус фильтра (pending, approved, all) */
    public $status = 'pending';

    /** @var int Количество пользователей на странице (для pending) */
    public $perPage = 5;

    /** @var int Количество фото на странице (для approved/all) */
    public $perPhotos = 12;

    /** @var string Поисковый запрос (имя или ID пользователя) */
    public $search = '';

    /**
     * Инициализация компонента.
     * Восстанавливает фильтры из сессии.
     */
    public function mount()
    {
        $saved = session('moderate_photos', []);

        if (isset($saved['status'])) {
            $this->status = $saved['status'];
        }
        if (isset($saved['search'])) {
            $this->search = $saved['search'];
        }
    }

    /**
     * Полное физическое удаление всех версий фото с диска.
     * Обрабатывает 4 размера (original, large, medium, thumb), чтобы не оставлять мусора.
     *
     * @param Photo $photo
     */
    private function deletePhotoFiles(Photo $photo): void
    {
        $paths = [
            $photo->path, // Дублирует medium, но на всякий случай
            $photo->path_original,
            $photo->path_large,
            $photo->path_medium,
            $photo->path_thumb,
        ];

        foreach ($paths as $path) {
            // Удаляем только локальные файлы, игнорируя внешние URL (например, аватарки из соцсетей)
            if ($path && !filter_var($path, FILTER_VALIDATE_URL)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    /**
     * Хук Livewire: срабатывает при смене статуса.
     * Сохраняет выбор в сессию и сбрасывает пагинацию.
     */
    public function updatedStatus(): void
    {
        session(['moderate_photos.status' => $this->status]);
        $this->resetPage();
    }

    /**
     * Хук Livewire: срабатывает при вводе поиска.
     * Сохраняет выбор в сессию и сбрасывает пагинацию.
     */
    public function updatedSearch(): void
    {
        session(['moderate_photos.search' => $this->search]);
        $this->resetPage();
    }

    /**
     * Вычисляемое свойство: подготовка данных для страницы.
     * Для pending — группирует фото по пользователям.
     * Для approved/all — выводит общим списком.
     */
    public function with(): array
    {
        if ($this->status == 'pending') {
            // Жадная загрузка: тянем только юзеров, у которых есть pending фото
            $users = User::withWhereHas('photos', function ($query) {
                $query->where('status', 'pending')->orderBy('is_primary', 'desc')->oldest();
            })
                ->excludeAdmins() 
                ->with([
                    'photos' => function ($query) {
                        $query->where('status', 'pending')->orderBy('is_primary', 'desc')->oldest()->with('album');
                    },
                ])
                ->paginate($this->perPage);

            $photos = collect();
        } else {
            $query = Photo::with(['user', 'album'])->excludeAdmins(); 

            if ($this->status == 'approved') {
                $query->where('status', 'approved')->latest();
            } else {
                $query->latest();
            }

            if (!empty($this->search)) {
                $search = trim($this->search);
                // Изолируем условия поиска в замыкание
                $query->where(function ($q) use ($search) {
                    // Поддержка регистронезависимого поиска для PostgreSQL
                    $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

                    $q->whereHas('user', function ($subQuery) use ($search, $operator) {
                        $subQuery->where('name', $operator, '%' . $search . '%');
                    });

                    if (is_numeric($search)) {
                        $q->orWhere('user_id', (int) $search);
                    }
                });
            }

            $photos = $query->paginate($this->perPhotos);
            $users = null;
        }

        // Оптимизация: получаем все счетчики одним SQL-запросом
        $counts = Photo::excludeAdmins()->selectRaw( // ✅ Исключаем админов из счетчиков
            "
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        ",
        )->first();

        return [
            'users' => $users,
            'photos' => $photos ?? collect(),
            'pendingCount' => (int) ($counts->pending ?? 0),
            'approvedCount' => (int) ($counts->approved ?? 0),
            'rejectedCount' => (int) ($counts->rejected ?? 0),
            'totalCount' => (int) ($counts->total ?? 0),
        ];
    }

    /**
     * Очистка поискового запроса.
     */
    public function clearSearch(): void
    {
        $this->search = '';
        session()->forget('moderate_photos.search');
        $this->resetPage();
    }

    /**
     * Одобрить ВСЕ pending-фото конкретного пользователя.
     *
     * @param int $userId
     */
    public function approveUser(int $userId): void
    {
        $user = User::find($userId);
        if (!$user) {
            return;
        }

        $photos = $user->photos()->where('status', 'pending')->get();
        $count = $photos->count();

        if ($count === 0) {
            $this->dispatch('show-toast', type: 'info', message: 'Нет фото для одобрения.');
            return;
        }

        $firstPhotoId = $photos->first()->id;

        DB::transaction(function () use ($photos) {
            foreach ($photos as $photo) {
                $photo->update(['status' => 'approved']);
            }
        });

        // Отправляем задания в очередь для создания WebP-версий
        foreach ($photos as $photo) {
            ProcessApprovedPhoto::dispatch($photo->id);
        }

        $user->notify(new PhotoModerated($firstPhotoId, $userId, 'approved', $count));

        $this->dispatch('show-toast', type: 'success', message: "Все фото пользователя {$user->name} ({$count} шт.) отправлены на обработку.");
        $this->dispatch('$refresh');
    }

    /**
     * Отклонить ВСЕ pending-фото конкретного пользователя.
     *
     * @param int $userId
     */
    public function rejectUser(int $userId): void
    {
        $user = User::find($userId);
        if (!$user) {
            return;
        }

        $photos = $user->photos()->where('status', 'pending')->get();
        $count = $photos->count();

        if ($count === 0) {
            $this->dispatch('show-toast', type: 'info', message: 'Нет фото для отклонения.');
            return;
        }

        $firstPhotoId = $photos->first()->id;

        // Сначала удаляем все файлы со всех фото с диска
        foreach ($photos as $photo) {
            $this->deletePhotoFiles($photo);
        }

        // Потом удаляем записи из БД
        DB::transaction(function () use ($photos) {
            foreach ($photos as $photo) {
                $photo->delete();
            }
        });

        $user->notify(new PhotoModerated($firstPhotoId, $userId, 'rejected', $count));

        $this->dispatch('show-toast', type: 'error', message: "Все фото пользователя {$user->name} ({$count} шт.) отклонены и удалены.");
        $this->dispatch('$refresh');
    }

    /**
     * Одобрить единичное фото.
     *
     * @param int $photoId
     */
    public function approve(int $photoId): void
    {
        $photo = Photo::find($photoId);
        if (!$photo) {
            return;
        }

        $photo->update(['status' => 'approved']);
        ProcessApprovedPhoto::dispatch($photoId);

        // Защита от NullPointer: автор фото мог удалить аккаунт
        if ($photo->user) {
            $photo->user->notify(new PhotoModerated($photo->id, $photo->user_id, 'approved', 1));
        }

        $this->dispatch('show-toast', type: 'success', message: 'Фото одобрено. Запущена обработка...');
        $this->dispatch('$refresh');
    }

    /**
     * Отклонить единичное фото (удаляет файлы и БД).
     *
     * @param int $photoId
     */
    public function reject(int $photoId): void
    {
        $photo = Photo::find($photoId);
        if (!$photo) {
            return;
        }

        $userId = $photo->user_id;
        $user = $photo->user;

        $this->deletePhotoFiles($photo);
        $photo->delete();

        if ($user) {
            $user->notify(new PhotoModerated($photo->id, $userId, 'rejected', 1));
        }

        $this->dispatch('show-toast', type: 'error', message: 'Фото отклонено и удалено.');
        $this->dispatch('$refresh');
    }

    /**
     * Полное удаление уже одобренного фото (из архива).
     *
     * @param int $photoId
     */
    public function destroy(int $photoId): void
    {
        $photo = Photo::find($photoId);
        if (!$photo) {
            return;
        }

        $userId = $photo->user_id;
        $user = $photo->user;

        // Удаляем все версии файла (оригинал, large, medium, thumb)
        $this->deletePhotoFiles($photo);

        $photo->delete();

        if ($user) {
            $user->notify(new PhotoModerated($photo->id, $userId, 'deleted', 1));
        }

        $this->dispatch('show-toast', type: 'success', message: 'Фото и все его версии удалены.');
        $this->dispatch('$refresh');
    }

    /**
     * Установить фото как основное (аватар).
     *
     * @param int $photoId
     */
    public function setPrimary(int $photoId): void
    {
        $photo = Photo::find($photoId);
        if ($photo) {
            // Обернуто в транзакцию: сначала снимаем флаг у старой аватарки, потом ставим новой
            DB::transaction(function () use ($photo) {
                Photo::where('user_id', $photo->user_id)->update(['is_primary' => false]);
                $photo->update(['is_primary' => true]);
            });

            $this->dispatch('show-toast', type: 'success', message: 'Фото установлено как основное.');
            $this->dispatch('$refresh');
        }
    }

    /**
     * Установка фильтра статуса.
     *
     * @param string $status
     */
    public function setStatus(string $status): void
    {
        $this->status = $status;
        session(['moderate_photos.status' => $status]);
        $this->resetPage();
    }
};
?>

<div class="space-y-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Модерация фотографий</h1>
        @if ($pendingCount > 0)
            <span class="bg-destructive/10 text-destructive px-3 py-1 rounded-full text-sm font-medium"
                wire:key="pending-badge">
                В очереди: {{ $pendingCount }} шт.
            </span>
        @endif
    </div>

    <!-- Фильтры + Поиск -->
    <div class="flex flex-wrap items-center gap-2">
        <div class="flex flex-wrap gap-2">
            <x-ui.button wire:click="setStatus('pending')" variant="{{ $status == 'pending' ? 'default' : 'secondary' }}"
                wire:key="filter-pending">
                Ожидают
                <x-ui.badge>{{ $pendingCount }}</x-ui.badge>
            </x-ui.button>

            <x-ui.button wire:click="setStatus('approved')"
                variant="{{ $status == 'approved' ? 'default' : 'secondary' }}" wire:key="filter-approved">
                Одобрены
                <x-ui.badge>{{ $approvedCount }}</x-ui.badge>
            </x-ui.button>

            <x-ui.button wire:click="setStatus('all')" variant="{{ $status == 'all' ? 'default' : 'secondary' }}"
                wire:key="filter-all">
                Все
                <x-ui.badge>{{ $totalCount }}</x-ui.badge>
            </x-ui.button>
        </div>

        <!-- Поиск (только для approved/all) -->
        @if ($status != 'pending')
            <div class="flex items-center gap-5 ml-auto">
                @if (!empty($search))
                    <span class="text-xs text-muted-foreground whitespace-nowrap" wire:key="search-count">
                        Найдено: {{ $photos->total() }}
                    </span>
                @endif

                <div class="relative" wire:key="search-wrapper">
                    <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Поиск по имени или ID..."
                        class="pl-9 pr-3 py-2 text-sm bg-card border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 w-64" />
                    @if (!empty($search))
                        <button wire:click="clearSearch"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
                            <x-lucide-x class="w-4 h-4" />
                        </button>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <!-- Список пользователей с фото (pending) -->
    @if ($status == 'pending')
        @if ($users->isEmpty())
            <div class="bg-card border border-border rounded-lg p-16 text-center" wire:key="empty-state">
                <div class="w-16 h-16 mx-auto rounded-full bg-muted flex items-center justify-center mb-4">
                    <x-lucide-check-circle class="w-8 h-8 text-muted-foreground" />
                </div>
                <h3 class="text-lg font-medium text-foreground">Очередь пуста!</h3>
                <p class="text-muted-foreground mt-1">Все фотографии проверены. Отличная работа.</p>
            </div>
        @else
            <div class="space-y-6">
                @foreach ($users as $user)
                    <div wire:key="user-{{ $user->id }}"
                        class="bg-card border border-border rounded-lg overflow-hidden">
                        <!-- Заголовок пользователя -->
                        <div class="p-4 border-b border-border flex items-center justify-between bg-muted/20">
                            <div class="flex items-center gap-3">
                                <x-avatar src="{{ $user->avatar_url }}" name="{{ $user->name ?? 'User' }}"
                                    size="lg" userId="{{ $user->id }}" showStatus="true" />
                                <div>
                                    <!--  Добавлены бейджи в шапку юзера -->
                                    <p class="font-semibold text-foreground flex items-center gap-2 flex-wrap">
                                        <a href="{{ route('admin.users.show', $user->id) }}" class="hover:text-primary">
                                            {{ $user->name ?? 'Без имени' }}
                                        </a>
                                        @if($user->has_active_premium)
                                            <x-ui.badge variant="warning" size="xs" class="p-1 flex items-center gap-1">
                                                <x-lucide-crown class="w-3 h-3" /> 
                                            </x-ui.badge>
                                        @endif
                                        @if($user->is_banned)
                                            <x-ui.badge variant="destructive" size="xs">Бан</x-ui.badge>
                                        @endif
                                    </p>
                                    <div class="flex items-center gap-3 text-xs text-muted-foreground">
                                        <span>ID: {{ $user->id }}</span>
                                        <span>•</span>
                                        <span>Фото: {{ $user->photos->count() }}</span>
                                        <span>•</span>
                                        <span>{{ $user->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Массовые действия -->
                            <div class="flex gap-2">
                                <x-ui.button wire:click="approveUser({{ $user->id }})" wire:loading.attr="disabled"
                                    wire:target="approveUser({{ $user->id }})" variant="success"
                                    wire:key="approve-user-{{ $user->id }}">
                                    <span wire:loading.remove wire:target="approveUser({{ $user->id }})">
                                        <x-lucide-check class="w-4 h-4 inline" />
                                        Одобрить все
                                    </span>
                                    <x-ui.spinner wire:loading wire:target="approveUser({{ $user->id }})"
                                        class="w-4 h-4" />
                                </x-ui.button>

                                <x-ui.alert-dialog wire:key="reject-user-dialog-{{ $user->id }}">
                                    <x-ui.alert-dialog-trigger>
                                        <x-ui.button variant="destructive" wire:loading.attr="disabled"
                                            wire:target="rejectUser({{ $user->id }})">
                                            <span wire:loading.remove wire:target="rejectUser({{ $user->id }})">
                                                <x-lucide-x class="w-4 h-4 inline" />
                                                Отклонить все
                                            </span>
                                            <x-ui.spinner wire:loading wire:target="rejectUser({{ $user->id }})"
                                                class="w-4 h-4" />
                                        </x-ui.button>
                                    </x-ui.alert-dialog-trigger>
                                    <x-ui.alert-dialog-content>
                                        <x-ui.alert-dialog-header>
                                            <x-ui.alert-dialog-title>Отклонить все фото</x-ui.alert-dialog-title>
                                            <x-ui.alert-dialog-description>
                                                Вы уверены, что хотите отклонить все фото пользователя
                                                <strong>{{ $user->name }}</strong>?
                                                <br><strong class="text-destructive">Это действие нельзя
                                                    отменить.</strong>
                                            </x-ui.alert-dialog-description>
                                        </x-ui.alert-dialog-header>
                                        <x-ui.alert-dialog-footer>
                                            <x-ui.alert-dialog-cancel>Отмена</x-ui.alert-dialog-cancel>
                                            <x-ui.alert-dialog-action wire:click="rejectUser({{ $user->id }})">
                                                <x-lucide-x class="w-4 h-4" />
                                                Отклонить все
                                            </x-ui.alert-dialog-action>
                                        </x-ui.alert-dialog-footer>
                                    </x-ui.alert-dialog-content>
                                </x-ui.alert-dialog>
                            </div>
                        </div>

                        <!-- Сетка фото пользователя -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 p-4">
                            @foreach ($user->photos as $photo)
                                <div wire:key="photo-{{ $photo->id }}"
                                    class="relative aspect-square bg-muted group overflow-hidden rounded-lg">
                                    <!-- ГАЛЕРЕЯ ПО ПОЛЬЗОВАТЕЛЮ -->
                                    <a href="{{ $photo->large_url ?? $photo->url }}"
                                        data-fancybox="gallery-{{ $user->id }}" data-caption="{{ $user->name }}"
                                        class="block w-full h-full">
                                        <img src="{{ $photo->medium_url ?? $photo->url }}" alt="Photo"
                                            class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                    </a>

                                    <!-- Бейджи -->
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

                                    <!-- Иконка увеличения -->
                                    <div
                                        class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center pointer-events-none">
                                        <x-lucide-maximize-2 class="w-6 h-6 text-white drop-shadow-lg" />
                                    </div>

                                    <!-- Кнопки действий над фото -->
                                    <div
                                        class="absolute bottom-2 left-2 right-2 z-10 flex gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <x-ui.button wire:click="approve({{ $photo->id }})"
                                            wire:loading.attr="disabled" wire:target="approve({{ $photo->id }})"
                                            variant="success" class="flex-1" wire:key="approve-{{ $photo->id }}">
                                            <span wire:loading.remove wire:target="approve({{ $photo->id }})">
                                                <x-lucide-check class="w-3.5 h-3.5 inline" />
                                                Да
                                            </span>
                                            <x-ui.spinner wire:loading wire:target="approve({{ $photo->id }})"
                                                class="w-3.5 h-3.5" />
                                        </x-ui.button>

                                        <x-ui.button wire:click="reject({{ $photo->id }})"
                                            wire:confirm="Отклонить и удалить это фото?" wire:loading.attr="disabled"
                                            wire:target="reject({{ $photo->id }})" variant="destructive"
                                            class="flex-1" wire:key="reject-{{ $photo->id }}">
                                            <span wire:loading.remove wire:target="reject({{ $photo->id }})">
                                                <x-lucide-x class="w-3.5 h-3.5 inline" />
                                                Нет
                                            </span>
                                            <x-ui.spinner wire:loading wire:target="reject({{ $photo->id }})"
                                                class="w-3.5 h-3.5" />
                                        </x-ui.button>

                                        <x-ui.button wire:click="setPrimary({{ $photo->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="setPrimary({{ $photo->id }})" variant="icon"
                                            title="Сделать основным" wire:key="primary-{{ $photo->id }}">
                                            <span wire:loading.remove wire:target="setPrimary({{ $photo->id }})">
                                                <x-lucide-star class="w-3.5 h-3.5" />
                                            </span>
                                            <x-ui.spinner wire:loading wire:target="setPrimary({{ $photo->id }})"
                                                class="w-3.5 h-3.5" />
                                        </x-ui.button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Пагинация пользователей -->
            <div class="mt-6" wire:key="user-pagination">
                {{ $users->links('partials.pagination') }}
            </div>
        @endif
    @else
        <!-- Для approved/all — обычный список фото -->
        @if ($photos->isEmpty())
            <div class="bg-card border border-border rounded-lg p-16 text-center" wire:key="photos-empty">
                <div class="w-16 h-16 mx-auto rounded-full bg-muted flex items-center justify-center mb-4">
                    <x-lucide-check-circle class="w-8 h-8 text-muted-foreground" />
                </div>
                <h3 class="text-lg font-medium text-foreground">
                    @if (!empty($search))
                        Ничего не найдено по запросу "{{ $search }}"
                    @else
                        Нет фотографий
                    @endif
                </h3>
                <p class="text-muted-foreground mt-1">
                    @if (!empty($search))
                        Попробуйте изменить поисковый запрос
                    @else
                        Фотографии с таким статусом отсутствуют.
                    @endif
                </p>
                @if (!empty($search))
                    <x-ui.button wire:click="clearSearch" variant="outline" class="mt-4"
                        wire:key="clear-search-btn">
                        Очистить поиск
                    </x-ui.button>
                @endif
            </div>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-6">
                @foreach ($photos as $photo)
                    <div wire:key="photo-{{ $photo->id }}"
                        class="bg-card border border-border rounded-lg overflow-hidden flex flex-col">
                        <!-- Фото -->
                        <div class="relative aspect-square bg-muted group overflow-hidden">
                            <!-- ГАЛЕРЕЯ ПО ПОЛЬЗОВАТЕЛЮ -->
                            <a href="{{ $photo->large_url ?? $photo->url }}"
                                data-fancybox="gallery-{{ $photo->user_id }}"
                                data-caption="{{ $photo->user->name }}" class="block w-full h-full">
                                <img src="{{ $photo->medium_url ?? $photo->url }}" alt="Photo"
                                    class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                            </a>

                            <div
                                class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center pointer-events-none">
                                <x-lucide-maximize-2 class="w-8 h-8 text-white drop-shadow-lg" />
                            </div>

                            <!-- Бейджи -->
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

                        <!-- Инфо -->
                        <div class="p-3 border-b border-border flex items-center gap-2">
                            <x-avatar src="{{ $photo->user->avatar_url }}" name="{{ $photo->user->name ?? 'User' }}"
                                size="sm" userId="{{ $photo->user->id }}" showStatus="true" />
                            <div class="text-sm overflow-hidden">
                                <!--  Убрал дубль имени и исправил wire:key на $photo->id -->
                                <p class="font-medium text-foreground truncate">
                                    <a href="{{ route('admin.users.show', $photo->user_id) }}" class="hover:text-primary flex gap-2 items-center">
                                        <span title="{{ $photo->user->name }}" class="text-sm font-medium">{{ $photo->user->name }}</span>
                                        @if($photo->user->has_active_premium)
                                            <x-ui.badge variant="warning" size="xs" wire:key="premium-badge-{{ $photo->id }}" class="p-1 flex items-center gap-1">
                                                <x-lucide-crown class="w-3 h-3" /> 
                                            </x-ui.badge>
                                        @endif  
                                        @if($photo->user->is_banned)
                                            <x-ui.badge variant="destructive" size="xs">Бан</x-ui.badge>
                                        @endif                                                                          
                                    </a>
                                </p>
                                <p class="text-xs text-muted-foreground">ID: {{ $photo->user_id }}</p>
                            </div>
                        </div>

                        <!-- Кнопки (строго в ряд через flex) -->
                        <div class="flex divide-x divide-border">
                            <!-- Кнопка удаления на всю ширину -->
                            <button wire:click="destroy({{ $photo->id }})"
                                wire:confirm="Удалить это фото навсегда?" wire:loading.attr="disabled"
                                wire:target="destroy({{ $photo->id }})" wire:key="destroy-{{ $photo->id }}"
                                class="flex items-center justify-center gap-2 py-3 text-sm font-medium text-destructive hover:bg-destructive/10 transition-colors w-full border-t border-border">
                                <span wire:loading.remove wire:target="destroy({{ $photo->id }})">
                                    <x-lucide-trash-2 class="w-4 h-4 inline" />
                                    Удалить
                                </span>
                                <x-ui.spinner wire:loading wire:target="destroy({{ $photo->id }})"
                                    class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6" wire:key="photos-pagination">
                {{ $photos->links('partials.pagination') }}
            </div>
        @endif
    @endif
</div>


@push('scripts')
    <script>
        if (typeof Fancybox !== 'undefined') {
            Fancybox.defaults.Hash = false;
        }
    </script>
@endpush>