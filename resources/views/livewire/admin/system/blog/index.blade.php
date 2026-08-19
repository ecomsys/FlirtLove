<?php

use App\Actions\Admin\BlogPostsAction;
use App\Models\BlogPost;
use App\Models\AdminLog;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\Session;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    #[Url(as: 'q', except: '')] 
    #[Session] 
    public string $search = '';
    #[Session] 
    public string $statusFilter = 'all';
    #[Session] 
    public int $perPage = 15;
    
    public array $selected = [];
    public bool $selectAll = false;
    public string $bulkAction = '';

    public function mount(): void
    {
        if (is_numeric($this->search) && $this->search !== '') {
            $this->statusFilter = 'all';
        }
    }

    public function setStatusFilter(string $status): void 
    { 
        $this->statusFilter = $status; 
        $this->search = ''; 
        $this->resetPage(); 
        $this->clearSelection(); // ФИКС: сбрасываем галки
    }

    public function deletePost(int $id, BlogPostsAction $action): void
    {       
        try {
            $action->delete(BlogPost::findOrFail($id));
            $this->dispatch('show-toast', type: 'success', message: 'Пост удален');
        } catch (\Exception $e) {
            Log::error("Ошибка удаления: " . $e->getMessage());
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера!');
        }
    }

    public function toggleStatus(int $id, BlogPostsAction $action): void
    {
        try {
            $action->toggle(BlogPost::findOrFail($id));
            $this->dispatch('show-toast', type: 'success', message: 'Статус обновлен');
        } catch (\Exception $e) {
            Log::error("Ошибка смены статуса: " . $e->getMessage());
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера!');
        }
    }

    public function archivePost(int $id, BlogPostsAction $action): void
    {
        try {
            $action->archive(BlogPost::findOrFail($id));
            $this->dispatch('show-toast', type: 'success', message: 'Пост перемещен в архив');
        } catch (\Exception $e) {
            Log::error("Ошибка архивации: " . $e->getMessage());
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера!');
        }
    }

    public function restorePost(int $id, BlogPostsAction $action): void
    {
        try {
            $action->restore(BlogPost::findOrFail($id));
            $this->dispatch('show-toast', type: 'success', message: 'Пост восстановлен');
        } catch (\Exception $e) {
            Log::error("Ошибка восстановления: " . $e->getMessage());
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера!');
        }
    }

    public function duplicatePost(int $id): void
    {
        try {
            $post = BlogPost::findOrFail($id);
            $new = $post->replicate();
            // ФИКС: Делаем красивый слаг без времени
            $new->slug = $post->slug . '-copy-' . Str::random(6);
            $new->title = $post->title . ' (Копия)';
            $new->status = 'draft'; 
            $new->is_featured = false;
            $new->views_count = 0;
            $new->save();
            
            AdminLog::record('blog.create', $new, auth()->user());
            $this->dispatch('show-toast', type: 'success', message: 'Пост продублирован');
        } catch (\Exception $e) {
            Log::error("Ошибка дублирования: " . $e->getMessage());
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера!');
        }
    }

    public function applyBulkAction(BlogPostsAction $action): void
    {
        if (empty($this->selected) || empty($this->bulkAction)) return;

        if ($this->bulkAction === 'delete' && $this->statusFilter !== 'archived') {
            $this->dispatch('show-toast', type: 'error', message: 'Удалять можно только из вкладки "В архиве"!');
            $this->bulkAction = '';
            return;
        }

        try {
            $message = $action->applyBulk($this->selected, $this->bulkAction, $this->statusFilter === 'archived');
            $this->dispatch('show-toast', type: 'success', message: $message);
        } catch (\Exception $e) {
            Log::error("Ошибка массового действия: " . $e->getMessage());
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка сервера!');
        }

        $this->clearSelection();
        $this->bulkAction = '';
    }

    public function updatedSearch(): void 
    { 
        $this->resetPage(); 
        $this->clearSelection(); // ФИКС: сбрасываем галки
    }
    
    public function updatedSelectAll($value): void
    {
        $this->selected = $value ? $this->posts->getCollection()->pluck('id')->map(fn($id) => (string) $id)->toArray() : [];
    }

    public function clearSelection(): void 
    { 
        $this->selected = []; 
        $this->selectAll = false; 
    }

    #[Computed]
    public function posts()
    {
        $searchOperator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

        return BlogPost::query()
            ->with(['category', 'cover']) 
            ->when($this->search, function ($query) use ($searchOperator) {
                $query->where('title', $searchOperator, "%{$this->search}%")
                      ->orWhere('slug', $searchOperator, "%{$this->search}%")
                      ->orWhereRaw("CAST(id AS TEXT) {$searchOperator} ?", ["%{$this->search}%"]);
            })
            ->when($this->statusFilter === 'uncategorized', fn($q) => $q->whereNull('category_id'))
            ->when(!in_array($this->statusFilter, ['all', 'uncategorized']), fn($q) => $q->where('status', $this->statusFilter))
            ->latest('created_at')
            ->latest('id')
            ->paginate(min(max($this->perPage, 1), 100));
    }

    #[Computed]
    public function counts(): array
    {
        $stats = BlogPost::query()
            ->selectRaw("COUNT(*) as total")
            ->selectRaw("SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published")
            ->selectRaw("SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft")
            ->selectRaw("SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived")
            ->selectRaw("SUM(CASE WHEN category_id IS NULL THEN 1 ELSE 0 END) as uncategorized")
            ->first();

        return [
            'all' => $stats->total ?? 0,
            'published' => $stats->published ?? 0,
            'draft' => $stats->draft ?? 0,
            'archived' => $stats->archived ?? 0,
            'uncategorized' => $stats->uncategorized ?? 0,
        ];
    }
}; 
?>

