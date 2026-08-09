<?php

use App\Models\AdminLog;
use App\Models\Diary;
use App\Models\Rubric;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    public string $activeTab = 'diaries';
    
    // Фильтры дневников
    public string $search = '';
    public string $statusFilter = 'all';
    public string $rubricFilter = 'all';
    public string $trashedFilter = 'without'; // without, with, only
    
    // Фильтры рубрик
    public string $rubricSearch = '';

    // Модалка дневников (Быстрая модерация: статус, рубрика, комменты)
    public bool $showDiaryModal = false;
    public ?int $editingDiaryId = null;
    public string $diaryStatus = 'draft';
    public ?int $diaryRubricId = null;
    public bool $diaryCommentsEnabled = true;

    // Модалка рубрик
    public bool $showRubricModal = false;
    public ?int $editingRubricId = null;
    public string $rubricName = '';
    public string $rubricSlug = '';
    public string $rubricDescription = '';
    public bool $rubricIsActive = true;
    public int $rubricSortOrder = 0;

    public function mount(): void
    {
        $this->activeTab = session('admin_diaries_tab', 'diaries');
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        session(['admin_diaries_tab' => $tab]);
        $this->resetPage();
    }

    // ============================================
    // ДНЕВНИКИ (ЗАПИСИ)
    // ============================================

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedRubricFilter(): void { $this->resetPage(); }
    public function updatedTrashedFilter(): void { $this->resetPage(); }

    public function editDiaryModal(int $id): void
    {
        $diary = Diary::withTrashed()->find($id);
        if (!$diary) return;

        $this->editingDiaryId = $id;
        $this->diaryStatus = $diary->status;
        $this->diaryRubricId = $diary->rubric_id;
        $this->diaryCommentsEnabled = $diary->is_comments_enabled;
        
        $this->showDiaryModal = true;
    }

    public function updateDiary(): void
    {
        $diary = Diary::withTrashed()->find($this->editingDiaryId);
        if (!$diary) return;

        $diary->update([
            'status' => $this->diaryStatus,
            'rubric_id' => $this->diaryRubricId,
            'is_comments_enabled' => $this->diaryCommentsEnabled,
            'published_at' => $this->diaryStatus === 'published' && !$diary->published_at ? now() : $diary->published_at,
        ]);

        AdminLog::record('diary.update', $diary, auth()->user());
        $this->dispatch('show-toast', type: 'success', message: 'Запись обновлена!');
        $this->showDiaryModal = false;
    }

    public function toggleDiaryStatus(int $id): void
    {
        $diary = Diary::find($id);
        if (!$diary) return;

        if ($diary->status === 'published') {
            $diary->update(['status' => 'draft']);
        } else {
            $diary->publish(); // Используем хелпер из модели
        }

        AdminLog::record('diary.update', $diary, auth()->user());
        $this->dispatch('show-toast', type: 'success', message: 'Статус изменен');
    }

    public function deleteDiary(int $id): void
    {
        $diary = Diary::find($id);
        if ($diary) {
            $diary->delete();
            AdminLog::record('diary.delete', $diary, auth()->user());
            $this->dispatch('show-toast', type: 'success', message : 'Запись удалена (в корзину)');
        }
    }

    public function restoreDiary(int $id): void
    {
        $diary = Diary::withTrashed()->find($id);
        if ($diary) {
            $diary->restore();
            AdminLog::record('diary.restore', $diary, auth()->user());
            $this->dispatch('show-toast', type: 'success', message : 'Запись восстановлена');
        }
    }

    public function forceDeleteDiary(int $id): void
    {
        $diary = Diary::withTrashed()->find($id);
        if ($diary) {
            AdminLog::record('diary.force_delete', $diary, auth()->user());
            $diary->forceDelete();
            $this->dispatch('show-toast', type: 'success', message : 'Запись удалена навсегда');
        }
    }

    #[Computed]
    public function diaries()
    {
        $query = Diary::query()->with(['user', 'rubric']);

        if ($this->trashedFilter === 'with') {
            $query->withTrashed();
        } elseif ($this->trashedFilter === 'only') {
            $query->onlyTrashed();
        }

        $query->when($this->search, function ($q) {
            $q->where('title', 'like', "%{$this->search}%")
              ->orWhereHas('user', fn($q) => $q->where('name', 'like', "%{$this->search}%"));
        });

        $query->when($this->statusFilter !== 'all', fn($q) => $q->where('status', $this->statusFilter));
        $query->when($this->rubricFilter !== 'all', fn($q) => $q->where('rubric_id', $this->rubricFilter));

        return $query->latest('published_at')->paginate(15);
    }

    #[Computed]
    public function diaryCounts(): array
    {
        return [
            'all' => Diary::count(),
            'published' => Diary::where('status', 'published')->count(),
            'draft' => Diary::where('status', 'draft')->count(),
            'trashed' => Diary::onlyTrashed()->count(),
        ];
    }

    // ============================================
    // РУБРИКИ
    // ============================================

    public function updatedRubricSearch(): void { $this->resetPage(); }

    public function createRubricModal(): void
    {
        $this->reset(['rubricName', 'rubricSlug', 'rubricDescription', 'rubricIsActive', 'rubricSortOrder', 'editingRubricId']);
        $this->rubricIsActive = true;
        $this->showRubricModal = true;
    }

    public function editRubricModal(int $id): void
    {
        $rubric = Rubric::find($id);
        if (!$rubric) return;

        $this->editingRubricId = $id;
        $this->rubricName = $rubric->name;
        $this->rubricSlug = $rubric->slug;
        $this->rubricDescription = $rubric->description;
        $this->rubricIsActive = $rubric->is_active;
        $this->rubricSortOrder = $rubric->sort_order;

        $this->showRubricModal = true;
    }

    public function updatedRubricName(string $value): void
    {
        if (is_null($this->editingRubricId)) {
            $this->rubricSlug = Str::slug($value);
        }
    }

    public function saveRubric(): void
    {
        $this->validate([
            'rubricName' => 'required|string|max:255',
            'rubricSlug' => 'required|alpha_dash|unique:rubrics,slug',
            'rubricSortOrder' => 'integer',
        ]);

        $rubric = Rubric::create([
            'name' => $this->rubricName,
            'slug' => $this->rubricSlug,
            'description' => $this->rubricDescription,
            'is_active' => $this->rubricIsActive,
            'sort_order' => $this->rubricSortOrder,
        ]);

        AdminLog::record('rubric.create', $rubric, auth()->user());
        $this->dispatch('show-toast', type: 'success', message :'Рубрика создана');
        $this->showRubricModal = false;
    }

    public function updateRubric(): void
    {
        $this->validate([
            'rubricName' => 'required|string|max:255',
            'rubricSlug' => 'required|alpha_dash|unique:rubrics,slug,' . $this->editingRubricId,
            'rubricSortOrder' => 'integer',
        ]);

        $rubric = Rubric::find($this->editingRubricId);
        if (!$rubric) return;

        $rubric->update([
            'name' => $this->rubricName,
            'slug' => $this->rubricSlug,
            'description' => $this->rubricDescription,
            'is_active' => $this->rubricIsActive,
            'sort_order' => $this->rubricSortOrder,
        ]);

        AdminLog::record('rubric.update', $rubric, auth()->user());
        $this->dispatch('show-toast', type: 'success', message : 'Рубрика обновлена');
        $this->showRubricModal = false;
    }

    public function deleteRubric(int $id): void
    {
        $rubric = Rubric::find($id);
        if ($rubric) {
            AdminLog::record('rubric.delete', $rubric, auth()->user());
            $rubric->delete();
            $this->dispatch('show-toast', type: 'success', message : 'Рубрика удалена (посты станут без рубрики)');
        }
    }

    public function toggleRubricStatus(int $id): void
    {
        $rubric = Rubric::find($id);
        if ($rubric) {
            $rubric->update(['is_active' => !$rubric->is_active]);
            AdminLog::record('rubric.update', $rubric, auth()->user());
        }
    }

    #[Computed]
    public function rubrics()
    {
        return Rubric::withCount('diaries')
            ->when($this->rubricSearch, fn($q) => $q->where('name', 'like', "%{$this->rubricSearch}%"))
            ->ordered()
            ->paginate(15);
    }

    // Список рубрик для фильтра в дневниках
    #[Computed]
    public function allRubrics()
    {
        return Rubric::ordered()->get();
    }
}; 
?>

