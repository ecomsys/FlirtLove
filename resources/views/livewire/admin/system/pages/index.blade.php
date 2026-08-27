<?php

use App\Models\AdminLog;
use App\Models\Page;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    /** @var string Поисковый запрос */
    #[Url(as: 'q', except: '')]
    public string $search = '';
    
    /** @var string Текущий фильтр статуса */
    #[Url(as: 'status', except: 'all')]
    public string $statusFilter = 'all';
    
    /** @var int Количество элементов на странице */
    public int $perPage = 15;

    /** @var string URL для кнопки "Назад" */
    public string $backUrl = '';
    
    /** @var array Массив выбранных ID страниц для массовых действий */
    public array $selected = [];
    
    /** @var bool Состояние чекбокса "Выбрать всё" на текущей странице */
    public bool $selectAll = false;
    
    /** @var string Выбранное массовое действие (delete, activate, draft) */
    public string $bulkAction = '';

    /**
     * Удаление одной страницы по ID.
     * Логирует действие в админ-лог и системный лог.
     *
     * @param int $id ID страницы
     * @return void
     */
    public function deletePage(int $id): void
    {
        try {
            $page = Page::findOrFail($id);
            AdminLog::record('page.delete', $page, auth()->user());
            $page->delete();
            
            Log::info("Админ удалил страницу", ['page_id' => $id, 'admin_id' => auth()->id()]);
            $this->dispatch('show-toast', type: 'success', message: 'Страница удалена');
            $this->clearComputedCache();
        } catch (\Exception $e) {
            Log::error("Ошибка при удалении страницы: " . $e->getMessage(), ['page_id' => $id]);
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера при удалении!');
        }
    }

    /**
     * Переключение статуса публикации страницы (Опубликована / Черновик).
     *
     * @param int $id ID страницы
     * @return void
     */
    public function toggleStatus(int $id): void
    {
        try {
            $page = Page::findOrFail($id);
            $oldStatus = $page->is_active;
            
            $page->update(['is_active' => !$oldStatus]);
            
            AdminLog::record('page.update', $page, auth()->user(), ['is_active' => $oldStatus], ['is_active' => !$oldStatus]);
            
            Log::info("Админ изменил статус страницы", [
                'page_id' => $id, 
                'old_status' => $oldStatus, 
                'new_status' => !$oldStatus, 
                'admin_id' => auth()->id()
            ]);
            
            $this->dispatch('show-toast', 
                type: 'success', 
                message: !$oldStatus ? 'Страница опубликована' : 'Страница снята с публикации'
            );
            $this->clearComputedCache();
        } catch (\Exception $e) {
            Log::error("Ошибка при смене статуса страницы: " . $e->getMessage(), ['page_id' => $id]);
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера при смене статуса!');
        }
    }

    /**
     * Дублирование страницы.
     * Создает копию с уникальным слагом и статусом "Черновик".
     *
     * @param int $id ID исходной страницы
     * @return void
     */
    public function duplicatePage(int $id): void
    {
        try {
            $page = Page::findOrFail($id);
            $new = $page->replicate();
            $new->slug = $page->slug . '-copy-' . time();
            $new->title = $page->title . ' (Копия)';
            $new->is_active = false; 
            $new->save();

            AdminLog::record('page.create', $new, auth()->user());
            
            Log::info("Админ продублировал страницу", [
                'source_page_id' => $id, 
                'new_page_id' => $new->id, 
                'admin_id' => auth()->id()
            ]);

            $this->dispatch('show-toast', type: 'success', message: 'Страница продублирована');
            $this->clearComputedCache();
        } catch (\Exception $e) {
            Log::error("Ошибка при дублировании страницы: " . $e->getMessage(), ['page_id' => $id]);
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера при дублировании!');
        }
    }

  
       public function mount(): void
    {
        // ФИКС: Запоминаем URL "Назад" только при первой загрузке
        $previousUrl = url()->previous();
        $this->backUrl = ($previousUrl && $previousUrl !== url()->current()) 
            ? $previousUrl 
            : route('admin.dashboard');
    }

      /**
     * Установка фильтра статуса и сброс пагинации.
     *
     * @param string $status Статус фильтрации (all, active, draft)
     * @return void
     */
    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->search = ''; // ФИКС: Очищаем поиск при смене вкладки
        $this->resetPage();
        $this->clearComputedCache();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->clearComputedCache();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
        $this->clearComputedCache();
    }

    private function clearComputedCache(): void
    {
        unset($this->pages);
        unset($this->counts);
    }

    /**
     * Обработка чекбокса "Выбрать всё".
     * Извлекает коллекцию из пагинатора для корректного получения ID текущей страницы.
     *
     * @param mixed $value Значение чекбокса (true/false)
     * @return void
     */
    public function updatedSelectAll($value): void
    {
        if ($value) {
            $this->selected = $this->pages->getCollection()->pluck('id')->map(fn($id) => (string) $id)->toArray();
        } else {
            $this->selected = [];
        }
    }

    /**
     * Очистка выбранных элементов и сброс чекбокса "Выбрать всё".
     *
     * @return void
     */
    public function clearSelection(): void
    {
        $this->selected = [];
        $this->selectAll = false;
    }

    /**
     * Применение массового действия к выбранным страницам.
     * Поддерживает удаление, публикацию и снятие с публикации.
     *
     * @return void
     */
    public function applyBulkAction(): void
    {
        if (empty($this->selected) || empty($this->bulkAction)) {
            return;
        }

        $pages = Page::whereIn('id', $this->selected)->get();
        $admin = auth()->user();
        $message = '';

        try {
            foreach ($pages as $page) {
                if ($this->bulkAction === 'delete') {
                    AdminLog::record('page.delete', $page, $admin);
                    $page->delete();
                    Log::info("Массовое удаление: страница #{$page->id} удалена", ['admin_id' => $admin->id]);
                    $message = 'Страницы удалены';
                } elseif ($this->bulkAction === 'activate') {
                    if (!$page->is_active) {
                        $page->update(['is_active' => true]);
                        AdminLog::record('page.update', $page, $admin, ['is_active' => false], ['is_active' => true]);
                        Log::info("Массовая активация: страница #{$page->id} опубликована", ['admin_id' => $admin->id]);
                    }
                    $message = 'Выбранные страницы опубликованы';
                } elseif ($this->bulkAction === 'draft') {
                    if ($page->is_active) {
                        $page->update(['is_active' => false]);
                        AdminLog::record('page.update', $page, $admin, ['is_active' => true], ['is_active' => false]);
                        Log::info("Массовое снятие: страница #{$page->id} снята с публикации", ['admin_id' => $admin->id]);
                    }
                    $message = 'Выбранные страницы сняты с публикации';
                }
            }

            $this->dispatch('show-toast', type: 'success', message: $message);
            $this->clearComputedCache();
        } catch (\Exception $e) {
            Log::error("Ошибка при массовом действии со страницами: " . $e->getMessage(), [
                'selected_ids' => $this->selected,
                'action' => $this->bulkAction
            ]);
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера при выполнении действия!');
        }

        $this->clearSelection();
        $this->bulkAction = '';
    }

    /**
     * Получение списка страниц с фильтрацией и пагинацией.
     * Внимание: Жесткое ограничение perPage (max 100) для защиты от DoS-атак.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    #[Computed]
    public function pages()
    {
        $perPage = min(max($this->perPage, 1), 100);

         $searchOperator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

        return Page::query()
            ->when($this->search, function ($query) use ($searchOperator) {
                $query->where(function ($q) use ($searchOperator) {
                    $q->where('title', $searchOperator, "%{$this->search}%")
                      ->orWhere('slug', $searchOperator, "%{$this->search}%");
                    if (is_numeric($this->search)) {
                        $q->orWhere('id', (int) $this->search);
                    }
                });
            })
            ->when($this->statusFilter === 'active', fn($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'draft', fn($q) => $q->where('is_active', false))
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Подсчет количества страниц для вкладок фильтра.
     * Оптимизация: 1 запрос в БД вместо 3-х с использованием условной агрегации.
     *
     * @return array
     */
    #[Computed]
    public function counts(): array
    {
        $stats = Page::query()
            ->selectRaw("COUNT(*) as total")
            ->selectRaw("SUM(CASE WHEN is_active = true THEN 1 ELSE 0 END) as active")
            ->first();

        $total = $stats->total ?? 0;
        $active = $stats->active ?? 0;

        return [
            'all' => $total,
            'active' => $active,
            'draft' => $total - $active,
        ];
    }
}; 
?>


