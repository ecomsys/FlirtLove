<?php

use App\Models\AdminLog;
use App\Models\Page;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public int $perPage = 10;

    // Поля формы
    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public ?int $editingId = null;
    
    public string $modalTitle = '';
    public string $modalSlug = '';
    public string $modalBody = '';
    public string $modalMetaDescription = '';
    public bool $modalIsActive = true;

    // Автогенерация слага из заголовка при создании
    public function updatedModalTitle(string $value): void
    {
        // Генерируем слаг только если это новая страница (не редактирование)
        // и слаг пустой или совпадает с предыдущим транслитерированным значением
        if (is_null($this->editingId)) {
            $this->modalSlug = Str::slug($value);
        }
    }

    public function createModal(): void
    {
        $this->reset(['modalTitle', 'modalSlug', 'modalBody', 'modalMetaDescription', 'modalIsActive', 'editingId']);
        $this->modalIsActive = true;
        $this->resetValidation();
        $this->showCreateModal = true;
    }

    public function editModal(int $id): void
    {
        $page = Page::find($id);
        if (!$page) return;

        $this->editingId = $id;
        $this->modalTitle = $page->title;
        $this->modalSlug = $page->slug;
        $this->modalBody = $page->body;
        $this->modalMetaDescription = $page->meta_description;
        $this->modalIsActive = $page->is_active;
        
        $this->resetValidation();
        $this->showEditModal = true;
    }

    public function savePage(): void
    {
        $this->validate($this->rules());

        $page = Page::create([
            'title' => $this->modalTitle,
            'slug' => $this->modalSlug,
            'body' => $this->modalBody,
            'meta_description' => $this->modalMetaDescription,
            'is_active' => $this->modalIsActive,
        ]);

        AdminLog::record('page.create', $page, auth()->user());

        $this->dispatch('show-toast', type: 'success', message: 'Страница создана!');
        $this->showCreateModal = false;
    }

    public function updatePage(): void
    {
        $this->validate($this->rules());

        $page = Page::find($this->editingId);
        if (!$page) return;

        $page->update([
            'title' => $this->modalTitle,
            'slug' => $this->modalSlug,
            'body' => $this->modalBody,
            'meta_description' => $this->modalMetaDescription,
            'is_active' => $this->modalIsActive,
        ]);

        AdminLog::record('page.update', $page, auth()->user());

        $this->dispatch('show-toast', type: 'success', message: 'Страница обновлена!');
        $this->showEditModal = false;
    }

    public function deletePage(int $id): void
    {
        $page = Page::find($id);
        if ($page) {
            AdminLog::record('page.delete', $page, auth()->user());
            $page->delete();
            $this->dispatch('show-toast', type: 'success', message: 'Страница удалена');
        }
    }

    public function toggleStatus(int $id): void
    {
        $page = Page::find($id);
        if ($page) {
            $page->update(['is_active' => !$page->is_active]);
            AdminLog::record('page.update', $page, auth()->user(), ['is_active' => !$page->is_active], ['is_active' => $page->is_active]);
          $this->dispatch('show-toast', 
                type: 'success', 
                message: $page->is_active ? 'Страница опубликована' : 'Страница снята с публикации'
            );
            }
    }

    public function duplicatePage(int $id): void
    {
        $page = Page::find($id);
        if ($page) {
            $new = $page->replicate();
            $new->slug = $page->slug . '-copy';
            $new->title = $page->title . ' (Копия)';
            $new->is_active = false; // Дубликаты по дефолту черновики
            $new->save();

            AdminLog::record('page.create', $new, auth()->user());

            $this->dispatch('show-toast', type: 'success', message: 'Страница продублирована');
        }
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    protected function rules(): array
    {
        $slugRule = 'required|alpha_dash|unique:pages,slug';
        if ($this->editingId) {
            $slugRule .= ',' . $this->editingId;
        }

        return [
            'modalTitle' => 'required|string|max:255',
            'modalSlug' => $slugRule,
            'modalBody' => 'nullable|string',
            'modalMetaDescription' => 'nullable|string|max:500',
            'modalIsActive' => 'boolean',
        ];
    }

    #[Computed]
    public function pages()
    {
        return Page::query()
            ->when($this->search, function ($query) {
                $query->where('title', 'like', "%{$this->search}%")
                      ->orWhere('slug', 'like', "%{$this->search}%");
            })
            ->when($this->statusFilter === 'active', fn($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'draft', fn($q) => $q->where('is_active', false))
            ->latest()
            ->paginate($this->perPage);
    }

    #[Computed]
    public function counts(): array
    {
        return [
            'all' => Page::count(),
            'active' => Page::where('is_active', true)->count(),
            'draft' => Page::where('is_active', false)->count(),
        ];
    }
}; 
?>

<div class="space-y-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <h1 class="text-2xl font-semibold flex items-center gap-2">
            <x-lucide-file-text class="w-6 h-6" />
            Страницы
        </h1>

        <x-ui.button wire:click="createModal" variant="default" size="sm">
            <x-lucide-plus class="w-4 h-4" />
            Создать страницу
        </x-ui.button>
    </div>

    <!-- Фильтры -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-1.5">
            <x-ui.button wire:click="setStatusFilter('all')" variant="{{ $statusFilter === 'all' ? 'default' : 'secondary' }}" size="sm">
                Все <x-ui.badge size="xs">{{ $this->counts['all'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="setStatusFilter('active')" variant="{{ $statusFilter === 'active' ? 'default' : 'secondary' }}" size="sm">
                Опубликованные <x-ui.badge size="xs" variant="success">{{ $this->counts['active'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="setStatusFilter('draft')" variant="{{ $statusFilter === 'draft' ? 'default' : 'secondary' }}" size="sm">
                Черновики <x-ui.badge size="xs" variant="warning">{{ $this->counts['draft'] }}</x-ui.badge>
            </x-ui.button>
        </div>

        <div class="relative w-64">
            <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск по названию или слагу..." class="pl-9 pr-8" />
            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            @if (!empty($search))
                <button wire:click="$set('search', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
                    <x-lucide-x class="w-4 h-4" />
                </button>
            @endif
        </div>
    </div>

    <!-- Таблица -->
    <x-ui.table>
        <x-ui.table-header>
            <x-ui.table-row>
                <x-ui.table-head>Заголовок</x-ui.table-head>
                <x-ui.table-head>URL (Slug)</x-ui.table-head>
                <x-ui.table-head>Статус</x-ui.table-head>
                <x-ui.table-head>Дата обновления</x-ui.table-head>
                <x-ui.table-head class="text-right">Действия</x-ui.table-row>
            </x-ui.table-row>
        </x-ui.table-header>

        <x-ui.table-body>
            @forelse ($this->pages as $page)
                <x-ui.table-row wire:key="page-{{ $page->id }}">
                    <x-ui.table-cell>
                        <div class="font-medium text-sm">{{ $page->title }}</div>
                        @if($page->meta_description)
                            <div class="text-xs text-muted-foreground line-clamp-1 mt-0.5">{{ $page->meta_description }}</div>
                        @endif
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        <code class="text-xs px-1.5 py-0.5 bg-muted rounded">/{{ $page->slug }}</code>
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        <button wire:click="toggleStatus({{ $page->id }})" class="cursor-pointer">
                            @if($page->is_active)
                                <x-ui.badge variant="success" size="sm">Опубликована</x-ui.badge>
                            @else
                                <x-ui.badge variant="warning" size="sm">Черновик</x-ui.badge>
                            @endif
                        </button>
                    </x-ui.table-cell>
                    <x-ui.table-cell class="text-muted-foreground text-xs whitespace-nowrap">
                        {{ $page->updated_at->format('d.m.Y H:i') }}
                    </x-ui.table-cell>
                    <x-ui.table-cell class="text-right">
                        <x-ui.dropdown-menu>
                            <x-ui.dropdown-menu-trigger>
                                <x-ui.button variant="ghost" size="icon-sm"><x-lucide-more-horizontal class="w-4 h-4" /></x-ui.button>
                            </x-ui.dropdown-menu-trigger>
                            <x-ui.dropdown-menu-content align="end">
                                <x-ui.dropdown-menu-item wire:click="editModal({{ $page->id }})">
                                    <x-lucide-pencil class="w-4 h-4" /> Редактировать
                                </x-ui.dropdown-menu-item>
                                <x-ui.dropdown-menu-item wire:click="duplicatePage({{ $page->id }})">
                                    <x-lucide-copy class="w-4 h-4" /> Дублировать
                                </x-ui.dropdown-menu-item>
                                <x-ui.dropdown-menu-item variant="destructive" wire:click="deletePage({{ $page->id }})" wire:confirm="Удалить эту страницу?">
                                    <x-lucide-trash-2 class="w-4 h-4" /> Удалить
                                </x-ui.dropdown-menu-item>
                            </x-ui.dropdown-menu-content>
                        </x-ui.dropdown-menu>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @empty
                <x-ui.table-row>
                    <x-ui.table-cell colspan="5" class="py-12 text-center text-muted-foreground">
                        <div class="flex flex-col items-center gap-2">
                            <x-lucide-file-x class="w-12 h-12 opacity-30" />
                            <p>Страницы не найдены</p>
                        </div>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @endforelse
        </x-ui.table-body>
    </x-ui.table>

    <!-- Пагинация -->
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div class="text-xs text-muted-foreground">
            Показано {{ $this->pages->firstItem() ?? 0 }} - {{ $this->pages->lastItem() ?? 0 }} из {{ $this->pages->total() }}
        </div>
        {{ $this->pages->links('partials.pagination') }}
    </div>

    {{-- МОДАЛКА СОЗДАНИЯ --}}
    <div x-show="$wire.showCreateModal" x-cloak @click.self="$wire.showCreateModal = false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" @keydown.escape.window="$wire.showCreateModal = false">
        <div class="relative bg-card border border-border rounded-lg shadow-2xl max-w-4xl w-full mx-4 overflow-hidden max-h-[90vh] flex flex-col">
            <div class="flex items-center justify-between p-4 border-b border-border">
                <h2 class="text-lg font-semibold">Создать страницу</h2>
                <button @click="$wire.showCreateModal = false" class="text-muted-foreground hover:text-foreground"><x-lucide-x class="w-5 h-5" /></button>
            </div>

            <div class="p-6 space-y-4 overflow-y-auto little-scroll">
                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <x-ui.label for="c-title">Заголовок</x-ui.label>
                        <x-ui.input id="c-title" wire:model.live="modalTitle" placeholder="Название страницы" />
                        @error('modalTitle') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex flex-col gap-2">
                        <x-ui.label for="c-slug">URL (Slug)</x-ui.label>
                        <x-ui.input id="c-slug" wire:model="modalSlug" placeholder="auto-generated-slug" />
                        @error('modalSlug') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <x-ui.label for="c-meta">Meta Description (SEO)</x-ui.label>
                    <x-ui.textarea id="c-meta" wire:model="modalMetaDescription" rows="2" placeholder="Краткое описание для поисковиков..." class="resize-none" />
                    @error('modalMetaDescription') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-2">
                    <x-ui.label>Контент страницы</x-ui.label>
                    <!-- 
                        ВАЖНО: Замени этот textarea на WYSIWYG редактор (Trix, TinyMCE, TipTap)
                        когда будешь подключать визуальный редактор. 
                        Для этого textarea пока используется моноширинный шрифт для удобства HTML-кода.
                    -->
                    <x-ui.textarea wire:model="modalBody" rows="12" class="font-mono text-xs little-scroll" placeholder="Введите HTML контент или текст..." />
                    @error('modalBody') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="modalIsActive" class="sr-only peer" />
                        <div class="w-11 h-6 bg-muted rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-primary after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:border-primary"></div>
                    </label>
                    <span class="text-sm font-medium">Опубликовать сразу</span>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 p-4 border-t border-border bg-muted/20 mt-auto">
                <x-ui.button @click="$wire.showCreateModal = false" variant="outline" size="sm">Отмена</x-ui.button>
                <x-ui.button wire:click="savePage" variant="default" size="sm">
                    <x-lucide-save class="w-4 h-4 inline" /> Сохранить
                </x-ui.button>
            </div>
        </div>
    </div>

    {{-- МОДАЛКА РЕДАКТИРОВАНИЯ --}}
    <div x-show="$wire.showEditModal" x-cloak @click.self="$wire.showEditModal = false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" @keydown.escape.window="$wire.showEditModal = false">
        <div class="relative bg-card border border-border rounded-lg shadow-2xl max-w-4xl w-full mx-4 overflow-hidden max-h-[90vh] flex flex-col">
            <div class="flex items-center justify-between p-4 border-b border-border">
                <h2 class="text-lg font-semibold">Редактировать страницу</h2>
                <button @click="$wire.showEditModal = false" class="text-muted-foreground hover:text-foreground"><x-lucide-x class="w-5 h-5" /></button>
            </div>

            <div class="p-6 space-y-4 overflow-y-auto little-scroll">
                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <x-ui.label for="e-title">Заголовок</x-ui.label>
                        <x-ui.input id="e-title" wire:model="modalTitle" />
                        @error('modalTitle') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex flex-col gap-2">
                        <x-ui.label for="e-slug">URL (Slug)</x-ui.label>
                        <x-ui.input id="e-slug" wire:model="modalSlug" />
                        @error('modalSlug') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <x-ui.label for="e-meta">Meta Description (SEO)</x-ui.label>
                    <x-ui.textarea id="e-meta" wire:model="modalMetaDescription" rows="2" class="resize-none" />
                    @error('modalMetaDescription') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-2">
                    <x-ui.label>Контент страницы</x-ui.label>
                    <x-ui.textarea wire:model="modalBody" rows="12" class="font-mono text-xs little-scroll" />
                    @error('modalBody') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="modalIsActive" class="sr-only peer" />
                        <div class="w-11 h-6 bg-muted rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-primary after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:border-primary"></div>
                    </label>
                    <span class="text-sm font-medium">Страница опубликована</span>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 p-4 border-t border-border bg-muted/20 mt-auto">
                <x-ui.button @click="$wire.showEditModal = false" variant="outline" size="sm">Отмена</x-ui.button>
                <x-ui.button wire:click="updatePage" variant="default" size="sm">
                    <x-lucide-save class="w-4 h-4 inline" /> Обновить
                </x-ui.button>
            </div>
        </div>
    </div>
</div>