<div class="space-y-6 pb-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
         @php
            $previousUrl = url()->previous();
            $backUrl = ($previousUrl && $previousUrl !== url()->current()) 
                ? $previousUrl 
                : route('admin.dashboard');
        @endphp

        <div class="flex gap-2 items-center">
            <a href="{{ $backUrl }}" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            <h1 class="text-2xl font-semibold flex items-center gap-2">
                <x-lucide-newspaper class="w-6 h-6" />
                Записи блога
            </h1>
        </div>

        <a href="{{ route('admin.system.blog.create') }}" wire:navigate class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow hover:bg-primary/90 h-9 px-4 py-2">
            <x-lucide-plus class="w-4 h-4" />
            Написать пост
        </a>
    </div>

    <!-- ФИЛЬТРЫ -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-1.5">
            <x-ui.button wire:click="setStatusFilter('all')" variant="{{ $statusFilter === 'all' ? 'default' : 'secondary' }}" size="sm">
                Все <x-ui.badge size="xs">{{ $this->counts['all'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="setStatusFilter('published')" variant="{{ $statusFilter === 'published' ? 'default' : 'secondary' }}" size="sm">
                Опубликованные <x-ui.badge size="xs" variant="success">{{ $this->counts['published'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="setStatusFilter('draft')" variant="{{ $statusFilter === 'draft' ? 'default' : 'secondary' }}" size="sm">
                Черновики <x-ui.badge size="xs" variant="warning">{{ $this->counts['draft'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="setStatusFilter('archived')" variant="{{ $statusFilter === 'archived' ? 'default' : 'secondary' }}" size="sm">
                В архиве <x-ui.badge size="xs" variant="secondary">{{ $this->counts['archived'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="setStatusFilter('uncategorized')" variant="{{ $statusFilter === 'uncategorized' ? 'default' : 'outline' }}" size="sm">
                Без рубрики <x-ui.badge size="xs" variant="outline">{{ $this->counts['uncategorized'] }}</x-ui.badge>
            </x-ui.button>
        </div>

        <div class="relative w-64">
            <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск по названию или id..." class="pl-9 pr-8" />
            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            @if (!empty($search))
                <button wire:click="$set('search', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
                    <x-lucide-x class="w-4 h-4" />
                </button>
            @endif
        </div>
    </div>

    <!-- Панель массовых действий -->
    <div x-show="$wire.selected.length > 0" x-cloak x-transition class="bg-muted/30 border border-border rounded-lg p-3 flex flex-wrap items-center gap-3">
        <span class="text-sm font-medium">Выбрано: <span x-text="$wire.selected.length" class="text-primary"></span></span>
        <div class="flex-1"></div>
        
        <x-ui.select wire:model.live="bulkAction" class="min-w-48">
            <x-ui.select-trigger class="w-50">
                <x-ui.select-value placeholder="Выберите действие..." />
            </x-ui.select-trigger>
            <x-ui.select-content>
                <x-ui.select-item value="publish">Опубликовать</x-ui.select-item>
                <x-ui.select-item value="draft">В черновики</x-ui.select-item>
                <x-ui.select-item value="archive">В архив</x-ui.select-item>
                @if($statusFilter === 'archived')
                    <x-ui.select-item value="delete" class="text-destructive focus:text-destructive">Удалить навсегда</x-ui.select-item>
                @endif
            </x-ui.select-content>
        </x-ui.select>
        
        <x-ui.button wire:click="applyBulkAction" variant="default" size="sm" wire:confirm="Вы уверены?" wire:target="applyBulkAction" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="applyBulkAction" class="flex items-center gap-2"><x-lucide-check class="w-4 h-4 inline" /> <span>Применить</span></span>
            <span wire:loading wire:target="applyBulkAction" class="flex items-center gap-2"><x-lucide-loader-2 class="w-4 h-4 animate-spin inline" /> <span>Применяется...</span></span>
        </x-ui.button>
        <x-ui.button wire:click="clearSelection" variant="ghost" size="sm"><x-lucide-x class="w-4 h-4" /> Снять выделение</x-ui.button>
    </div>

    <!-- Таблица -->
    <x-ui.table>
        <x-ui.table-header>
            <x-ui.table-row>
                <x-ui.table-head class="w-10"><x-checkbox wire:model.live="selectAll" /></x-ui.table-head>
                <x-ui.table-head class="w-10">ID</x-ui.table-head>
                <x-ui.table-head class="w-[5.5rem]">Обложка</x-ui.table-head>
                <x-ui.table-head>Заголовок</x-ui.table-head>
                <x-ui.table-head>Рубрика</x-ui.table-head>
                <x-ui.table-head>Статус</x-ui.table-head>
                <x-ui.table-head>Создан</x-ui.table-head>
                <x-ui.table-head class="text-right">Действия</x-ui.table-head>
            </x-ui.table-row>
        </x-ui.table-header>

           <x-ui.table-body>
            @forelse ($this->posts as $post)
                @php 
                    // ФИКС: Строгое равенство, чтобы не подсвечивало 15 при поиске 5
                    $isHighlighted = is_numeric($this->search) && $post->id === (int)$this->search; 
                @endphp

                <x-ui.table-row 
                    wire:key="post-{{ $post->id }}-status-{{ $post->status }}" 
                    class="table-row-animate {{ $isHighlighted ? 'bg-primary/10 ring-2 ring-primary/50 transition-all duration-500' : '' }} {{ in_array((string)$post->id, $this->selected) ? 'bg-muted/30' : '' }}"
                    x-data="{ isHi: {{ $isHighlighted ? 'true' : 'false' }} }"
                    x-init="isHi && $nextTick(() => { $el.scrollIntoView({ behavior: 'smooth', block: 'center' }) })"
                >
                    <x-ui.table-cell>
                        <x-checkbox wire:model.live="selected" value="{{ $post->id }}" />
                    </x-ui.table-cell>
                    
                    <!-- ID: Подсвечивается синим, если это искомый ID -->
                    <x-ui.table-cell class="text-xs font-mono whitespace-nowrap {{ $isHighlighted ? 'text-primary font-bold' : 'text-muted-foreground' }}">
                        #{{ $post->id }}
                    </x-ui.table-cell>
                    
                    <x-ui.table-cell>
                        @if($post->cover)
                            <div class="w-16 h-9 rounded-sm overflow-hidden bg-muted">
                                <img src="{{ $post->cover->getVariantUrl('thumb') }}" alt="Cover" class="w-full h-full object-cover" />
                            </div>
                        @else
                            <div class="w-16 h-9 rounded-md bg-muted flex items-center justify-center">
                                <x-lucide-image class="w-4 h-4 text-muted-foreground" />
                            </div>
                        @endif
                    </x-ui.table-cell>

                   <x-ui.table-cell>
                        <div class="max-w-[12rem] md:max-w-[22rem]">
                            <a href="{{ route('admin.system.blog.edit', $post) }}" wire:navigate class="block truncate font-medium text-sm hover:text-primary hover:underline">
                                {{ $post->title }}
                            </a>
                            @if($post->excerpt)
                                <div class="truncate text-xs text-muted-foreground mt-0.5">{{ $post->excerpt }}</div>
                            @endif
                        </div>
                    </x-ui.table-cell>
                    
                    <x-ui.table-cell>
                        @if($post->category)
                            <x-ui.badge variant="outline" size="sm">{{ $post->category->name }}</x-ui.badge>
                        @else
                            <span class="text-xs text-muted-foreground italic">Без рубрики</span>
                        @endif
                    </x-ui.table-cell>
                    
                    <x-ui.table-cell>
                        <button wire:click="toggleStatus({{ $post->id }})" class="cursor-pointer">
                            <x-ui.badge variant="{{ $post->statusBadge['variant'] }}" size="sm">{{ $post->statusBadge['label'] }}</x-ui.badge>
                        </button>
                    </x-ui.table-cell>
                    
                    <x-ui.table-cell class="text-muted-foreground text-xs whitespace-nowrap">
                        {{ $post->created_at->format('d.m.Y H:i') }}
                    </x-ui.table-cell>
                    
                    <x-ui.table-cell class="text-right">
                        <x-ui.dropdown-menu>
                            <x-ui.dropdown-menu-trigger>
                                <x-ui.button variant="ghost" size="icon-sm"><x-lucide-more-horizontal class="w-4 h-4" /></x-ui.button>
                            </x-ui.dropdown-menu-trigger>
                            <x-ui.dropdown-menu-content align="end">
                                <a href="{{ route('admin.system.blog.edit', $post) }}" wire:navigate class="flex items-center gap-2 cursor-pointer select-none rounded-sm px-2 py-1.5 text-sm outline-none transition-colors hover:bg-accent hover:text-accent-foreground">
                                    <x-lucide-pencil class="w-4 h-4" /> Редактировать
                                </a>

                                <x-ui.dropdown-menu-item wire:click="duplicatePost({{ $post->id }})">
                                    <x-lucide-copy class="w-4 h-4" /> Дублировать
                                </x-ui.dropdown-menu-item>

                                <x-ui.dropdown-menu-separator />

                                @if($post->status === 'draft')
                                    <x-ui.dropdown-menu-item wire:click="toggleStatus({{ $post->id }})">
                                        <x-lucide-send class="w-4 h-4" /> Опубликовать
                                    </x-ui.dropdown-menu-item>
                                @endif

                                @if($post->status === 'published')
                                    <x-ui.dropdown-menu-item wire:click="toggleStatus({{ $post->id }})">
                                        <x-lucide-file-edit class="w-4 h-4" /> В черновики
                                    </x-ui.dropdown-menu-item>
                                @endif

                                @if($post->status === 'archived')
                                    <x-ui.dropdown-menu-item wire:click="restorePost({{ $post->id }})">
                                        <x-lucide-arrow-up-from-line class="w-4 h-4" /> Восстановить
                                    </x-ui.dropdown-menu-item>
                                @else
                                    <x-ui.dropdown-menu-item wire:click="archivePost({{ $post->id }})">
                                        <x-lucide-archive class="w-4 h-4" /> В архив
                                    </x-ui.dropdown-menu-item>
                                @endif

                                @if($post->status === 'archived')
                                    <x-ui.dropdown-menu-separator />
                                    <x-ui.dropdown-menu-item variant="destructive" wire:click="deletePost({{ $post->id }})" wire:confirm="Удалить этот пост навсегда?">
                                        <x-lucide-trash-2 class="w-4 h-4" /> Удалить навсегда
                                    </x-ui.dropdown-menu-item>
                                @endif
                            </x-ui.dropdown-menu-content>
                        </x-ui.dropdown-menu>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @empty
                <x-ui.table-row wire:key="empty-state">
                    <x-ui.table-cell colspan="8" class="py-12 text-center text-muted-foreground bg-card">
                        <x-ui.empty>
                            <x-ui.empty-header>
                                <x-ui.empty-media variant="icon">
                                    <x-lucide-newspaper class="w-12 h-12 opacity-30" />
                                </x-ui.empty-media>
                                <x-ui.empty-title>Посты не найдены</x-ui.empty-title>       
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
            Показано {{ $this->posts->firstItem() ?? 0 }} - {{ $this->posts->lastItem() ?? 0 }} из {{ $this->posts->total() }}
        </div>
        {{ $this->posts->links('partials.pagination') }}
    </div>
</div>