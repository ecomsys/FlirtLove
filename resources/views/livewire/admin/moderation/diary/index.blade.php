<?php

use App\Models\AdminLog;
use App\Models\Diary;
use App\Models\Rubric;

use App\Actions\Admin\ModerateDiaryAction;
use App\Actions\Admin\ManageDiaryRubricAction;

use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Session;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    /** @var string Активная вкладка (diaries или rubrics) */
    #[Session] 
    public string $activeTab = 'diaries';
    
    /** @var string Поиск по дневникам */
    #[Session]
    public string $search = '';
    
    /** @var string Фильтр статуса дневников */
    #[Session]
    public string $statusFilter = 'pending';
    
    /** @var string Фильтр рубрики */
    #[Session]
    public string $rubricFilter = 'all';
    
    /** @var string Фильтр удаленных (without, with, only) */
    #[Session]
    public string $trashedFilter = 'without';
    
    /** @var string Поиск по рубрикам */
    #[Session]
    public string $rubricSearch = '';

    /** @var bool Видимость модалки создания/редактирования рубрики */
    public bool $showRubricModal = false;
    
    /** @var int|null ID редактируемой рубрики */
    public ?int $editingRubricId = null;
    
    public string $rubricName = '';
    public string $rubricSlug = '';
    public string $rubricDescription = '';
    public bool $rubricIsActive = true;
    public int $rubricSortOrder = 0;

    /** @var bool Видимость модалки удаления рубрики */
    public bool $showDeleteRubricModal = false;
    public ?int $deletingRubricId = null;
    public string $deletingRubricName = '';
    public int $deletingRubricCount = 0;
    
    /** @var string ID рубрики для переноса постов ('' = без рубрики) */
    public string $reassignRubricId = '';

    /** @var string URL для кнопки "Назад" (фикс потери истории при AJAX-запросах) */
    public string $backUrl = '';

    /**
     * Инициализация компонента.
     * Фиксим запоминание URL для кнопки "Назад".
     */
       public function mount(): void
    {
        $previousUrl = url()->previous();
        $this->backUrl = ($previousUrl && $previousUrl !== url()->current()) 
            ? $previousUrl 
            : route('admin.dashboard');

        // Умный поиск: если пришли по ссылке ?q=ID
        if (request()->has('q')) {
            $searchTerm = (string) request()->input('q');
            $this->activeTab = 'diaries';
            
            if (is_numeric($searchTerm)) {
                $diary = Diary::withTrashed()->find((int) $searchTerm);
                if ($diary) {
                    $this->search = $searchTerm;
                    $this->trashedFilter = $diary->trashed() ? 'only' : 'without';
                    $this->statusFilter = $diary->trashed() ? 'all' : $diary->status;
                    return;
                }
            }
            $this->search = $searchTerm;
            $this->statusFilter = 'all';
        }
    }
    /**
     * Переключение вкладок (Дневники / Рубрики).
     */
    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->search = '';
        $this->rubricSearch = '';
        $this->resetPage();
    }

    // ============================================
    // ДНЕВНИКИ
    // ============================================

    /**
     * Хук Livewire: сброс пагинации и кэша при поиске.
     */
       public function updatedSearch(): void 
    { 
        $this->resetPage(); 

        if (is_numeric($this->search) && !empty($this->search)) {
            $diary = Diary::withTrashed()->find((int) $this->search);
            if ($diary) {
                $newTrashed = $diary->trashed() ? 'only' : 'without';
                $newStatus = $diary->trashed() ? 'all' : $diary->status;
                
                if ($this->trashedFilter !== $newTrashed || $this->statusFilter !== $newStatus) {
                    $this->trashedFilter = $newTrashed;
                    $this->statusFilter = $newStatus;
                    unset($this->diaryCounts);
                }
            }
        }
        unset($this->diaries); 
    }
    /**
     * Хук Livewire: сброс пагинации и кэша при смене рубрики.
     */
    public function updatedRubricFilter(): void 
    { 
        $this->resetPage(); 
        unset($this->diaries); 
    }

    public function clearRubricSearch(): void
    {
        $this->rubricSearch = '';
        $this->resetPage();
        unset($this->rubrics);
    }

    /**
     * Установка фильтра статуса.
     */
    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->trashedFilter = 'without';
        $this->search = ''; // ФИКС: Очищаем поиск
        $this->resetPage();
        unset($this->diaries);
        unset($this->diaryCounts);
    }

    /**
     * Переключение фильтра карантина (удаленных).
     */
     public function toggleTrashedFilter(): void
    {
        $this->trashedFilter = $this->trashedFilter === 'only' ? 'without' : 'only';
        $this->statusFilter = 'all';
        $this->search = ''; // ФИКС: Очищаем поиск
        $this->resetPage();
        unset($this->diaries);
        unset($this->diaryCounts);
    }

    /**
     * Очистка строки поиска по дневникам.
     */
    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
        unset($this->diaries);
    }

    /**
     * Одобрить запись. Делегирует в Action.
     */
    public function approveDiary(int $id, ModerateDiaryAction $action): void
    {
        $diary = Diary::find($id);
        if (!$diary) return;
        $action->approve($diary, auth()->user());
        unset($this->diaries);
        unset($this->diaryCounts);
        $this->dispatch('show-toast', type: 'success', message: 'Запись одобрена');
    }

    /**
     * Отклонить запись с указанием причины.
     */
    public function rejectDiary(int $id, string $reason, ModerateDiaryAction $action): void
    {
        $diary = Diary::find($id);
        if (!$diary) return;
        $action->reject($diary, auth()->user(), $reason);
        unset($this->diaries);
        unset($this->diaryCounts);
        $this->dispatch('show-toast', type: 'warning', message: 'Запись отклонена');
    }

    /**
     * Мягкое удаление (в карантин).
     */
    public function deleteDiary(int $id, ModerateDiaryAction $action): void
    {
        $diary = Diary::find($id);
        if (!$diary) return;
        $action->delete($diary, auth()->user());
        unset($this->diaries);
        unset($this->diaryCounts);
        $this->dispatch('show-toast', type: 'success', message: 'Отправлено в карантин');
    }

    /**
     * Восстановление из карантина.
     */
    public function restoreDiary(int $id, ModerateDiaryAction $action): void
    {
        $diary = Diary::withTrashed()->find($id);
        if (!$diary) return;
        $action->restore($diary, auth()->user());
        unset($this->diaries);
        unset($this->diaryCounts);
        $this->dispatch('show-toast', type: 'success', message: 'Восстановлено из карантина');
    }

    /**
     * Полное удаление записи.
     */
    public function forceDeleteDiary(int $id, ModerateDiaryAction $action): void
    {
        $diary = Diary::withTrashed()->find($id);
        if (!$diary) return;
        $action->forceDelete($diary, auth()->user());
        unset($this->diaries);
        unset($this->diaryCounts);
        $this->dispatch('show-toast', type: 'success', message: 'Удалено навсегда');
    }

    /**
     * Получение списка дневников с фильтрацией и пагинацией.
     */
    #[Computed]
    public function diaries()
    {
        $avatarQuery = fn($q) => $q->select(['id', 'user_id', 'is_primary', 'status', 'path_thumb', 'path_medium', 'path_large', 'path_original'])->orderByDesc('is_primary')->limit(1);
            $query = Diary::query()->with([
            'user' => fn($q) => $q->withTrashed()->with(['photos' => $avatarQuery]), 
            'rubric'
        ]);

        if ($this->trashedFilter === 'with') $query->withTrashed();
        elseif ($this->trashedFilter === 'only') $query->onlyTrashed();

        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

        $query->when($this->search, function ($q) use ($operator) {
            $q->where(function ($q) use ($operator) {
                $q->where('title', $operator, "%{$this->search}%")
                  ->orWhereHas('user', fn($uq) => $uq->where('name', $operator, "%{$this->search}%"));
                if (is_numeric($this->search)) {
                    $q->orWhere('id', (int) $this->search);
                }
            });
        });

        $query->when($this->statusFilter !== 'all', fn($q) => $q->where('status', $this->statusFilter));
        $query->when($this->rubricFilter !== 'all', fn($q) => $q->where('rubric_id', $this->rubricFilter));

        return $query->latest('published_at')->paginate(15);
    }

    /**
     * Подсчет счетчиков для кнопок фильтров.
     */
    #[Computed]
    public function diaryCounts(): array
    {
        $stats = Diary::selectRaw("
            COUNT(*) as all_count,
            SUM(CASE WHEN status = 'published' AND deleted_at IS NULL THEN 1 ELSE 0 END) as published,
            SUM(CASE WHEN status = 'pending' AND deleted_at IS NULL THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'rejected' AND deleted_at IS NULL THEN 1 ELSE 0 END) as rejected
        ")->first();

        return [
            'all' => (int) ($stats->all_count ?? 0),
            'published' => (int) ($stats->published ?? 0),
            'pending' => (int) ($stats->pending ?? 0),
            'rejected' => (int) ($stats->rejected ?? 0),
            'trashed' => Diary::onlyTrashed()->count(),
        ];
    }

    // ============================================
    // РУБРИКИ
    // ============================================

    /**
     * Хук Livewire: сброс пагинации при поиске рубрик.
     */
    public function updatedRubricSearch(): void { $this->resetPage(); }

    /**
     * Открытие модалки создания рубрики.
     */
    public function createRubricModal(): void
    {
        $this->reset(['rubricName', 'rubricSlug', 'rubricDescription', 'rubricIsActive', 'rubricSortOrder', 'editingRubricId']);
        $this->rubricIsActive = true;
        $this->showRubricModal = true;
    }

    /**
     * Открытие модалки редактирования рубрики.
     */
    public function editRubricModal(int $id): void
    {
        $rubric = Rubric::find($id);
        if (!$rubric) return;

        $this->editingRubricId = $id;
        $this->rubricName = $rubric->name;
        $this->rubricSlug = $rubric->slug;
        $this->rubricDescription = $rubric->description ?? '';
        $this->rubricIsActive = $rubric->is_active;
        $this->rubricSortOrder = $rubric->sort_order;
        $this->showRubricModal = true;
    }

    /**
     * Авто-генерация слага при вводе названия (только при создании).
     */
    public function updatedRubricName(string $value): void
    {
        if (is_null($this->editingRubricId)) $this->rubricSlug = Str::slug($value);
    }

    /**
     * Сохранение новой рубрики.
     */
    public function saveRubric(ManageDiaryRubricAction $action): void
    {
        $this->validate([
            'rubricName' => 'required|string|max:255',
            'rubricSlug' => 'required|alpha_dash|unique:rubrics,slug',
            'rubricSortOrder' => 'integer',
        ]);

        $action->create([
            'name' => $this->rubricName, 
            'slug' => $this->rubricSlug, 
            'description' => $this->rubricDescription,
            'is_active' => $this->rubricIsActive, 
            'sort_order' => $this->rubricSortOrder, 
            'user_id' => null
        ], auth()->user());

        unset($this->rubrics);
        unset($this->allRubrics);
        $this->dispatch('show-toast', type: 'success', message: 'Рубрика создана');
        $this->showRubricModal = false;
    }

    /**
     * Обновление существующей рубрики.
     */
    public function updateRubric(ManageDiaryRubricAction $action): void
    {
        $this->validate([
            'rubricName' => 'required|string|max:255',
            'rubricSlug' => 'required|alpha_dash|unique:rubrics,slug,' . $this->editingRubricId,
            'rubricSortOrder' => 'integer',
        ]);

        $rubric = Rubric::find($this->editingRubricId);
        if (!$rubric) return;

        $action->update($rubric, [
            'name' => $this->rubricName, 
            'slug' => $this->rubricSlug, 
            'description' => $this->rubricDescription,
            'is_active' => $this->rubricIsActive, 
            'sort_order' => $this->rubricSortOrder
        ], auth()->user());

        unset($this->rubrics);
        unset($this->allRubrics);
        $this->dispatch('show-toast', type: 'success', message: 'Рубрика обновлена');
        $this->showRubricModal = false;
    }

    /**
     * Открытие модалки удаления рубрики с проверкой привязанных постов.
     */
    public function openDeleteRubricModal(int $id): void
    {
        $rubric = Rubric::withCount('diaries')->find($id);
        if (!$rubric) return;

        $this->deletingRubricId = $id;
        $this->deletingRubricName = $rubric->name;
        $this->deletingRubricCount = $rubric->diaries_count;
        $this->reassignRubricId = ''; 
        $this->showDeleteRubricModal = true;
    }

    /**
     * Подтверждение удаления рубрики (с переносом постов).
     */
    public function confirmDeleteRubric(ManageDiaryRubricAction $action): void
    {
        if (!$this->deletingRubricId) return;

        $rubric = Rubric::find($this->deletingRubricId);
        if (!$rubric) {
            $this->showDeleteRubricModal = false;
            return;
        }

        $reassignId = $this->reassignRubricId !== '' ? (int) $this->reassignRubricId : null;
        
        $action->delete($rubric, $reassignId, auth()->user());

        unset($this->rubrics);
        unset($this->allRubrics);
        unset($this->diaries); 

        $this->dispatch('show-toast', type: 'success', message: 'Рубрика удалена. Записи перенесены.');
        $this->showDeleteRubricModal = false;
    }

    /**
     * Быстрое переключение статуса рубрики (Активна/Скрыта).
     */
    public function toggleRubricStatus(int $id, ManageDiaryRubricAction $action): void
    {
        $rubric = Rubric::find($id);
        if (!$rubric) return;

        $action->toggleStatus($rubric, auth()->user());
        unset($this->rubrics);
    }

    /**
     * Получение списка рубрик с поиском.
     */
    #[Computed]
    public function rubrics()
    {
        $avatarQuery = fn($q) => $q->select(['id', 'user_id', 'is_primary', 'status', 'path_thumb', 'path_medium', 'path_large', 'path_original'])->orderByDesc('is_primary')->limit(1);
        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

               //  Грузим автора рубрики (и аватарки) даже если он мягко-удален
        return Rubric::with(['user' => fn($q) => $q->withTrashed()->with(['photos' => $avatarQuery])])
            ->withCount('diaries')
            ->when($this->rubricSearch, function ($q) use ($operator) {
                $q->where(function ($q) use ($operator) {
                    $q->where('name', $operator, "%{$this->rubricSearch}%")
                      ->orWhereHas('user', fn($uq) => $uq->where('name', $operator, "%{$this->rubricSearch}%"));
                    
                    if (is_numeric($this->rubricSearch)) {
                        $q->orWhere('id', (int) $this->rubricSearch);
                        $q->orWhere('user_id', (int) $this->rubricSearch);
                    }
                });
            })
            ->ordered()
            ->paginate(15);
    }
    
    /**
     * Получение только системных рубрик (для выпадающего списка).
     */
    #[Computed]
    public function allRubrics()
    {
        return Rubric::whereNull('user_id')->ordered()->get();
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
            <h1 class="text-2xl font-semibold flex items-center gap-2">
                <x-lucide-book-open class="w-6 h-6" />
                Дневники
            </h1>
        </div>
    </div>

    <!-- Вкладки -->
    <div class="flex border-b border-border">
        <button wire:click="setTab('diaries')" wire:key="tab-diaries" class="px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px {{ $activeTab === 'diaries' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
            Записи
        </button>
        <button wire:click="setTab('rubrics')" wire:key="tab-rubrics" class="px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px {{ $activeTab === 'rubrics' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
            Рубрики
        </button>
    </div>

    @if($activeTab === 'diaries')
        <div class="space-y-4">
            <!-- Фильтры -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap gap-1.5">
                    <x-ui.button wire:click="setStatusFilter('all')" variant="{{ $statusFilter === 'all' && $trashedFilter === 'without' ? 'default' : 'secondary' }}" size="sm" wire:key="btn-all">
                        Все <x-ui.badge size="xs">{{ $this->diaryCounts['all'] }}</x-ui.badge>
                    </x-ui.button>
                    <x-ui.button wire:click="setStatusFilter('pending')" variant="{{ $statusFilter === 'pending' ? 'default' : 'secondary' }}" size="sm" wire:key="btn-pending">
                        Модерация <x-ui.badge size="xs" variant="warning">{{ $this->diaryCounts['pending'] }}</x-ui.badge>
                    </x-ui.button>
                    <x-ui.button wire:click="setStatusFilter('published')" variant="{{ $statusFilter === 'published' ? 'default' : 'secondary' }}" size="sm" wire:key="btn-pub">
                        Опубликованные <x-ui.badge size="xs" variant="success">{{ $this->diaryCounts['published'] }}</x-ui.badge>
                    </x-ui.button>
                    <x-ui.button wire:click="setStatusFilter('rejected')" variant="{{ $statusFilter === 'rejected' ? 'default' : 'secondary' }}" size="sm" wire:key="btn-rej">
                        Отклоненные <x-ui.badge size="xs" variant="destructive">{{ $this->diaryCounts['rejected'] }}</x-ui.badge>
                    </x-ui.button>
                   <x-ui.button wire:click="toggleTrashedFilter()" variant="{{ $trashedFilter === 'only' ? 'destructive' : 'secondary' }}" size="sm" wire:key="btn-trash">
                        Карантин <x-ui.badge size="xs">{{ $this->diaryCounts['trashed'] }}</x-ui.badge>
                    </x-ui.button>
                </div>

                <div class="flex items-center gap-2">
                    <x-ui.select wire:model.live="rubricFilter">
                        <x-ui.select-trigger class="min-w-40">
                            <x-ui.select-value placeholder="Все рубрики" />
                        </x-ui.select-trigger>
                        <x-ui.select-content>
                            <x-ui.select-item value="all">Все рубрики</x-ui.select-item>
                            @foreach($this->allRubrics as $r)
                                <x-ui.select-item value="{{ $r->id }}" wire:key="opt-rub-{{ $r->id }}">{{ $r->name }}</x-ui.select-item>
                            @endforeach
                        </x-ui.select-content>
                    </x-ui.select>

                    <div class="relative w-64">
                        <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск по ID, названию..." class="pl-9 pr-8" />
                        <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground z-10" />
                        @if (!empty($search))
                            <button wire:click="clearSearch" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
                                <x-lucide-x class="w-4 h-4" />
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <x-ui.table>
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-ui.table-head class="w-16">ID</x-ui.table-head>
                        <x-ui.table-head>Автор</x-ui.table-head>
                        <x-ui.table-head>Заголовок</x-ui.table-head>
                        <x-ui.table-head>Рубрика</x-ui.table-head>
                        <x-ui.table-head>Статус</x-ui.table-head>
                        <x-ui.table-head>Статистика</x-ui.table-head>
                        <x-ui.table-head>Дата</x-ui.table-head>
                        <x-ui.table-head class="text-right">Действия</x-ui.table-row>
                    </x-ui.table-row>
                </x-ui.table-header>

                <x-ui.table-body>
                    @forelse ($this->diaries as $diary)
                       @php $isHighlighted = is_numeric($this->search) && $diary->id == (int)$this->search; @endphp
                        <x-ui.table-row 
                            wire:key="diary-{{ $diary->id }}" 
                            class="{{ $diary->trashed() ? 'opacity-50 bg-red-500/5' : '' }} {{ $isHighlighted ? 'bg-blue-500/10 ring-2 ring-blue-500/50' : '' }}"
                            x-data="{ isHi: {{ $isHighlighted ? 'true' : 'false' }} }"
                            x-init="isHi && setTimeout(() => { $el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 200)"
                        >
                         <x-ui.table-cell class="text-muted-foreground text-xs font-mono whitespace-nowrap {{ $isHighlighted ? 'text-blue-500 font-bold' : '' }}">
                            #{{ $diary->id }}
                        </x-ui.table-cell>
                            <x-ui.table-cell>
                                @if($diary->user)
                                    <a href="{{ route('admin.users.show', $diary->user->id) }}" wire:navigate class="group flex items-center gap-3 hover:text-primary transition-colors">
                                        <x-avatar src="{{ $diary->user->avatar_url }}" name="{{ $diary->user->name }}" size="sm" userId="{{ $diary->user->id }}" showStatus="true" :isOnline="$diary->user->is_online" />
                                        <div class="flex flex-col">
                                            <div class="flex items-center gap-1">
                                                <x-user-status-sign :user="$diary->user" />
                                                <span class="text-sm font-medium group-hover:text-primary">{{ $diary->user->name }}</span>
                                                @if($diary->user->has_active_premium) <x-lucide-crown class="w-3 h-3 text-yellow-500" /> @endif
                                            </div>
                                            <div class="text-xs text-muted-foreground">{{ $diary->user->email }}</div>
                                        </div>
                                    </a>
                                @else
                                    <span class="text-sm text-muted-foreground">Удален</span>
                                @endif
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                @if($diary->trashed())
                                    <span class="text-sm font-medium line-clamp-1 text-left text-muted-foreground">
                                        {{ $diary->title }}
                                    </span>
                                @else
                                    <a href="{{ route('admin.moderation.diary.moderate', $diary->id) }}" wire:navigate class="text-sm font-medium line-clamp-1 text-left hover:text-primary">
                                        {{ $diary->title }}
                                    </a>
                                @endif
                            </x-ui.table-cell>                    
                        <x-ui.table-cell>
                            @if($diary->rubric)
                                <div class="flex items-center gap-1">
                                    @if(!$diary->rubric->is_active)
                                        <x-lucide-alert-triangle class="w-3.5 h-3.5 text-yellow-500 shrink-0" title="Рубрика скрыта админом (не видна юзерам)" />
                                    @endif
                                    <button wire:click="editRubricModal({{ $diary->rubric->id }})" class="cursor-pointer hover:opacity-80 transition-opacity" title="Редактировать рубрику">
                                        <x-ui.badge variant="{{ $diary->rubric->is_active ? 'outline' : 'secondary' }}" size="sm">
                                            <span class="flex items-center gap-1">
                                                @if($diary->rubric->user_id) 
                                                    <x-lucide-user class="w-3 h-3 text-muted-foreground" />
                                                @else 
                                                    <x-lucide-globe class="w-3 h-3 text-blue-500" />
                                                @endif
                                                {{ $diary->rubric->name }}
                                            </span>
                                        </x-ui.badge>
                                    </button>
                                </div>
                            @else
                                <span class="text-xs text-muted-foreground italic">Без рубрики</span>
                            @endif
                        </x-ui.table-cell>
                           <x-ui.table-cell>
                                @if($diary->trashed())
                                    <x-ui.badge variant="destructive" size="sm">В карантине</x-ui.badge>
                                @elseif($diary->status === 'published')
                                    <x-ui.badge variant="success" size="sm">Опубл.</x-ui.badge>
                                @elseif($diary->status === 'pending')
                                    <x-ui.badge variant="warning" size="sm">Модерация</x-ui.badge>
                                @elseif($diary->status === 'rejected')
                                    <div class="flex flex-col gap-1">
                                        <x-ui.badge variant="destructive" size="sm">Отклонено</x-ui.badge>
                                        @php
                                            $rejectReasonEnum = \App\Enums\DiaryRejectReason::tryFrom($diary->reject_reason ?? 'other');
                                        @endphp
                                        @if($rejectReasonEnum)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium w-fit {{ $rejectReasonEnum->color() }}">
                                                {{ $rejectReasonEnum->label() }}
                                            </span>
                                        @endif
                                    </div>
                                @else
                                    <x-ui.badge variant="secondary" size="sm">Черновик</x-ui.badge>
                                @endif
                            </x-ui.table-cell>
                            <x-ui.table-cell class="text-xs text-muted-foreground">
                                <div class="flex items-center gap-1"><x-lucide-eye class="w-3 h-3" /> {{ $diary->views_count }}</div>
                                <div class="flex items-center gap-1"><x-lucide-message-circle class="w-3 h-3" /> {{ $diary->comments_count }}</div>
                            </x-ui.table-cell>
                            <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap">
                                {{ $diary->published_at?->format('d.m.y H:i') ?? $diary->created_at->format('d.m.y H:i') }}
                            </x-ui.table-cell>
                            <x-ui.table-cell class="text-right">
                                <x-ui.dropdown-menu>
                                    <x-ui.dropdown-menu-trigger>
                                        <x-ui.button variant="ghost" size="icon-sm"><x-lucide-more-horizontal class="w-4 h-4" /></x-ui.button>
                                    </x-ui.dropdown-menu-trigger>
                                 <x-ui.dropdown-menu-content align="end">
                                    @if(!$diary->trashed())
                                        <a href="{{ route('admin.moderation.diary.moderate', $diary->id) }}" wire:navigate class="flex items-center gap-2 cursor-pointer select-none rounded-sm px-2 py-1.5 text-sm outline-none transition-colors hover:bg-accent hover:text-accent-foreground">
                                            <x-lucide-eye class="w-4 h-4" /> Открыть
                                        </a>
                                        
                                        @if($diary->status === 'pending')
                                            <x-ui.dropdown-menu-item wire:click="approveDiary({{ $diary->id }})">
                                                <x-lucide-check class="w-4 h-4 text-success" /> Одобрить
                                            </x-ui.dropdown-menu-item>
                                            
                                            <x-ui.dropdown-menu-separator />
                                            <x-ui.dropdown-menu-label>Отклонить по причине:</x-ui.dropdown-menu-label>
                                            
                                            @foreach (\App\Enums\DiaryRejectReason::options() as $value => $label)
                                                <x-ui.dropdown-menu-item wire:click="rejectDiary({{ $diary->id }}, '{{ $value }}')" variant="destructive">
                                                    {{ $label }}
                                                </x-ui.dropdown-menu-item>
                                            @endforeach
                                            
                                            <x-ui.dropdown-menu-separator />
                                        @endif
                                        
                                        <x-ui.dropdown-menu-item variant="destructive" wire:click="deleteDiary({{ $diary->id }})">
                                            <x-lucide-trash-2 class="w-4 h-4" /> В карантин
                                        </x-ui.dropdown-menu-item>
                                    @else
                                        <x-ui.dropdown-menu-item wire:click="restoreDiary({{ $diary->id }})">
                                            <x-lucide-undo class="w-4 h-4" /> Восстановить из карантина
                                        </x-ui.dropdown-menu-item>
                                        <x-ui.dropdown-menu-item variant="destructive" wire:click="forceDeleteDiary({{ $diary->id }})" wire:confirm="Удалить навсегда? Это необратимо!">
                                            <x-lucide-x-circle class="w-4 h-4" /> Удалить навсегда
                                        </x-ui.dropdown-menu-item>
                                    @endif
                                </x-ui.dropdown-menu-content>
                                </x-ui.dropdown-menu>
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row wire:key="empty-state-diaries">
                            <x-ui.table-cell colspan="8" class="py-12 text-center text-muted-foreground">
                                <x-lucide-notebook-pen class="w-12 h-12 opacity-30 mx-auto mb-2" />
                                <p>Записи не найдены</p>
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>
            <div class="mt-4">{{ $this->diaries->links('partials.pagination') }}</div>
        </div>

    @elseif($activeTab === 'rubrics')
        <div class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <div class="relative w-64">
                    <x-ui.input wire:model.live.debounce.300ms="rubricSearch" type="search" placeholder="Поиск по названию, имени или ID..." class="pl-9 pr-8" />
                    <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground z-10" />
                    @if (!empty($rubricSearch))
                        <button wire:click="clearRubricSearch" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
                            <x-lucide-x class="w-4 h-4" />
                        </button>
                    @endif
                </div>
                <x-ui.button wire:click="createRubricModal" variant="default" size="sm">
                    <x-lucide-plus class="w-4 h-4" /> Создать системную рубрику
                </x-ui.button>
            </div>

            <x-ui.table>
                <x-ui.table-header>
                    <x-ui.table-row>
                         <x-ui.table-head class="w-16">ID</x-ui.table-head>
                        <x-ui.table-head class="w-16">Сорт.</x-ui.table-head>
                        <x-ui.table-head>Название</x-ui.table-head>
                        <x-ui.table-head>Автор</x-ui.table-head>
                        <x-ui.table-head>Slug</x-ui.table-head>
                        <x-ui.table-head>Постов</x-ui.table-head>
                        <x-ui.table-head>Статус</x-ui.table-head>
                        <x-ui.table-head class="text-right">Действия</x-ui.table-row>
                    </x-ui.table-row>
                </x-ui.table-header>
                <x-ui.table-body>
                    @forelse ($this->rubrics as $rubric)
                        @php $isRubricHighlighted = is_numeric($this->rubricSearch) && $rubric->id == (int)$this->rubricSearch; @endphp
                        <x-ui.table-row 
                            wire:key="rubric-{{ $rubric->id }}" 
                            class="{{ $isRubricHighlighted ? 'bg-blue-500/10 ring-2 ring-blue-500/50' : '' }}"
                            x-data="{ isHi: {{ $isRubricHighlighted ? 'true' : 'false' }} }"
                            x-init="isHi && setTimeout(() => { $el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 200)"
                        >
                            <x-ui.table-cell class="text-muted-foreground text-xs font-mono whitespace-nowrap {{ $isRubricHighlighted ? 'text-blue-500 font-bold' : '' }}">
                                #{{ $rubric->id }}
                            </x-ui.table-cell>
                            <x-ui.table-cell class="text-muted-foreground text-sm">{{ $rubric->sort_order }}</x-ui.table-cell>
                            <x-ui.table-cell class="font-medium text-sm">
                                <button wire:click="editRubricModal({{ $rubric->id }})" class="hover:text-primary cursor-pointer text-left" title="Редактировать рубрику">
                                    {{ $rubric->name }}
                                </button>
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                @if($rubric->user_id)
                                    @if($rubric->user)
                                        <a href="{{ route('admin.users.show', $rubric->user->id) }}" wire:navigate class="group flex items-center gap-3 hover:text-primary transition-colors">
                                            <x-avatar src="{{ $rubric->user->avatar_url }}" name="{{ $rubric->user->name }}" size="sm" userId="{{ $rubric->user->id }}" showStatus="true" :isOnline="$rubric->user->is_online" />
                                            <div class="flex flex-col">
                                                <div class="flex items-center gap-1">
                                                    <x-user-status-sign :user="$rubric->user" />
                                                    <span class="text-sm font-medium group-hover:text-primary">{{ $rubric->user->name }}</span>
                                                    @if($rubric->user->has_active_premium) <x-lucide-crown class="w-3 h-3 text-yellow-500" /> @endif
                                                </div>
                                                <div class="text-xs text-muted-foreground">{{ $rubric->user->email }}</div>
                                            </div>
                                        </a>
                                    @else
                                        <span class="text-sm text-muted-foreground">Удален</span>
                                    @endif
                                @else
                                    <x-ui.badge variant="secondary" size="sm"> <x-lucide-globe class="w-3 h-3 text-blue-500" /> Система</x-ui.badge>
                                @endif
                            </x-ui.table-cell>
                            <x-ui.table-cell><code class="text-xs px-1.5 py-0.5 bg-muted rounded">{{ $rubric->slug }}</code></x-ui.table-cell>
                            <x-ui.table-cell><x-ui.badge variant="outline" size="sm">{{ $rubric->diaries_count }}</x-ui.badge></x-ui.table-cell>
                            <x-ui.table-cell>
                                <button wire:click="toggleRubricStatus({{ $rubric->id }})" class="cursor-pointer">
                                    @if($rubric->is_active) <x-ui.badge variant="success" size="sm">Активна</x-ui.badge> @else <x-ui.badge variant="secondary" size="sm">Скрыта</x-ui.badge> @endif
                                </button>
                            </x-ui.table-cell>
                            <x-ui.table-cell class="text-right">
                                <x-ui.dropdown-menu>
                                    <x-ui.dropdown-menu-trigger>
                                        <x-ui.button variant="ghost" size="icon-sm"><x-lucide-more-horizontal class="w-4 h-4" /></x-ui.button>
                                    </x-ui.dropdown-menu-trigger>
                                    <x-ui.dropdown-menu-content align="end">
                                        <x-ui.dropdown-menu-item wire:click="editRubricModal({{ $rubric->id }})">
                                            <x-lucide-pencil class="w-4 h-4" /> Редактировать
                                        </x-ui.dropdown-menu-item>
                                        <x-ui.dropdown-menu-item variant="destructive" wire:click="openDeleteRubricModal({{ $rubric->id }})">
                                            <x-lucide-trash-2 class="w-4 h-4" /> Удалить
                                        </x-ui.dropdown-menu-item>
                                    </x-ui.dropdown-menu-content>
                                </x-ui.dropdown-menu>
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row wire:key="empty-state-rubrics">
                            <x-ui.table-cell colspan="8" class="py-12 text-center text-muted-foreground">
                                <p>Рубрики не найдены</p>
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>
            <div class="mt-4">{{ $this->rubrics->links('partials.pagination') }}</div>
        </div>
    @endif

    <!-- МОДАЛКА: РУБРИКИ -->
    <div x-data="{ show: @entangle('showRubricModal') }" x-show="show" x-cloak 
         x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="$wire.showRubricModal = false">
        
        <div class="bg-card border border-border rounded-lg shadow-2xl max-w-lg w-full mx-4 overflow-hidden"
             x-show="show" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
            
            <div class="flex items-center justify-between p-4 border-b border-border">
                <h2 class="text-lg font-semibold">{{ $editingRubricId ? 'Редактировать рубрику' : 'Создать рубрику' }}</h2>
                <button @click="$wire.showRubricModal = false" class="text-muted-foreground hover:text-foreground"><x-lucide-x class="w-5 h-5" /></button>
            </div>

            <div class="p-6 space-y-4">
                <div class="flex flex-col gap-2">
                    <x-ui.label for="r-name">Название</x-ui.label>
                    <x-ui.input id="r-name" wire:model.live="rubricName" placeholder="Например: Путешествия" />
                    @error('rubricName') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-col gap-2">
                    <x-ui.label for="r-slug">URL (Slug)</x-ui.label>
                    <x-ui.input id="r-slug" wire:model="rubricSlug" placeholder="auto-generated-slug" />
                    @error('rubricSlug') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-col gap-2">
                    <x-ui.label for="r-desc">Описание</x-ui.label>
                    <x-ui.textarea id="r-desc" wire:model="rubricDescription" rows="3" class="resize-none" placeholder="О чем эта рубрика..." />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <x-ui.label for="r-sort">Сортировка</x-ui.label>
                        <x-ui.input id="r-sort" wire:model="rubricSortOrder" type="number" />
                        @error('rubricSortOrder') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-end gap-3 pb-1">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="rubricIsActive" class="sr-only peer" />
                            <div class="w-11 h-6 bg-muted rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-primary after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:border-primary"></div>
                        </label>
                        <span class="text-sm font-medium">Активна</span>
                    </div>
                </div>
            </div>

          <div class="flex items-center justify-end gap-2 p-4 border-t border-border bg-muted/20">
            <x-ui.button @click="$wire.showRubricModal = false" variant="outline" size="sm">Отмена</x-ui.button>
            
            @if($editingRubricId)
                <x-ui.button wire:click="updateRubric" variant="default" size="sm">
                    <x-lucide-save class="w-4 h-4" /> Сохранить
                </x-ui.button>
            @else
                <x-ui.button wire:click="saveRubric" variant="default" size="sm">
                    <x-lucide-save class="w-4 h-4" /> Сохранить
                </x-ui.button>
            @endif
        </div>
        </div>
    </div>


        <!-- МОДАЛКА: УДАЛЕНИЕ РУБРИКИ С ПЕРЕНОСОМ -->
    <div x-data="{ show: @entangle('showDeleteRubricModal') }" x-show="show" x-cloak 
         x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="$wire.showDeleteRubricModal = false">
        
        <div class="bg-card border border-border rounded-lg shadow-2xl max-w-md w-full mx-4 overflow-hidden"
             x-show="show" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
            
            <div class="flex items-center justify-between p-4 border-b border-border">
                <h2 class="text-lg font-semibold flex items-center gap-2 text-destructive">
                    <x-lucide-trash-2 class="w-5 h-5" /> Удаление рубрики
                </h2>
                <button @click="$wire.showDeleteRubricModal = false" class="text-muted-foreground hover:text-foreground"><x-lucide-x class="w-5 h-5" /></button>
            </div>

            <div class="p-6 space-y-4">
                <p class="text-sm text-muted-foreground">
                    Вы собираетесь удалить рубрику <span class="font-bold text-foreground">"{{ $deletingRubricName }}"</span>.
                </p>
                
                @if($deletingRubricCount > 0)
                    <div class="p-3 bg-destructive/10 border border-destructive/30 rounded-md text-sm text-foreground">
                        В этой рубрике находится <span class="font-bold">{{ $deletingRubricCount }}</span> записей. Выберите, куда их перенести, иначе они останутся без рубрики.
                    </div>

                    <div class="flex flex-col gap-2">
                        <x-ui.label>Перенести записи в:</x-ui.label>
                        <x-ui.select wire:model="reassignRubricId">
                            <x-ui.select-trigger><x-ui.select-value placeholder="Без рубрики" /></x-ui.select-trigger>
                            <x-ui.select-content>
                                <x-ui.select-item value="">Оставить без рубрики</x-ui.select-item>
                               @foreach($this->allRubrics as $r)
                                    @if($r->id !== $deletingRubricId)
                                        <x-ui.select-item value="{{ $r->id }}" wire:key="opt-del-rub-{{ $r->id }}">
                                            <span class="flex items-center gap-2">
                                                @if($r->user_id) 
                                                    <x-lucide-user class="w-4 h-4 text-muted-foreground" />
                                                @else 
                                                    <x-lucide-globe class="w-4 h-4 text-blue-500" />
                                                @endif
                                                {{ $r->name }}
                                            </span>
                                        </x-ui.select-item>
                                    @endif
                                @endforeach
                            </x-ui.select-content>
                        </x-ui.select>
                    </div>
                @else
                    <div class="p-3 bg-muted rounded-md text-sm text-muted-foreground">
                        В этой рубрике нет записей. Можно удалять безопасно.
                    </div>
                @endif
            </div>

            <div class="flex items-center justify-end gap-2 p-4 border-t border-border bg-muted/20">
                <x-ui.button @click="$wire.showDeleteRubricModal = false" variant="outline" size="sm">Отмена</x-ui.button>
                <x-ui.button wire:click="confirmDeleteRubric" variant="destructive" size="sm" wire:target="confirmDeleteRubric" wire:loading.attr="disabled">
                    <x-lucide-trash-2 class="w-4 h-4" wire:loading.remove wire:target="confirmDeleteRubric" />
                    <x-lucide-loader-2 class="w-4 h-4 animate-spin hidden" wire:loading wire:target="confirmDeleteRubric" />
                    Удалить рубрику
                </x-ui.button>
            </div>
        </div>
    </div>
</div>