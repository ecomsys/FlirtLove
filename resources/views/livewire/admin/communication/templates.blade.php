<?php

use App\Models\AdminLog;
use App\Models\SupportTemplate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin')] class extends Component 
{
    public ?int $templateId = null;
    public string $category = '';
    public string $title = '';
    public string $body = '';
    public bool $is_active = true;
    public int $sort_order = 0;
    
    public bool $showFormModal = false;
    public string $search = '';

    public function mount(): void
    {
        // По умолчанию новая категория при открытии формы
        $this->category = 'Общие';
    }

    #[Computed]
    public function templates()
    {
        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
        $search = '%' . $this->search . '%';

        return SupportTemplate::query()
            ->when($this->search, fn($q) => $q->where('title', $operator, $search)->orWhere('category', $operator, $search))
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get();
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

    public function save(): void
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
            $before = $template->getOriginal();
            $template->update($data);
            AdminLog::record('template.update', $template, auth()->user(), $before, $data);
            $this->dispatch('show-toast', type: 'success', message: 'Шаблон обновлен!');
        } else {
            $template = SupportTemplate::create($data);
            AdminLog::record('template.create', $template, auth()->user());
            $this->dispatch('show-toast', type: 'success', message: 'Шаблон создан!');
        }

        $this->showFormModal = false;
        unset($this->templates); // Сбрасываем кэш списка
    }

    public function deleteTemplate(int $id): void
    {
        $template = SupportTemplate::find($id);
        if ($template) {
            AdminLog::record('template.delete', $template, auth()->user());
            $template->delete();
            $this->dispatch('show-toast', type: 'warning', message: 'Шаблон удален.');
            unset($this->templates);
        }
    }
}; 
?>

<div class="space-y-6">
    <!-- Заголовок -->    
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.dashboard') }}" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-semibold flex items-center gap-2">
                    <x-lucide-file-text class="w-6 h-6" />
                    Шаблоны поддержки
                </h1>
                <p class="text-sm text-muted-foreground">Управление заготовками для чата поддержки</p>
            </div>
        </div>       
    
<div class="flex items-center justify-between flex-wrap gap-4">
    <!-- Поиск -->
    <div class="relative w-full max-w-md">
        <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground z-10" />
        <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск по названию или категории..." class="pl-9" />
    </div>

     <x-ui.button wire:click="openCreateModal" variant="default" size="sm">
            <x-lucide-plus class="w-4 h-4" />
            Создать шаблон
        </x-ui.button>
</div>
    <!-- Таблица шаблонов -->
    <div class="bg-card border border-border rounded-lg overflow-hidden">
        <x-ui.table>
            <x-ui.table-header>
                <x-ui.table-row>
                    <x-ui.table-head class="w-32">Категория</x-ui.table-head>
                    <x-ui.table-head>Название</x-ui.table-head>
                    <x-ui.table-head class="hidden md:table-cell">Текст (превью)</x-ui.table-head>
                    <x-ui.table-head class="w-24 text-center">Статус</x-ui.table-head>
                    <x-ui.table-head class="w-16 text-right">Действия</x-ui.table-head>
                </x-ui.table-row>
            </x-ui.table-header>
            <x-ui.table-body>
                @forelse ($this->templates as $template)
                    <x-ui.table-row wire:key="tpl-{{ $template->id }}">
                        <x-ui.table-cell class="font-medium text-sm">
                            <x-ui.badge variant="secondary" size="xs">{{ $template->category }}</x-ui.badge>
                        </x-ui.table-cell>
                        <x-ui.table-cell class="font-medium text-sm">{{ $template->title }}</x-ui.table-cell>
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
                    <x-ui.table-row>
                        <x-ui.table-cell colspan="5" class="py-12 text-center text-muted-foreground">
                            Шаблонов не найдено. Нажмите "Создать шаблон", чтобы добавить первый.
                        </x-ui.table-cell>
                    </x-ui.table-row>
                @endforelse
            </x-ui.table-body>
        </x-ui.table>
    </div>

    <!-- Модалка создания/редактирования -->
    @if ($showFormModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" wire:click.self="$set('showFormModal', false)">
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

                <div class="flex items-center gap-6 pt-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="is_active" class="rounded border-border text-primary focus:ring-primary" />
                        <span class="text-sm">Активен (виден в чате)</span>
                    </label>
                    
                    <div class="flex items-center gap-2">
                        <x-ui.label for="sort_order" class="text-xs m-0">Порядок:</x-ui.label>
                        <input type="number" wire:model="sort_order" class="w-16 rounded-md border border-border bg-card px-2 py-1 text-sm" />
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 p-4 border-t border-border bg-muted/20 shrink-0">
                <x-ui.button variant="outline" size="sm" wire:click="$set('showFormModal', false)">Отмена</x-ui.button>
                <x-ui.button wire:click="save" variant="default" size="sm" wire:loading.attr="disabled" wire:target="save">
                    <x-lucide-save class="w-4 h-4" wire:loading.remove wire:target="save" />
                    <x-lucide-loader-2 class="w-4 h-4 animate-spin hidden" wire:loading wire:target="save" />
                    Сохранить
                </x-ui.button>
            </div>
        </div>
    </div>
    @endif
</div>