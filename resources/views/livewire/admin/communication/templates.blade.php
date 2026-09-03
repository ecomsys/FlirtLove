<?php

use App\Actions\Admin\ManageSupportTemplatesAction;
use App\Models\SupportTemplate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Session;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    public ?int $templateId = null;
    public string $category = '';
    public string $title = '';
    public string $body = '';
    public bool $is_active = true;
    public int $sort_order = 0;
    
    public bool $showFormModal = false;
    
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Session]
    public string $categoryFilter = 'all';

    public string $backUrl = '';

    public function mount(): void
    {
        $previousUrl = url()->previous();
        $this->backUrl = ($previousUrl && $previousUrl !== url()->current()) 
            ? $previousUrl 
            : route('admin.dashboard');

        $this->category = 'Общие';

        // Умный поиск: если ищут по ID шаблона, автоматически переключаем фильтр на его реальную категорию
        if (!empty($this->search) && is_numeric($this->search)) {
            $template = SupportTemplate::find((int) $this->search);
            if ($template) {
                $this->categoryFilter = $template->category;
            }
        }
    }

    public function updatedSearch(): void 
    { 
        $this->resetPage(); 

        // ФИКС: Умная подсветка вкладки при ручном вводе ID
        if (is_numeric($this->search) && !empty($this->search)) {
            $template = SupportTemplate::find((int) $this->search);
            if ($template) {
                $this->categoryFilter = $template->category;
            }
        }
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function setCategoryFilter(string $category): void 
    { 
        $this->categoryFilter = $category; 
        $this->search = ''; // Очищаем поиск при переключении вкладок
        $this->resetPage(); 
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->categoryFilter = 'all';
        $this->resetPage();
    }

    #[Computed]
    public function categories()
    {
        return SupportTemplate::select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');
    }

    // ФИКС: Агрегатный подсчет счетчиков за 1 запрос (как в антифроде)
    #[Computed]
    public function counts(): array
    {
        $stats = SupportTemplate::query()
            ->selectRaw("COUNT(*) as total")
            ->selectRaw("SUM(CASE WHEN is_active = true THEN 1 ELSE 0 END) as active")
            ->first();

        // Считаем количество по каждой категории динамически
        $categoryCounts = SupportTemplate::query()
            ->selectRaw("category, COUNT(*) as count")
            ->groupBy('category')
            ->pluck('count', 'category');

        return [
            'total' => $stats->total ?? 0,
            'active' => $stats->active ?? 0,
            'categories' => $categoryCounts->toArray(),
        ];
    }

    #[Computed]
    public function templates()
    {
        $searchOperator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

        return SupportTemplate::query()
            ->when($this->categoryFilter !== 'all', fn($q) => $q->where('category', $this->categoryFilter))
            ->when($this->search, function ($q) use ($searchOperator) {
                // Умный поиск: если число, ищем по ID
                if (is_numeric($this->search)) {
                    $q->where('id', (int) $this->search);
                } else {
                    $q->where('title', $searchOperator, "%{$this->search}%")
                      ->orWhere('category', $searchOperator, "%{$this->search}%")
                      ->orWhere('body', $searchOperator, "%{$this->search}%");
                }
            })
            ->orderBy('category')
            ->orderBy('sort_order')
            ->paginate(15);
    }

    public function openCreateModal(): void
    {
        $this->resetValidation();
        $this->templateId = null;
        $this->category = 'Общие';
        $this->title = '';
        $this->body = '';
        $this->is_active = true;
        $this->sort_order = 0;
        $this->showFormModal = true;
    }

    public function openEditModal(int $id): void
    {
        $template = SupportTemplate::find($id);
        if (!$template) return;

        $this->resetValidation();
        $this->templateId = $template->id;
        $this->category = $template->category;
        $this->title = $template->title;
        $this->body = $template->body;
        $this->is_active = $template->is_active;
        $this->sort_order = $template->sort_order;
        $this->showFormModal = true;
    }

    public function save(ManageSupportTemplatesAction $action): void
    {
        $this->validate([
            'category' => 'required|string|max:100',
            'title' => 'required|string|max:150',
            'body' => 'required|string|max:2000',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $data = [
            'category' => $this->category,
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

        $this->showFormModal = false;
        unset($this->templates);
        unset($this->categories);
        unset($this->counts); // Сбрасываем кэш счетчиков
    }

    public function deleteTemplate(int $id, ManageSupportTemplatesAction $action): void
    {
        $template = SupportTemplate::find($id);
        if ($template) {
            $action->delete($template, auth()->user());
            $this->dispatch('show-toast', type: 'warning', message: 'Шаблон удален.');
            unset($this->templates);
            unset($this->categories);
            unset($this->counts); // Сбрасываем кэш счетчиков
        }
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
                <p class="text-sm text-muted-foreground mt-1">
                    Всего записей: <span class="text-primary font-bold">{{ $this->counts['total'] }}</span>
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2">
             @if($search || $categoryFilter !== 'all')
                <x-ui.button wire:click="clearFilters" variant="ghost" size="sm" class="text-muted-foreground">
                    <x-lucide-x class="w-4 h-4" /> Сбросить
                </x-ui.button>
            @endif
            
            
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
    </div>

    <!-- ФИЛЬТРЫ (Кнопки категорий) -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-1.5 items-center">
            <x-ui.button wire:click="setCategoryFilter('all')" variant="{{ $categoryFilter === 'all' ? 'default' : 'secondary' }}" size="sm">
                Все <x-ui.badge size="xs">{{ $this->counts['total'] }}</x-ui.badge>
            </x-ui.button>

            @foreach($this->categories as $cat)
                <x-ui.button wire:key="cat-{{ \Illuminate\Support\Str::slug($cat) }}" wire:click="setCategoryFilter('{{ $cat }}')" variant="{{ $categoryFilter === $cat ? 'default' : 'secondary' }}" size="sm">
                    {{ $cat }} <x-ui.badge size="xs" variant="secondary">{{ $this->counts['categories'][$cat] ?? 0 }}</x-ui.badge>
                </x-ui.button>
            @endforeach
            
        </div>
        
    </div>

    <!-- ТАБЛИЦА -->
    <x-ui.table>
        <x-ui.table-header>
            <x-ui.table-row wire:key="tbl-head">
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
                        <button wire:click="openEditModal({{ $template->id }})" class="text-left hover:text-primary transition-colors">
                            {{ $template->title }}
                        </button>
                    </x-ui.table-cell>

                    <x-ui.table-cell class="font-medium text-sm">
                        <x-ui.badge variant="secondary" size="xs">{{ $template->category }}</x-ui.badge>
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
                            <x-ui.button wire:click="openEditModal({{ $template->id }})" variant="ghost" size="icon-sm" title="Редактировать">
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
    <div class="flex items-center justify-end flex-wrap gap-2">
        <div class="text-xs text-muted-foreground">
            Показано {{ $this->templates->firstItem() ?? 0 }} - {{ $this->templates->lastItem() ?? 0 }} из {{ $this->templates->total() }}
        </div>
        {{ $this->templates->links('partials.pagination') }}
    </div>

    <!-- Модалка -->
    @if ($showFormModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" wire:key="template-modal" wire:click.self="$set('showFormModal', false)">
        <div class="bg-card border border-border rounded-lg shadow-2xl max-w-2xl w-full mx-4 overflow-hidden flex flex-col max-h-[90vh]" wire:click.stop>
            
            <div class="flex items-center justify-between p-4 border-b border-border shrink-0">
                <h2 class="text-lg font-semibold">{{ $this->templateId ? 'Редактирование шаблона' : 'Новый шаблон' }}</h2>
                <x-ui.button variant="ghost" size="icon-sm" wire:click="$set('showFormModal', false)">
                    <x-lucide-x class="w-5 h-5" />
                </x-ui.button>
            </div>

            <div class="p-6 space-y-4 overflow-y-auto little-scroll">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <x-ui.label for="category" class="text-xs">Категория</x-ui.label>
                        <x-ui.input id="category" wire:model="category" placeholder="Например: Общие, Оплата" />
                        @error('category') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>
                    <div class="space-y-1.5">
                        <x-ui.label for="title" class="text-xs">Название (для меню)</x-ui.label>
                        <x-ui.input id="title" wire:model="title" placeholder="Например: Приветствие" />
                        @error('title') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>
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
                <x-ui.button variant="outline" size="sm" wire:click="$set('showFormModal', false)">Отмена</x-ui.button>
                <x-ui.button wire:click="save" variant="default" size="sm" wire:loading.attr="disabled" wire:target="save">
                    <x-lucide-save class="w-4 h-4 wire:loading.remove" wire:target="save" />
                    <x-lucide-loader-2 class="w-4 h-4 animate-spin hidden" wire:loading wire:target="save" />
                    Сохранить
                </x-ui.button>
            </div>
        </div>
    </div>
    @endif
</div>