<div class="space-y-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <h1 class="text-2xl font-semibold flex items-center gap-2">
            <x-lucide-book-open class="w-6 h-6" />
            Дневники
        </h1>
    </div>

    <!-- Вкладки -->
    <div class="flex border-b border-border">
        <button wire:click="setTab('diaries')" class="px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px {{ $activeTab === 'diaries' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
            Записи
        </button>
        <button wire:click="setTab('rubrics')" class="px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px {{ $activeTab === 'rubrics' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
            Рубрики
        </button>
    </div>

    <!-- ============================================ -->
    <!-- ВКЛАДКА: ЗАПИСИ ДНЕВНИКОВ                    -->
    <!-- ============================================ -->
    @if($activeTab === 'diaries')
        <div class="space-y-4">
            <!-- Фильтры -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap gap-1.5">
                    <x-ui.button wire:click="$set('statusFilter', 'all')" variant="{{ $statusFilter === 'all' ? 'default' : 'secondary' }}" size="sm">
                        Все <x-ui.badge size="xs">{{ $this->diaryCounts['all'] }}</x-ui.badge>
                    </x-ui.button>
                    <x-ui.button wire:click="$set('statusFilter', 'published')" variant="{{ $statusFilter === 'published' ? 'default' : 'secondary' }}" size="sm">
                        Опубликованные <x-ui.badge size="xs" variant="success">{{ $this->diaryCounts['published'] }}</x-ui.badge>
                    </x-ui.button>
                    <x-ui.button wire:click="$set('statusFilter', 'draft')" variant="{{ $statusFilter === 'draft' ? 'default' : 'secondary' }}" size="sm">
                        Черновики <x-ui.badge size="xs" variant="warning">{{ $this->diaryCounts['draft'] }}</x-ui.badge>
                    </x-ui.button>
                    <x-ui.button wire:click="$set('trashedFilter', $trashedFilter === 'only' ? 'without' : 'only')" variant="{{ $trashedFilter === 'only' ? 'destructive' : 'secondary' }}" size="sm">
                        Корзина <x-ui.badge size="xs">{{ $this->diaryCounts['trashed'] }}</x-ui.badge>
                    </x-ui.button>
                </div>

                <div class="flex items-center gap-2">
                    <x-ui.select wire:model.live="rubricFilter" class="min-w-40">
                        <x-ui.select-trigger><x-ui.select-value placeholder="Все рубрики" /></x-ui.select-trigger>
                        <x-ui.select-content>
                            <x-ui.select-item value="all">Все рубрики</x-ui.select-item>
                            @foreach($this->allRubrics as $r)
                                <x-ui.select-item value="{{ $r->id }}">{{ $r->name }}</x-ui.select-item>
                            @endforeach
                        </x-ui.select-content>
                    </x-ui.select>

                    <div class="relative w-64">
                        <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск по названию/автору..." class="pl-9 pr-8" />
                        <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                    </div>
                </div>
            </div>

            <!-- Таблица записей -->
            <x-ui.table>
                <x-ui.table-header>
                    <x-ui.table-row>
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
                        <x-ui.table-row wire:key="diary-{{ $diary->id }}" class="{{ $diary->trashed() ? 'opacity-50 bg-red-500/5' : '' }}">
                            <x-ui.table-cell>
                                <div class="text-sm font-medium">{{ $diary->user?->name ?? 'Удален' }}</div>
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                <div class="text-sm font-medium line-clamp-1">{{ $diary->title }}</div>
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                @if($diary->rubric)
                                    <x-ui.badge variant="outline" size="sm">{{ $diary->rubric->name }}</x-ui.badge>
                                @else
                                    <span class="text-xs text-muted-foreground">—</span>
                                @endif
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                @if($diary->trashed())
                                    <x-ui.badge variant="destructive" size="sm">Удалено</x-ui.badge>
                                @else
                                    <button wire:click="toggleDiaryStatus({{ $diary->id }})" class="cursor-pointer">
                                        @if($diary->status === 'published')
                                            <x-ui.badge variant="success" size="sm">Опубликовано</x-ui.badge>
                                        @else
                                            <x-ui.badge variant="warning" size="sm">Черновик</x-ui.badge>
                                        @endif
                                    </button>
                                @endif
                            </x-ui.table-cell>
                            <x-ui.table-cell class="text-xs text-muted-foreground">
                                <div class="flex items-center gap-1"><x-lucide-eye class="w-3 h-3" /> {{ $diary->views_count }}</div>
                                <div class="flex items-center gap-1"><x-lucide-message-circle class="w-3 h-3" /> {{ $diary->comments_count }}</div>
                            </x-ui.table-cell>
                            <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap">
                                {{ $diary->published_at?->format('d.m.Y H:i') ?? $diary->created_at->format('d.m.Y H:i') }}
                            </x-ui.table-cell>
                            <x-ui.table-cell class="text-right">
                                <x-ui.dropdown-menu>
                                    <x-ui.dropdown-menu-trigger>
                                        <x-ui.button variant="ghost" size="icon-sm"><x-lucide-more-horizontal class="w-4 h-4" /></x-ui.button>
                                    </x-ui.dropdown-menu-trigger>
                                    <x-ui.dropdown-menu-content align="end">
                                        @if(!$diary->trashed())
                                            <x-ui.dropdown-menu-item wire:click="editDiaryModal({{ $diary->id }})">
                                                <x-lucide-settings class="w-4 h-4" /> Настроить
                                            </x-ui.dropdown-menu-item>
                                            <x-ui.dropdown-menu-item variant="destructive" wire:click="deleteDiary({{ $diary->id }})">
                                                <x-lucide-trash-2 class="w-4 h-4" /> В корзину
                                            </x-ui.dropdown-menu-item>
                                        @else
                                            <x-ui.dropdown-menu-item wire:click="restoreDiary({{ $diary->id }})">
                                                <x-lucide-undo class="w-4 h-4" /> Восстановить
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
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="7" class="py-12 text-center text-muted-foreground">
                                <x-lucide-notebook-pen class="w-12 h-12 opacity-30 mx-auto mb-2" />
                                <p>Записи не найдены</p>
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>

            <div class="mt-4">
                {{ $this->diaries->links('partials.pagination') }}
            </div>
        </div>

    <!-- ============================================ -->
    <!-- ВКЛАДКА: РУБРИКИ                             -->
    <!-- ============================================ -->
    @elseif($activeTab === 'rubrics')
        <div class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <div class="relative w-64">
                    <x-ui.input wire:model.live.debounce.300ms="rubricSearch" type="search" placeholder="Поиск рубрики..." class="pl-9 pr-8" />
                    <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                </div>

                <x-ui.button wire:click="createRubricModal" variant="default" size="sm">
                    <x-lucide-plus class="w-4 h-4" /> Создать рубрику
                </x-ui.button>
            </div>

            <x-ui.table>
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-ui.table-head class="w-16">Сорт.</x-ui.table-head>
                        <x-ui.table-head>Название</x-ui.table-head>
                        <x-ui.table-head>Slug</x-ui.table-head>
                        <x-ui.table-head>Постов</x-ui.table-head>
                        <x-ui.table-head>Статус</x-ui.table-head>
                        <x-ui.table-head class="text-right">Действия</x-ui.table-row>
                    </x-ui.table-row>
                </x-ui.table-header>

                <x-ui.table-body>
                    @forelse ($this->rubrics as $rubric)
                        <x-ui.table-row wire:key="rubric-{{ $rubric->id }}">
                            <x-ui.table-cell class="text-muted-foreground text-sm">{{ $rubric->sort_order }}</x-ui.table-cell>
                            <x-ui.table-cell class="font-medium text-sm">{{ $rubric->name }}</x-ui.table-cell>
                            <x-ui.table-cell><code class="text-xs px-1.5 py-0.5 bg-muted rounded">{{ $rubric->slug }}</code></x-ui.table-cell>
                            <x-ui.table-cell><x-ui.badge variant="outline" size="sm">{{ $rubric->diaries_count }}</x-ui.badge></x-ui.table-cell>
                            <x-ui.table-cell>
                                <button wire:click="toggleRubricStatus({{ $rubric->id }})" class="cursor-pointer">
                                    @if($rubric->is_active)
                                        <x-ui.badge variant="success" size="sm">Активна</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="secondary" size="sm">Скрыта</x-ui.badge>
                                    @endif
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
                                        <x-ui.dropdown-menu-item variant="destructive" wire:click="deleteRubric({{ $rubric->id }})" wire:confirm="Удалить рубрику? Посты не удалятся, но останутся без рубрики.">
                                            <x-lucide-trash-2 class="w-4 h-4" /> Удалить
                                        </x-ui.dropdown-menu-item>
                                    </x-ui.dropdown-menu-content>
                                </x-ui.dropdown-menu>
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="6" class="py-12 text-center text-muted-foreground">
                                <p>Рубрики не найдены</p>
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>

            <div class="mt-4">
                {{ $this->rubrics->links('partials.pagination') }}
            </div>
        </div>
    @endif

    <!-- ============================================ -->
    <!-- МОДАЛКА: НАСТРОЙКИ ЗАПИСИ ДНЕВНИКА           -->
    <!-- ============================================ -->
    <div x-show="$wire.showDiaryModal" x-cloak @click.self="$wire.showDiaryModal = false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" x-transition @keydown.escape.window="$wire.showDiaryModal = false">
        <div class="relative bg-card border border-border rounded-lg shadow-2xl max-w-lg w-full mx-4 overflow-hidden">
            <div class="flex items-center justify-between p-4 border-b border-border">
                <h2 class="text-lg font-semibold">Настройки записи</h2>
                <button @click="$wire.showDiaryModal = false" class="text-muted-foreground hover:text-foreground"><x-lucide-x class="w-5 h-5" /></button>
            </div>

            <div class="p-6 space-y-4">
                <div class="flex flex-col gap-2">
                    <x-ui.label>Статус</x-ui.label>
                    <x-ui.select wire:model="diaryStatus">
                        <x-ui.select-trigger><x-ui.select-value /></x-ui.select-trigger>
                        <x-ui.select-content>
                            <x-ui.select-item value="draft">Черновик</x-ui.select-item>
                            <x-ui.select-item value="published">Опубликовано</x-ui.select-item>
                        </x-ui.select-content>
                    </x-ui.select>
                </div>

                <div class="flex flex-col gap-2">
                    <x-ui.label>Рубрика</x-ui.label>
                    <x-ui.select wire:model="diaryRubricId">
                        <x-ui.select-trigger><x-ui.select-value placeholder="Без рубрики" /></x-ui.select-trigger>
                        <x-ui.select-content>
                            <x-ui.select-item value="">Без рубрики</x-ui.select-item>
                            @foreach($this->allRubrics as $r)
                                <x-ui.select-item value="{{ $r->id }}">{{ $r->name }}</x-ui.select-item>
                            @endforeach
                        </x-ui.select-content>
                    </x-ui.select>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="diaryCommentsEnabled" class="sr-only peer" />
                        <div class="w-11 h-6 bg-muted rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-primary after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:border-primary"></div>
                    </label>
                    <span class="text-sm font-medium">Разрешить комментарии</span>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 p-4 border-t border-border bg-muted/20">
                <x-ui.button @click="$wire.showDiaryModal = false" variant="outline" size="sm">Отмена</x-ui.button>
                <x-ui.button wire:click="updateDiary" variant="default" size="sm">
                    <x-lucide-save class="w-4 h-4 inline" /> Сохранить
                </x-ui.button>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- МОДАЛКА: СОЗДАНИЕ/РЕДАКТИРОВАНИЕ РУБРИКИ     -->
    <!-- ============================================ -->
    <div x-show="$wire.showRubricModal" x-cloak @click.self="$wire.showRubricModal = false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" x-transition @keydown.escape.window="$wire.showRubricModal = false">
        <div class="relative bg-card border border-border rounded-lg shadow-2xl max-w-lg w-full mx-4 overflow-hidden">
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
                <x-ui.button wire:click="{{ $editingRubricId ? 'updateRubric' : 'saveRubric' }}" variant="default" size="sm">
                    <x-lucide-save class="w-4 h-4 inline" /> Сохранить
                </x-ui.button>
            </div>
        </div>
    </div>
</div>