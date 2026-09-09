<?php

use App\Actions\Admin\ManageSupportTemplatesAction;
use App\Models\SupportTemplate;
use App\Models\SupportTemplateCategory;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Session;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Illuminate\Support\Str;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    #[Session] 
    public string $activeTab = 'templates';

    public ?int $templateId = null;
    public ?int $category_id = null;
    public string $title = '';
    public string $body = '';
    public bool $is_active = true;
    public int $sort_order = 0;
    public bool $showTemplateModal = false;
    
    #[Session]
    public string $search = '';
    #[Session]
    public string $categoryFilter = 'all';

    public ?int $catId = null;
    public string $catName = '';
    public string $catSlug = '';
    public bool $catIsActive = true;
    public int $catSortOrder = 0;
    public bool $showCategoryModal = false;
    
    #[Session]
    public string $catSearch = ''; 

    public string $backUrl = '';

        public function mount(): void
    {
        abort_unless(in_array(auth()->user()?->role, [User::ROLE_ADMIN, User::ROLE_MODERATOR]), 403);

        $previousUrl = url()->previous();
        $this->backUrl = ($previousUrl && $previousUrl !== url()->current()) 
            ? $previousUrl 
            : route('admin.dashboard');

        // ФИКС 1: Если переход из Журнала логов по конкретному ШАБЛОНУ
        if (request()->has('tpl_id') && ctype_digit((string)request()->input('tpl_id'))) {
            $templateId = (int) request()->input('tpl_id');
            $template = SupportTemplate::find($templateId);
            if ($template) {
                $this->activeTab = 'templates';
                $this->search = (string) $templateId;
                $this->categoryFilter = $template->category_id ? (string) $template->category_id : 'all';
                return;
            }
        }
        
        // ФИКС 2: Если переход из Журнала логов по конкретной КАТЕГОРИИ
        if (request()->has('cat_id') && ctype_digit((string)request()->input('cat_id'))) {
            $categoryId = (int) request()->input('cat_id');
            $category = SupportTemplateCategory::find($categoryId);
            if ($category) {
                $this->activeTab = 'categories';
                $this->catSearch = (string) $categoryId;
                return;
            }
        }

        // Обратная совместимость со старым ?q= (если кто-то руками ввел)
        if (request()->has('q')) {
            $searchTerm = (string) request()->input('q');
            
            if (ctype_digit($searchTerm)) {
                $template = SupportTemplate::find((int) $searchTerm);
                if ($template) {
                    $this->activeTab = 'templates';
                    $this->search = $searchTerm;
                    $this->categoryFilter = $template->category_id ? (string) $template->category_id : 'all';
                    return;
                }

                $category = SupportTemplateCategory::find((int) $searchTerm);
                if ($category) {
                    $this->activeTab = 'categories';
                    $this->catSearch = $searchTerm;
                    return;
                }
            }
            
            $this->activeTab = 'templates';
            $this->search = $searchTerm;
            $this->categoryFilter = 'all';
        }
    
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->search = '';
        $this->catSearch = '';
        $this->categoryFilter = 'all';
        $this->resetPage();
        $this->clearComputedCache();
    }

    // ============================================
    // ЛОГИКА ШАБЛОНОВ
    // ============================================

    public function updatedSearch(): void 
    { 
        $this->resetPage(); 
        $this->clearComputedCache();

        $search = trim($this->search);
        // ФИКС: ctype_digit
        if (ctype_digit($search) && !empty($search)) {
            $template = SupportTemplate::find((int) $search);
            if ($template) {
                $this->categoryFilter = $template->category_id ? (string) $template->category_id : 'all';
            }
        }
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
        $this->clearComputedCache();
    }

    public function setCategoryFilter(string $categoryId): void 
    { 
        $this->categoryFilter = $categoryId; 
        $this->search = ''; 
        $this->resetPage(); 
        $this->clearComputedCache();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->categoryFilter = 'all';
        $this->resetPage();
        $this->clearComputedCache();
    }

    // ============================================
    // ЛОГИКА КАТЕГОРИЙ
    // ============================================

    public function updatedCatSearch(): void 
    { 
        $this->resetPage(); 
        $this->clearComputedCache();
    }

    public function clearCatSearch(): void
    {
        $this->catSearch = '';
        $this->resetPage();
        $this->clearComputedCache();
    }

    // ============================================
    // ВЫВОД ДАННЫХ (Computed)
    // ============================================

    #[Computed]
    public function categories()
    {
        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
        $search = trim($this->catSearch);

        return SupportTemplateCategory::query()
            ->when($search, function ($q) use ($search, $operator) {
                // ФИКС: ctype_digit
                if (ctype_digit($search)) {
                    $q->where('id', (int) $search);
                } else {
                    $q->where('name', $operator, "%{$search}%")
                      ->orWhere('slug', $operator, "%{$search}%");
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function counts(): array
    {
        // ФИКС: Кэшируем счетчики на 1 минуту
        return Cache::remember('admin_support_template_counts', 60, function () {
            $stats = SupportTemplate::query()
                ->selectRaw("COUNT(*) as total")
                ->selectRaw("SUM(CASE WHEN is_active = true THEN 1 ELSE 0 END) as active")
                ->first();

            $categoryCounts = SupportTemplate::query()
                ->selectRaw("category_id, COUNT(*) as count")
                ->whereNotNull('category_id')
                ->groupBy('category_id')
                ->pluck('count', 'category_id');

            return [
                'total' => (int)($stats?->total ?? 0),
                'active' => (int)($stats?->active ?? 0),
                'categories' => $categoryCounts->toArray(),
            ];
        });
    }

    #[Computed]
    public function templates()
    {
        $searchOperator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
        $search = trim($this->search);

        return SupportTemplate::query()
            ->when($this->categoryFilter !== 'all', fn($q) => $q->where('category_id', (int)$this->categoryFilter))
            ->when($search, function ($q) use ($search, $searchOperator) {
                // ФИКС: ctype_digit
                if (ctype_digit($search)) {
                    $q->where('id', (int) $search);
                } else {
                    $q->where('title', $searchOperator, "%{$search}%")
                      ->orWhere('body', $searchOperator, "%{$search}%");
                }
            })
            ->with('category')
            ->orderBy('category_id')
            ->orderBy('sort_order')
            ->paginate(15);
    }

    // ============================================
    // ДЕЙСТВИЯ ШАБЛОНОВ
    // ============================================

    public function openCreateTemplateModal(): void
    {
        $this->resetValidation();
        $this->templateId = null;
        $this->category_id = SupportTemplateCategory::orderBy('sort_order')->first()?->id;
        $this->title = '';
        $this->body = '';
        $this->is_active = true;
        $this->sort_order = 0;
        $this->showTemplateModal = true;
    }

    public function openEditTemplateModal(int $id): void
    {
        $template = SupportTemplate::find($id);
        if (!$template) return;

        $this->resetValidation();
        $this->templateId = $template->id;
        $this->category_id = $template->category_id;
        $this->title = $template->title;
        $this->body = $template->body;
        $this->is_active = $template->is_active;
        $this->sort_order = $template->sort_order;
        $this->showTemplateModal = true;
    }

    public function saveTemplate(ManageSupportTemplatesAction $action): void
    {
        $this->validate([
            'category_id' => 'required|exists:support_template_categories,id',
            'title' => 'required|string|max:150',
            'body' => 'required|string|max:2000',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $data = [
            'category_id' => $this->category_id,
            'title' => $this->title,
            'body' => $this->body,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];

        if ($this->templateId) {
            $template = SupportTemplate::find($this->templateId);
            $action->update($template, $data, auth()->user());
            $this->dispatch('show-toast', type: 'success', message: 'Шаблон обновлен!');
        } else {
            $action->create($data, auth()->user());
            $this->dispatch('show-toast', type: 'success', message: 'Шаблон создан!');
        }

        $this->showTemplateModal = false;
        $this->clearComputedCache();
    }

    public function deleteTemplate(int $id, ManageSupportTemplatesAction $action): void
    {
        $template = SupportTemplate::find($id);
        if ($template) {
            $action->delete($template, auth()->user());
            $this->dispatch('show-toast', type: 'warning', message: 'Шаблон удален.');
            $this->clearComputedCache();
        }
    }

    // ============================================
    // ДЕЙСТВИЯ КАТЕГОРИЙ
    // ============================================

    public function openCreateCategoryModal(): void
    {
        $this->resetValidation();
        $this->catId = null;
        $this->catName = '';
        $this->catSlug = '';
        $this->catIsActive = true;
        $this->catSortOrder = 0;
        $this->showCategoryModal = true;
    }

    public function openEditCategoryModal(int $id): void
    {
        $cat = SupportTemplateCategory::find($id);
        if (!$cat) return;

        $this->resetValidation();
        $this->catId = $cat->id;
        $this->catName = $cat->name;
        $this->catSlug = $cat->slug;
        $this->catIsActive = $cat->is_active;
        $this->catSortOrder = $cat->sort_order;
        $this->showCategoryModal = true;
    }

    public function saveCategory(ManageSupportTemplatesAction $action): void
    {
        // ЖЕСТКАЯ ВАЛИДАЦИЯ: Только строчные латинские буквы, цифры и дефис
        $this->validate([
            'catName' => 'required|string|max:100',
            'catSlug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/'],
            'catSortOrder' => 'integer|min:0',
            'catIsActive' => 'boolean'
        ], [
            'catSlug.regex' => 'Slug может содержать только строчные латинские буквы, цифры и дефисы.'
        ]);

        if (mb_strtolower(trim($this->catName)) === 'общие') {
            $this->catSlug = 'general';
        }

        // Формируем слаг. Если поле было пустым, генерируем из названия.
        // Если после Str::slug получилась пустота (смайлики и т.д.), ставим дефолтный slug
        $finalSlug = $this->catSlug ? Str::slug($this->catSlug) : Str::slug($this->catName);
        if (empty($finalSlug)) {
            $finalSlug = 'category-' . time();
        }

        $data = [
            'name' => trim($this->catName),
            'slug' => $finalSlug,
            'is_active' => $this->catIsActive,
            'sort_order' => $this->catSortOrder
        ];

        if ($this->catId) {
            $existingCat = SupportTemplateCategory::find($this->catId);
            // Защита системной категории
            if ($existingCat && in_array($existingCat->slug, ['general', 'obshhhie']) && $data['slug'] !== 'general') {
                $this->dispatch('show-toast', type: 'error', message: 'Нельзя менять Slug у системной категории!');
                return;
            }

            $cat = SupportTemplateCategory::find($this->catId);
            $action->updateCategory($cat, $data, auth()->user());
            $this->dispatch('show-toast', type: 'success', message: 'Категория обновлена!');
        } else {
            $action->createCategory($data, auth()->user());
            $this->dispatch('show-toast', type: 'success', message: 'Категория создана!');
        }

        $this->showCategoryModal = false;
        $this->clearComputedCache();
    }

    public function deleteCategory(int $categoryId, ManageSupportTemplatesAction $action): void
    {
        $generalCategory = SupportTemplateCategory::whereIn('slug', ['general', 'obshhhie'])->first();
        
        if ($generalCategory && $categoryId === $generalCategory->id) {
            $this->dispatch('show-toast', type: 'error', message: 'Нельзя удалить системную категорию "Общие"!');
            return;
        }

        if ($generalCategory) {
            SupportTemplate::where('category_id', $categoryId)->update(['category_id' => $generalCategory->id]);
        }

        $category = SupportTemplateCategory::find($categoryId);
        if ($category) {
            $action->deleteCategory($category, auth()->user());
        }
        
        $this->clearComputedCache();
        $this->dispatch('show-toast', type: 'warning', message: "Категория удалена. Шаблоны перемещены в 'Общие'.");
    }

    // ============================================
    // ОБЩЕЕ
    // ============================================

    public function clearComputedCache(): void
    {
        unset($this->templates);
        unset($this->categories);
        unset($this->counts);
    }
}; 
?>

<div class="space-y-6 pb-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ $backUrl }}" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-semibold flex items-center gap-2">
                    <x-lucide-file-text class="w-6 h-6" />
                    Шаблоны поддержки
                </h1>
                <p class="text-sm text-muted-foreground mt-1">Управление шаблонами и категориями для вставки в чат поддержки</p>
            </div>
        </div>


    </div>

    <!-- ТАБЫ -->
    <div class="flex gap-x-4 flex-wrap border-b border-border">
        <button wire:key="tab-templates" wire:click="setTab('templates')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'templates' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
            <x-lucide-file-text class="w-4 h-4 inline mr-1" /> Шаблоны
        </button>
        <button wire:key="tab-categories" wire:click="setTab('categories')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'categories' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
            <x-lucide-folder class="w-4 h-4 inline mr-1" /> Категории
        </button>
    </div>

    <!-- ============================================ -->
    <!-- ВКЛАДКА: ШАБЛОНЫ                              -->
    <!-- ============================================ -->
    @if($activeTab === 'templates')
        <div class="flex items-center justify-between flex-wrap gap-4 mb-4">
            <div class="flex items-center gap-2">
                @if($search || $categoryFilter !== 'all')
                    <x-ui.button wire:click="clearFilters" variant="ghost" size="sm" class="text-muted-foreground">
                        <x-lucide-x class="w-4 h-4" /> Сбросить
                    </x-ui.button>
                @endif

                <x-ui.button wire:click="openCreateTemplateModal" variant="default" size="sm">
                    <x-lucide-plus class="w-4 h-4" /> Создать шаблон
                </x-ui.button>
            </div>

            <div class="relative w-64">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск по ID или тексту..." class="pl-9 pr-8" />
                <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                @if (!empty($search))
                    <button wire:click="clearSearch" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground z-10">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                @endif
            </div>           
        </div>

        <!-- ФИЛЬТРЫ КАТЕГОРИЙ -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="flex flex-wrap gap-1.5 items-center">
                <x-ui.button wire:key="filter-cat-all" wire:click="setCategoryFilter('all')" variant="{{ $categoryFilter === 'all' ? 'default' : 'secondary' }}" size="sm">
                    Все <x-ui.badge size="xs">{{ $this->counts['total'] }}</x-ui.badge>
                </x-ui.button>

                @foreach($this->categories as $cat)
                    <x-ui.button wire:key="filter-cat-{{ $cat->id }}" wire:click="setCategoryFilter('{{ $cat->id }}')" variant="{{ $categoryFilter === (string)$cat->id ? 'default' : 'secondary' }}" size="sm">
                        {{ $cat->name }} <x-ui.badge size="xs" variant="secondary">{{ $this->counts['categories'][$cat->id] ?? 0 }}</x-ui.badge>
                    </x-ui.button>
                @endforeach
            </div>
        </div>

        <!-- ТАБЛИЦА ШАБЛОНОВ -->
        <x-ui.table>
            <x-ui.table-header>
                <x-ui.table-row wire:key="tbl-head-templates">
                    <x-ui.table-head class="w-10">ID</x-ui.table-head>
                    <x-ui.table-head>Название</x-ui.table-head>
                    <x-ui.table-head class="w-40">Категория</x-ui.table-head>
                    <x-ui.table-head class="hidden md:table-cell">Текст (превью)</x-ui.table-head>
                    <x-ui.table-head class="w-24 text-center">Статус</x-ui.table-head>
                    <x-ui.table-head class="w-32 text-right">Действия</x-ui.table-head>
                </x-ui.table-row>
            </x-ui.table-header>

            <x-ui.table-body>
                @forelse ($this->templates as $template)
                    @php 
                        $isHighlighted = is_numeric($this->search) && $template->id === (int)$this->search;
                    @endphp
                    <x-ui.table-row 
                        wire:key="tpl-{{ $template->id }}" 
                        class="{{ $isHighlighted ? 'bg-primary/10 ring-2 ring-primary/50 transition-all duration-500' : '' }}"
                        x-data="{ isHi: {{ $isHighlighted ? 'true' : 'false' }} }"
                        x-init="isHi && $nextTick(() => { $el.scrollIntoView({ behavior: 'smooth', block: 'center' }) })"
                    >
                        <x-ui.table-cell class="text-xs font-mono whitespace-nowrap {{ $isHighlighted ? 'text-primary font-bold' : 'text-muted-foreground' }}">
                            #{{ $template->id }}
                        </x-ui.table-cell>
                        
                        <x-ui.table-cell class="font-medium text-sm">
                            <button wire:click="openEditTemplateModal({{ $template->id }})" class="text-left hover:text-primary transition-colors">
                                {{ $template->title }}
                            </button>
                        </x-ui.table-cell>

                        <x-ui.table-cell class="font-medium text-sm">
                            @if($template->category)
                                <div class="flex flex-col gap-1">
                                    <x-ui.badge variant="secondary" size="xs">{{ $template->category->name }}</x-ui.badge>
                                    {{-- НОВОЕ: Вывод слага категории мелким шрифтом --}}
                                    <code class="text-[10px] text-muted-foreground">{{ $template->category->slug }}</code>
                                </div>
                            @else
                                <span class="text-xs text-muted-foreground italic">Удалена</span>
                            @endif
                        </x-ui.table-cell>
                        
                        <x-ui.table-cell class="hidden md:table-cell text-xs text-muted-foreground truncate max-w-xs">
                            {{ Str::limit($template->body, 80) }}
                        </x-ui.table-cell>
                        
                        <x-ui.table-cell class="text-center">
                            @if($template->is_active)
                                <x-ui.badge variant="success" size="xs">Активен</x-ui.badge>
                            @else
                                <x-ui.badge variant="secondary" size="xs">Выкл</x-ui.badge>
                            @endif
                        </x-ui.table-cell>
                        
                        <x-ui.table-cell class="text-right">
                            <div class="flex justify-end gap-1">
                                <x-ui.button wire:click="openEditTemplateModal({{ $template->id }})" variant="ghost" size="icon-sm" title="Редактировать">
                                    <x-lucide-pencil class="w-4 h-4 text-muted-foreground" />
                                </x-ui.button>
                                <x-ui.button wire:click="deleteTemplate({{ $template->id }})" wire:confirm="Удалить шаблон?" variant="ghost" size="icon-sm" title="Удалить">
                                    <x-lucide-trash-2 class="w-4 h-4 text-destructive" />
                                </x-ui.button>
                            </div>
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @empty
                    <x-ui.table-row wire:key="tpl-empty">
                        <x-ui.table-cell colspan="6" class="py-12 text-center text-muted-foreground">
                            <x-lucide-file-search class="w-12 h-12 opacity-30 mx-auto mb-2" />
                            Шаблонов не найдено.
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @endforelse
            </x-ui.table-body>
        </x-ui.table>

        <!-- Пагинация -->
        <div class="flex items-center justify-end flex-wrap gap-2 mt-4">
            <div class="text-xs text-muted-foreground">
                Показано {{ $this->templates->firstItem() ?? 0 }} - {{ $this->templates->lastItem() ?? 0 }} из {{ $this->templates->total() }}
            </div>
            {{ $this->templates->links('partials.pagination') }}
        </div>

    @endif

    <!-- ============================================ -->
    <!-- ВКЛАДКА: КАТЕГОРИИ                            -->
    <!-- ============================================ -->
    @if($activeTab === 'categories')
        <div class="flex items-center justify-between flex-wrap gap-4 mb-4">
            <x-ui.button wire:click="openCreateCategoryModal" variant="default" size="sm">
                <x-lucide-plus class="w-4 h-4" /> Создать категорию
            </x-ui.button>

            <div class="relative w-64">
                <x-ui.input wire:model.live.debounce.300ms="catSearch" type="search" placeholder="Поиск по ID, названию или slug..." class="pl-9 pr-8" />
                <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                @if (!empty($catSearch))
                   <button wire:click="clearCatSearch" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground z-10">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                @endif
            </div>           
        </div>

        <x-ui.table>
            <x-ui.table-header>
                <x-ui.table-row wire:key="tbl-head-cats">
                    <x-ui.table-head class="w-10">ID</x-ui.table-head>
                    <x-ui.table-head>Название</x-ui.table-head>
                    <x-ui.table-head class="w-32">Slug</x-ui.table-head>
                    <x-ui.table-head class="w-24 text-center">Статус</x-ui.table-head>
                    <x-ui.table-head class="w-24 text-center">Порядок</x-ui.table-head>
                    <x-ui.table-head class="w-32 text-right">Действия</x-ui.table-head>
                </x-ui.table-row>
            </x-ui.table-header>

            <x-ui.table-body>
                @forelse ($this->categories as $cat)
                    @php 
                         $isCatHighlighted = is_numeric($this->catSearch) && $cat->id === (int)$this->catSearch;
                    @endphp
                    <x-ui.table-row 
                        wire:key="cat-row-{{ $cat->id }}" 
                        class="{{ $isCatHighlighted ? 'bg-primary/10 ring-2 ring-primary/50 transition-all duration-500' : '' }}"
                        x-data="{ isHi: {{ $isCatHighlighted ? 'true' : 'false' }} }"
                        x-init="isHi && $nextTick(() => { $el.scrollIntoView({ behavior: 'smooth', block: 'center' }) })"
                    >
                        <x-ui.table-cell class="text-xs font-mono whitespace-nowrap {{ $isCatHighlighted ? 'text-primary font-bold' : 'text-muted-foreground' }}">
                            #{{ $cat->id }}
                        </x-ui.table-cell>
                        <x-ui.table-cell class="font-medium text-sm">
                            <span class="hover:text-primary cursor-pointer" wire:click="openEditCategoryModal({{ $cat->id }})">{{ $cat->name }}</span>
                            @if(in_array($cat->slug, ['general', 'obshhie']))
                                <x-ui.badge variant="outline" size="xs" class="ml-2 text-yellow-500 border-yellow-500/30">Системная</x-ui.badge>
                            @endif
                        </x-ui.table-cell>
                        <x-ui.table-cell class="text-xs font-mono text-muted-foreground">
                            {{ $cat->slug }}
                        </x-ui.table-cell>
                        <x-ui.table-cell class="text-center">
                            @if($cat->is_active)
                                <x-ui.badge variant="success" size="xs">Активна</x-ui.badge>
                            @else
                                <x-ui.badge variant="secondary" size="xs">Выкл</x-ui.badge>
                            @endif
                        </x-ui.table-cell>
                        <x-ui.table-cell class="text-center text-sm text-muted-foreground">
                            {{ $cat->sort_order }}
                        </x-ui.table-cell>
                        <x-ui.table-cell class="text-right">
                            <div class="flex justify-end gap-1">
                                <x-ui.button wire:click="openEditCategoryModal({{ $cat->id }})" variant="ghost" size="icon-sm" title="Редактировать">
                                    <x-lucide-pencil class="w-4 h-4 text-muted-foreground" />
                                </x-ui.button>
                                @if(!in_array($cat->slug, ['general', 'obshhie']))
                                    <x-ui.button wire:click="deleteCategory({{ $cat->id }})" wire:confirm="Удалить категорию? Шаблоны будут перемещены в 'Общие'." variant="ghost" size="icon-sm" title="Удалить">
                                        <x-lucide-trash-2 class="w-4 h-4 text-destructive" />
                                    </x-ui.button>
                                @endif
                            </div>
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @empty
                    <x-ui.table-row wire:key="cat-empty">
                        <x-ui.table-cell colspan="6" class="py-12 text-center text-muted-foreground">
                            <x-lucide-folder class="w-12 h-12 opacity-30 mx-auto mb-2" />
                            Категорий не найдено.
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @endforelse
            </x-ui.table-body>
        </x-ui.table>
    @endif

    <!-- ============================================ -->
    <!-- МОДАЛКА ШАБЛОНА                              -->
    <!-- ============================================ -->
    @if ($showTemplateModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" wire:key="template-modal" wire:click.self="$set('showTemplateModal', false)">
        <div class="bg-card border border-border rounded-lg shadow-2xl max-w-2xl w-full mx-4 overflow-hidden flex flex-col max-h-[90vh]" wire:click.stop>
            
            <div class="flex items-center justify-between p-4 border-b border-border shrink-0">
                <h2 class="text-lg font-semibold">{{ $this->templateId ? 'Редактирование шаблона' : 'Новый шаблон' }}</h2>
                <x-ui.button variant="ghost" size="icon-sm" wire:click="$set('showTemplateModal', false)">
                    <x-lucide-x class="w-5 h-5" />
                </x-ui.button>
            </div>

            <div class="p-6 space-y-4 overflow-y-auto little-scroll">
                <div class="space-y-1.5">
                    <x-ui.label for="category_id" class="text-xs">Категория</x-ui.label>
                    <x-ui.select wire:model.live="category_id" id="category_id" class="w-full">
                        <x-ui.select-trigger class="w-full">
                            <x-ui.select-value placeholder="Выберите категорию" />
                        </x-ui.select-trigger>
                        <x-ui.select-content>
                            @foreach($this->categories as $cat)
                                <x-ui.select-item wire:key="cat-opt-{{ $cat->id }}" value="{{ $cat->id }}">{{ $cat->name }}</x-ui.select-item>
                            @endforeach
                        </x-ui.select-content>
                    </x-ui.select>
                    @error('category_id') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                </div> 

                <div class="space-y-1.5">
                    <x-ui.label for="title" class="text-xs">Название (для меню)</x-ui.label>
                    <x-ui.input id="title" wire:model="title" placeholder="Например: Приветствие" />
                    @error('title') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-1.5">
                    <x-ui.label for="body" class="text-xs">Текст сообщения</x-ui.label>
                    <x-ui.textarea id="body" wire:model="body" rows="6" placeholder="Текст, который увидит юзер..." class="resize-y" />
                    @error('body') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center justify-between gap-6 pt-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <x-checkbox wire:model="is_active" />                          
                        <span class="text-sm">Активен (виден в чате)</span>
                    </label>
                    
                    <div class="flex items-center gap-2">
                        <x-ui.label for="sort_order" class="text-xs m-0">Порядок:</x-ui.label>
                        <x-ui.input type="number" wire:model="sort_order" class="max-w-[5rem]" />
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 p-4 border-t border-border bg-muted/20 shrink-0">
                <x-ui.button variant="outline" size="sm" wire:click="$set('showTemplateModal', false)">Отмена</x-ui.button>
                <x-ui.button wire:click="saveTemplate" variant="default" size="sm" wire:loading.attr="disabled" wire:target="saveTemplate">
                    <x-lucide-save class="w-4 h-4" wire:loading.remove wire:target="saveTemplate" />
                    <x-lucide-loader-2 class="w-4 h-4 animate-spin" wire:loading wire:target="saveTemplate" />
                    <span wire:loading.remove wire:target="saveTemplate">Сохранить</span>
                    <span wire:loading wire:target="saveTemplate">Сохранение...</span>
                </x-ui.button>
            </div>
        </div>
    </div>
    @endif

    <!-- ============================================ -->
    <!-- МОДАЛКА КАТЕГОРИИ                            -->
    <!-- ============================================ -->
    @if ($showCategoryModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" wire:key="category-modal" wire:click.self="$set('showCategoryModal', false)">
        <div class="bg-card border border-border rounded-lg shadow-2xl max-w-md w-full mx-4 overflow-hidden flex flex-col max-h-[90vh]" wire:click.stop>
            
            <div class="flex items-center justify-between p-4 border-b border-border shrink-0">
                <h2 class="text-lg font-semibold">{{ $this->catId ? 'Редактирование категории' : 'Новая категория' }}</h2>
                <x-ui.button variant="ghost" size="icon-sm" wire:click="$set('showCategoryModal', false)">
                    <x-lucide-x class="w-5 h-5" />
                </x-ui.button>
            </div>

            <div class="p-6 space-y-4 overflow-y-auto little-scroll">
                <div class="space-y-1.5">
                    <x-ui.label for="catName" class="text-xs">Название</x-ui.label>
                    <x-ui.input id="catName" wire:model="catName" placeholder="Например: Оплата" />
                    @error('catName') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-1.5">
                    <x-ui.label for="catSlug" class="text-xs">Slug (только латиница, цифры, дефис. Оставьте пустым для автогенерации)</x-ui.label>
                    <x-ui.input id="catSlug" wire:model="catSlug" placeholder="payment" />
                    @error('catSlug') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center justify-between gap-6 pt-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <x-checkbox wire:model="catIsActive" />                          
                        <span class="text-sm">Активна</span>
                    </label>
                    
                    <div class="flex items-center gap-2">
                        <x-ui.label for="catSortOrder" class="text-xs m-0">Порядок:</x-ui.label>
                        <x-ui.input type="number" wire:model="catSortOrder" class="max-w-[5rem]" />
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 p-4 border-t border-border bg-muted/20 shrink-0">
                <x-ui.button variant="outline" size="sm" wire:click="$set('showCategoryModal', false)">Отмена</x-ui.button>
                <x-ui.button wire:click="saveCategory" variant="default" size="sm" wire:loading.attr="disabled" wire:target="saveCategory">
                    <x-lucide-save class="w-4 h-4" wire:loading.remove wire:target="saveCategory" />
                    <x-lucide-loader-2 class="w-4 h-4 animate-spin" wire:loading wire:target="saveCategory" />
                    <span wire:loading.remove wire:target="saveCategory">Сохранить</span>
                    <span wire:loading wire:target="saveCategory">Сохранение...</span>
                </x-ui.button>
            </div>
        </div>
    </div>
    @endif

    <x-loading-overlay fixed="true" wire:loading.delay wire:key="overlay-loading-page"/>

</div>