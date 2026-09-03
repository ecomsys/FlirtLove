<?php

use App\Actions\Admin\StopWordsAction;
use App\Enums\StopWordAction;
use App\Enums\StopWordCategory;
use App\Models\StopWord;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';
    
    #[Url(as: 'category', except: 'all')]
    public string $categoryFilter = 'all';
    
    #[Url(as: 'action', except: 'all')]
    public string $actionFilter = 'all';
    
    #[Url(as: 'status', except: 'all')]
    public string $statusFilter = 'all';
    
    public int $perPage = 50;

    public int $filterVersion = 0;

    /** @var string URL для кнопки "Назад" */
    public string $backUrl = '';
    public array $selected = [];
    public bool $selectAll = false;
    public string $bulkAction = '';

    public bool $showAddModal = false;
    public string $bulkWords = '';
    // Дефолтные значения берем из Enum
    public string $modalCategory = StopWordCategory::Mat->value; 
    public string $modalAction = StopWordAction::Mask->value;

        public function mount(): void
    {
        // ФИКС: Запоминаем URL "Назад" только при первой загрузке
        $previousUrl = url()->previous();
        $this->backUrl = ($previousUrl && $previousUrl !== url()->current()) 
            ? $previousUrl 
            : route('admin.dashboard');
    }

    public function updatedSearch(): void { $this->resetPage(); $this->clearSelection(); }
    
    // ФИКС: Очищаем поиск при смене любого фильтра
    public function updatedCategoryFilter(): void { $this->search = ''; $this->resetPage(); $this->clearSelection(); }
    public function updatedActionFilter(): void { $this->search = ''; $this->resetPage(); $this->clearSelection(); }
    public function updatedStatusFilter(): void { $this->search = ''; $this->resetPage(); $this->clearSelection(); }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
        $this->clearSelection();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->categoryFilter = 'all';
        $this->actionFilter = 'all';
        $this->statusFilter = 'all';
        $this->resetPage();
        $this->clearSelection();
        $this->filterVersion++;
    }

    public function clearSelection(): void 
    { 
        $this->selected = []; 
        $this->selectAll = false; 
    }

    public function updatedSelectAll($value): void
    {
        $this->selected = $value ? $this->stopWords->getCollection()->pluck('id')->map(fn($id) => (string) $id)->toArray() : [];
    }

    public function applyBulkAction(StopWordsAction $action): void
    {
        if (empty($this->selected) || empty($this->bulkAction)) return;

        try {
            $action->applyBulk($this->selected, $this->bulkAction, auth()->user());
            $this->dispatch('show-toast', type: 'success', message: 'Массовое действие применено');
        } catch (\Exception $e) {
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера!');
        }

        $this->clearSelection();
        $this->bulkAction = '';
    }

    #[Computed]
    public function stopWords()
    {
        $searchOperator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

        return StopWord::query()
            ->when($this->search, function ($q) use ($searchOperator) {
                $q->where('word', $searchOperator, "%{$this->search}%")
                  ->orWhereRaw("CAST(id AS TEXT) {$searchOperator} ?", ["%{$this->search}%"]);
            })
            ->when($this->categoryFilter !== 'all', fn($q) => $q->where('category', $this->categoryFilter))
            ->when($this->actionFilter !== 'all', fn($q) => $q->where('action', $this->actionFilter))
            ->when($this->statusFilter !== 'all', fn($q) => $q->where('is_active', $this->statusFilter === 'active'))
            ->latest('created_at')
            ->latest('id')
            ->paginate(min(max($this->perPage, 10), 200));
    }

    #[Computed]
    public function counts(): array
    {
        $stats = StopWord::query()
            ->selectRaw("COUNT(*) as total")
            ->selectRaw("SUM(CASE WHEN is_active = true THEN 1 ELSE 0 END) as active")
            ->first();

        return [
            'total' => $stats->total ?? 0,
            'active' => $stats->active ?? 0,
        ];
    }

    public function toggleActive(int $id, StopWordsAction $action): void
    {
        $action->toggleActive($id, auth()->user());
        $this->dispatch('show-toast', type: 'success', message: 'Статус изменен');
    }

    public function deleteWord(int $id, StopWordsAction $action): void
    {
        $action->deleteWord($id, auth()->user());
        $this->dispatch('show-toast', type: 'success', message: 'Слово удалено');
    }

    public function saveBulkWords(StopWordsAction $action): void
    {
        if (empty(trim($this->bulkWords))) {
            $this->dispatch('show-toast', type: 'error', message: 'Нечего сохранять: поле пустое!');
            return;
        }

        // ФИКС: Валидация через Enum
        $this->validate([
            'bulkWords' => 'required|string',
            'modalCategory' => ['required', Rule::enum(StopWordCategory::class)],
            'modalAction' => ['required', Rule::enum(StopWordAction::class)],
        ]);

        // Передаем в Action уже объекты Enum, а не строки!
        $createdCount = $action->createBulk(
            $this->bulkWords, 
            StopWordCategory::from($this->modalCategory), 
            StopWordAction::from($this->modalAction),
            auth()->user()
        );
        
        $this->showAddModal = false;
        $this->bulkWords = '';

        if ($createdCount > 0) {
            $this->dispatch('show-toast', type: 'success', message: "Добавлено новых слов: {$createdCount}");
        } else {
            $this->dispatch('show-toast', type: 'error', message: 'Все введенные слова уже существуют в базе!');
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
                    <x-lucide-shield-alert class="w-6 h-6" />
                    Стоп-слова и фильтры
                </h1>
                <p class="text-sm text-muted-foreground mt-1">
                    Всего в базе: <span class="text-foreground font-medium">{{ $this->counts['total'] }}</span> · 
                    Активно: <span class="text-primary font-medium">{{ $this->counts['active'] }}</span>
                </p>
            </div>
        </div>

        <x-ui.button wire:click="$set('showAddModal', true)" variant="default" size="sm">
            <x-lucide-plus class="w-4 h-4" /> Добавить слова
        </x-ui.button>
    </div>

    <!-- ФИЛЬТРЫ -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2 items-center">
           <!-- Фильтр категорий -->
            <x-ui.select wire:key="cat-filter-{{ $filterVersion }}" wire:model.live="categoryFilter">
                <x-ui.select-trigger class="w-50">
                    <x-ui.select-value placeholder="Все категории" />
                </x-ui.select-trigger>
                <x-ui.select-content>
                    <x-ui.select-item value="all">Все категории</x-ui.select-item>
                    @foreach(\App\Enums\StopWordCategory::options() as $value => $label)
                        <x-ui.select-item wire:key="cat-opt-{{ $value }}" value="{{ $value }}">{{ $label }}</x-ui.select-item>
                    @endforeach
                </x-ui.select-content>
            </x-ui.select>

            <!-- Фильтр действий -->
            <x-ui.select wire:key="act-filter-{{ $filterVersion }}" wire:model.live="actionFilter">
                <x-ui.select-trigger class="w-50">
                    <x-ui.select-value placeholder="Поведение фильтра" />
                </x-ui.select-trigger>
                <x-ui.select-content>
                    <x-ui.select-item value="all">Любое поведение</x-ui.select-item>
                    @foreach(\App\Enums\StopWordAction::options() as $value => $label)
                        <x-ui.select-item wire:key="act-opt-{{ $value }}" value="{{ $value }}">{{ $label }}</x-ui.select-item>
                    @endforeach
                </x-ui.select-content>
            </x-ui.select>

            <!-- НОВЫЙ ФИЛЬТР СТАТУСА -->
            <x-ui.select wire:key="status-filter-{{ $filterVersion }}" wire:model.live="statusFilter">
                <x-ui.select-trigger class="w-50">
                    <x-ui.select-value placeholder="Статус" />
                </x-ui.select-trigger>
                <x-ui.select-content>
                    <x-ui.select-item value="all">Все статусы</x-ui.select-item>
                    <x-ui.select-item value="active">Только активные</x-ui.select-item>
                    <x-ui.select-item value="inactive">Только выключенные</x-ui.select-item>
                </x-ui.select-content>
            </x-ui.select>

            @if($search || $categoryFilter !== 'all' || $actionFilter !== 'all' || $statusFilter !== 'all')
                <x-ui.button wire:click="clearFilters" variant="outline" size="sm" class="text-muted-foreground">
                    <x-lucide-x class="w-4 h-4" /> Сбросить
                </x-ui.button>
            @endif
        </div>

        <div class="relative w-64">
            <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск по названию или id..." class="pl-9 pr-8" />
            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            @if (!empty($search))
                <button wire:click="clearSearch" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground z-10">
                    <x-lucide-x class="w-4 h-4" />
                </button>
            @endif
        </div>
    </div>

    <!-- Панель массовых действий -->
    <div wire:key="bulk-panel" x-show="$wire.selected.length > 0" x-cloak x-transition class="bg-muted/30 border border-border rounded-lg p-3 flex flex-wrap items-center gap-3">
        <span class="text-sm font-medium">Выбрано: <span x-text="$wire.selected.length" class="text-primary"></span></span>
        <div class="flex-1"></div>
        
        <x-ui.select wire:key="bulk-select-{{ $bulkAction }}" wire:model.live="bulkAction">
            <x-ui.select-trigger class="min-w-50">
                <x-ui.select-value placeholder="Массовое действие..." />
            </x-ui.select-trigger>
            <x-ui.select-content>
                <x-ui.select-item value="activate">Активировать</x-ui.select-item>
                <x-ui.select-item value="deactivate">Деактивировать</x-ui.select-item>
                <x-ui.select-item value="delete" class="text-destructive focus:text-destructive">Удалить навсегда</x-ui.select-item>
            </x-ui.select-content>
        </x-ui.select>
        
        <x-ui.button wire:click="applyBulkAction" variant="default" size="sm" wire:confirm="Вы уверены?" wire:target="applyBulkAction" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="applyBulkAction" class="flex items-center gap-2"><x-lucide-check class="w-4 h-4 inline" /> <span>Применить</span></span>
            <span wire:loading wire:target="applyBulkAction" class="flex items-center gap-2"><x-lucide-loader-2 class="w-4 h-4 animate-spin inline" /> <span>Применяется...</span></span>
        </x-ui.button>
        <x-ui.button wire:click="clearSelection" variant="outline" size="sm"><x-lucide-x class="w-4 h-4" /> Снять выделение</x-ui.button>
    </div>

    <!-- ТАБЛИЦА -->
    <x-ui.table>
        <x-ui.table-header>
            <x-ui.table-row>
                <x-ui.table-head class="w-10"><x-checkbox wire:model.live="selectAll" /></x-ui.table-head>
                <x-ui.table-head class="w-10">ID</x-ui.table-head>
                <x-ui.table-head class="w-2/3">Слово / Фраза / Regex</x-ui.table-head>
                <x-ui.table-head>Категория</x-ui.table-head>
                <x-ui.table-head>Поведение фильтра</x-ui.table-head>
                <x-ui.table-head class="text-center">Активно</x-ui.table-head>
                <x-ui.table-head class="text-right">Действия</x-ui.table-head>
            </x-ui.table-row>
        </x-ui.table-header>

        <x-ui.table-body>
            @forelse ($this->stopWords as $word)
                @php 
                    $isHighlighted = is_numeric($this->search) && $word->id === (int)$this->search; 
                @endphp
                <x-ui.table-row 
                    wire:key="word-{{ $word->id }}" 
                    class="{{ $isHighlighted ? 'bg-primary/10 ring-2 ring-primary/50 transition-all duration-500' : '' }} {{ in_array((string)$word->id, $this->selected) ? 'bg-muted/30' : '' }}"
                    x-data="{ isHi: {{ $isHighlighted ? 'true' : 'false' }} }"
                    x-init="isHi && $nextTick(() => { $el.scrollIntoView({ behavior: 'smooth', block: 'center' }) })"
                >
                    <x-ui.table-cell>
                        <x-checkbox wire:model.live="selected" value="{{ $word->id }}" />
                    </x-ui.table-cell>
                    
                    <x-ui.table-cell class="text-xs font-mono whitespace-nowrap {{ $isHighlighted ? 'text-primary font-bold' : 'text-muted-foreground' }}">
                        #{{ $word->id }}
                    </x-ui.table-cell>
                    
                    <x-ui.table-cell class="font-medium">
                        <span class="font-mono text-sm bg-muted px-2 py-1 rounded">{{ $word->word }}</span>
                    </x-ui.table-cell>
                    
                    <x-ui.table-cell>
                        <span class="text-xs text-muted-foreground">
                            {{ $word->category->label() }}
                        </span>
                    </x-ui.table-cell>
                    
                    <x-ui.table-cell>
                        <x-ui.badge variant="{{ $word->actionBadge['variant'] }}" size="sm">
                            {{ $word->actionBadge['label'] }}
                        </x-ui.badge>
                    </x-ui.table-cell>
                    
                    <x-ui.table-cell class="text-center">
                        <button wire:click="toggleActive({{ $word->id }})" class="cursor-pointer">
                            @if($word->is_active)
                                <x-lucide-check-circle-2 class="w-5 h-5 text-primary inline" />
                            @else
                                <x-lucide-circle class="w-5 h-5 text-muted-foreground inline" />
                            @endif
                        </button>
                    </x-ui.table-cell>
                    
                    <x-ui.table-cell class="text-right">
                        <x-ui.button variant="ghost" size="icon-sm" wire:click="deleteWord({{ $word->id }})" wire:confirm="Удалить это слово?">
                            <x-lucide-trash-2 class="w-4 h-4 text-destructive" />
                        </x-ui.button>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @empty
                <x-ui.table-row wire:key="empty-state">
                    <x-ui.table-cell colspan="7" class="py-12 text-center text-muted-foreground">
                        <x-lucide-shield-off class="w-12 h-12 opacity-30 mx-auto mb-2" />
                        Слова не найдены. Добавьте первые!
                    </x-ui.table-cell>
                </x-ui.table-row>
            @endforelse
        </x-ui.table-body>
    </x-ui.table>

    <!-- Пагинация -->
    <div class="flex items-center justify-end flex-wrap gap-2">
        <div class="text-xs text-muted-foreground">
            Показано {{ $this->stopWords->firstItem() ?? 0 }} - {{ $this->stopWords->lastItem() ?? 0 }} из {{ $this->stopWords->total() }}
        </div>
        {{ $this->stopWords->links('partials.pagination') }}
    </div>

    <!-- МОДАЛКА МАССОВОГО ДОБАВЛЕНИЯ -->
    <div wire:key="add-modal" x-data="{}" x-show="$wire.showAddModal" 
         x-cloak 
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         @click.self="$wire.showAddModal = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        
        <div class="relative bg-card border border-border rounded-lg shadow-2xl max-w-lg w-full mx-4 overflow-hidden">
            <div class="flex items-center justify-between p-4 border-b border-border">
                <h2 class="text-lg font-semibold">Добавить стоп-слова</h2>
                <x-ui.button variant="ghost" size="icon-sm" @click="$wire.showAddModal = false">
                    <x-lucide-x class="w-5 h-5" />
                </x-ui.button>
            </div>

            <div class="p-6 space-y-4">
                <div class="flex flex-col gap-2">
                    <x-ui.label>Слова или фразы</x-ui.label>
                    <x-ui.textarea wire:model.live="bulkWords" rows="5" placeholder="Введите слова через запятую или с новой строки...&#10;Например:&#10;казино&#10;телеграм, тг, @scammer" class="resize-none font-mono text-sm"></x-ui.textarea>
                    <p class="text-xs text-muted-foreground">Система автоматически пропустит дубликаты.</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <x-ui.label for="modalCategory">Категория</x-ui.label>
                        <x-ui.select wire:key="m-cat" wire:model.live="modalCategory" id="modalCategory">
                            <x-ui.select-trigger class="w-50">
                                <x-ui.select-value />
                            </x-ui.select-trigger>
                            <x-ui.select-content>
                              @foreach(\App\Enums\StopWordCategory::options() as $value => $label)
                                    <x-ui.select-item wire:key="m-cat-opt-{{ $value }}" value="{{ $value }}">{{ $label }}</x-ui.select-item>
                                @endforeach
                            </x-ui.select-content>
                        </x-ui.select>
                    </div>

                    <div class="flex flex-col gap-2">
                        <x-ui.label for="modalAction">Поведение</x-ui.label>
                        <x-ui.select wire:key="m-act" wire:model.live="modalAction" id="modalAction">
                            <x-ui.select-trigger class="w-50">
                                <x-ui.select-value />
                            </x-ui.select-trigger>
                            <x-ui.select-content>
                           @foreach(\App\Enums\StopWordAction::options() as $value => $label)
                                <x-ui.select-item wire:key="m-act-opt-{{ $value }}" value="{{ $value }}">{{ $label }}</x-ui.select-item>
                            @endforeach
                            </x-ui.select-content>
                        </x-ui.select>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 p-4 border-t border-border bg-muted/20">
                <x-ui.button variant="outline" size="sm" @click="$wire.showAddModal = false">Отмена</x-ui.button>
                <x-ui.button wire:click="saveBulkWords" variant="default" size="sm" wire:loading.attr="disabled" wire:target="saveBulkWords">
                    <span wire:loading.remove wire:target="saveBulkWords" class="flex items-center gap-2"><x-lucide-save class="w-4 h-4" /> Добавить</span>
                    <span wire:loading wire:target="saveBulkWords" class="flex items-center gap-2"><x-lucide-loader-2 class="w-4 h-4 animate-spin inline" /> Сохранение...</span>
                </x-ui.button>
            </div>
        </div>
    </div>
</div>