<div class="space-y-6 pb-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-4">
            <a href="{{ $backUrl }}" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            <h1 class="text-2xl font-semibold flex items-center gap-2">
                <x-lucide-file-text class="w-6 h-6" />
                Страницы
            </h1>
        </div>

        <a href="{{ route('admin.system.pages.create') }}" wire:navigate class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow hover:bg-primary/90 h-9 px-4 py-2">
            <x-lucide-plus class="w-4 h-4" />
            Создать страницу
        </a>
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
            <x-ui.input wire:key="pages-search-input" wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск по названию или id..." class="pl-9 pr-8" />
            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            @if (!empty($search))
                <button wire:click="clearSearch" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground z-10">
                    <x-lucide-x class="w-4 h-4" />
                </button>
            @endif
        </div>
    </div>

    <!-- Панель массовых действий -->
    <div x-show="$wire.selected.length > 0" x-cloak x-transition class="bg-muted/30 border border-border rounded-lg p-3 flex flex-wrap items-center gap-3">
        <span class="text-sm font-medium">
            Выбрано: <span x-text="$wire.selected.length" class="text-primary"></span>
        </span>
        
        <div class="flex-1"></div>
        
        <x-ui.select wire:model.live="bulkAction" class="min-w-48">
            <x-ui.select-trigger><x-ui.select-value placeholder="Выберите действие..." /></x-ui.select-trigger>
            <x-ui.select-content>
                <x-ui.select-item value="activate">Опубликовать</x-ui.select-item>
                <x-ui.select-item value="draft">Снять с публикации</x-ui.select-item>
                <x-ui.select-item value="delete">Удалить</x-ui.select-item>
            </x-ui.select-content>
        </x-ui.select>
        
      <x-ui.button wire:click="applyBulkAction" variant="default" size="sm" wire:confirm="Вы уверены, что хотите применить это действие к выбранным страницам?" wire:target="applyBulkAction" wire:loading.attr="disabled">
        <!-- Состояние по умолчанию (скрывается при загрузке) -->
        <span wire:loading.remove wire:target="applyBulkAction" class="flex items-center gap-2">
            <x-lucide-check class="w-4 h-4 inline" /> 
            <span>Применить</span>
        </span>
        <!-- Состояние загрузки (появляется при applyBulkAction) -->
        <span wire:loading wire:target="applyBulkAction" class="flex items-center gap-2">
            <x-lucide-loader-2 class="w-4 h-4 animate-spin  inline" /> 
            <span>Применяется...</span>            
        </span>
    </x-ui.button>

        <x-ui.button wire:click="clearSelection" variant="ghost" size="sm">
            <x-lucide-x class="w-4 h-4" /> Снять выделение
        </x-ui.button>
    </div>

    <!-- Таблица -->
    <x-ui.table>
        <x-ui.table-header>
            <x-ui.table-row>
                <x-ui.table-head class="w-10">
                    <x-checkbox wire:model.live="selectAll" />
                </x-ui.table-head>
                <x-ui.table-head class="w-10">ID</x-ui.table-head>
                <x-ui.table-head>Заголовок</x-ui.table-head>
                <x-ui.table-head>URL (Slug)</x-ui.table-head>
                <x-ui.table-head>Статус</x-ui.table-head>
                <x-ui.table-head>Дата обновления</x-ui.table-head>
                <x-ui.table-head class="text-right">Действия</x-ui.table-head>
            </x-ui.table-row>
        </x-ui.table-header>

        <x-ui.table-body>
            @forelse ($this->pages as $page)
                @php 
                    $isHighlighted = is_numeric($this->search) && $page->id == (int)$this->search; 
                @endphp
                <x-ui.table-row 
                    wire:key="page-{{ $page->id }}" 
                    class="{{ in_array((string)$page->id, $this->selected) ? 'bg-muted/30' : '' }} {{ $isHighlighted ? 'bg-blue-500/10 ring-2 ring-blue-500/50' : '' }}"
                    x-data="{ isHi: {{ $isHighlighted ? 'true' : 'false' }} }"
                    x-init="isHi && setTimeout(() => { $el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 200)"
                >
                    <x-ui.table-cell>
                        <x-checkbox wire:model.live="selected" value="{{ $page->id }}" />
                    </x-ui.table-cell>
                    <x-ui.table-cell class="text-xs font-mono whitespace-nowrap {{ $isHighlighted ? 'text-blue-500 font-bold' : 'text-muted-foreground' }}">
                        #{{ $page->id }}
                    </x-ui.table-cell>
                   <x-ui.table-cell>
                    <div class="max-w-[12rem] md:max-w-[22rem]">
                        <a href="{{ route('admin.system.pages.edit', $page) }}" wire:navigate class="block truncate font-medium text-sm hover:text-primary hover:underline">
                            {{ $page->title }}
                        </a>
                        @if($page->meta_description)
                            <div class="truncate text-xs text-muted-foreground mt-0.5">{{ $page->meta_description }}</div>
                        @endif
                    </div>
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
                                <a href="{{ route('admin.system.pages.edit', $page) }}" wire:navigate class="flex items-center gap-2 cursor-pointer select-none rounded-sm px-2 py-1.5 text-sm outline-none transition-colors hover:bg-accent hover:text-accent-foreground">
                                    <x-lucide-pencil class="w-4 h-4" /> Редактировать
                                </a>
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
                <x-ui.table-row wire:key="empty-state">
                    <x-ui.table-cell colspan="7" class="py-12 text-center text-muted-foreground bg-card">
                        <x-ui.empty>
                            <x-ui.empty-header>
                                <x-ui.empty-media variant="icon">
                                    <x-lucide-file-x class="w-12 h-12 opacity-30" />
                                </x-ui.empty-media>
                                <x-ui.empty-title>Страницы не найдены</x-ui.empty-title>       
                            </x-ui.empty-header>    
                        </x-ui.empty>                        
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
</div>