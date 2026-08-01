<?php

use App\Models\AdminLog;
use App\Models\StopWord;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    public string $search = '';
    public string $categoryFilter = 'all';
    public string $actionFilter = 'all';
    public string $statusFilter = 'all';
    public int $perPage = 30;

    // Поля формы
    public bool $showModal = false;
    public ?int $editingId = null;
    
    public string $modalWord = '';
    public string $modalCategory = 'mat';
    public string $modalAction = 'mask';
    public string $modalReplacement = '***';
    public bool $modalIsActive = true;

    // Список категорий для UI
    private array $categoriesList = [
        'mat' => 'Мат',
        'scam' => 'Мошенничество',
        'prostitution' => 'Проституция',
        'drugs' => 'Наркотики',
        'contacts' => 'Контакты/ТГ',
        'other' => 'Другое',
    ];

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedCategoryFilter(): void { $this->resetPage(); }
    public function updatedActionFilter(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }

    // Если меняем действие в форме, скрываем/показываем замену
    public function updatedModalAction(string $value): void
    {
        if ($value !== 'mask') {
            $this->modalReplacement = '';
        } else {
            $this->modalReplacement = '***';
        }
    }

    public function createModal(): void
    {
        $this->reset(['modalWord', 'modalCategory', 'modalAction', 'modalReplacement', 'modalIsActive', 'editingId']);
        $this->modalIsActive = true;
        $this->modalAction = 'mask';
        $this->modalReplacement = '***';
        $this->resetValidation();
        $this->showModal = true;
    }

    public function editModal(int $id): void
    {
        $word = StopWord::find($id);
        if (!$word) return;

        $this->editingId = $id;
        $this->modalWord = $word->word;
        $this->modalCategory = $word->category;
        $this->modalAction = $word->action;
        $this->modalReplacement = $word->replacement;
        $this->modalIsActive = $word->is_active;
        
        $this->resetValidation();
        $this->showModal = true;
    }

    public function saveWord(): void
    {
        $this->validate($this->rules());

        $word = StopWord::create([
            'word' => $this->modalWord,
            'category' => $this->modalCategory,
            'action' => $this->modalAction,
            'replacement' => $this->modalAction === 'mask' ? $this->modalReplacement : null,
            'is_active' => $this->modalIsActive,
        ]);

        AdminLog::record('stopword.create', $word, auth()->user());
        Cache::forget('stop_words_all'); // Сбрасываем кэш

        $this->dispatch('show-toast', type: 'success', message: 'Стоп-слово добавлено!');
        $this->showModal = false;
    }

    public function updateWord(): void
    {
        $this->validate($this->rules());

        $word = StopWord::find($this->editingId);
        if (!$word) return;

        $word->update([
            'word' => $this->modalWord,
            'category' => $this->modalCategory,
            'action' => $this->modalAction,
            'replacement' => $this->modalAction === 'mask' ? $this->modalReplacement : null,
            'is_active' => $this->modalIsActive,
        ]);

        AdminLog::record('stopword.update', $word, auth()->user());
        Cache::forget('stop_words_all');

        $this->dispatch('show-toast', type: 'success', message: 'Стоп-слово обновлено!');
        $this->showModal = false;
    }

    public function deleteWord(int $id): void
    {
        $word = StopWord::find($id);
        if ($word) {
            AdminLog::record('stopword.delete', $word, auth()->user());
            $word->delete();
            Cache::forget('stop_words_all');
            $this->dispatch('show-toast', type: 'success', message: 'Стоп-слово удалено');
        }
    }

    public function toggleStatus(int $id): void
    {
        $word = StopWord::find($id);
        if ($word) {
            $word->update(['is_active' => !$word->is_active]);
            AdminLog::record('stopword.update', $word, auth()->user());
            Cache::forget('stop_words_all');
        }
    }

    public function clearCache(): void
    {
        Cache::forget('stop_words_all');
        $this->dispatch('show-toast', type: 'success', message: 'Кэш стоп-слов сброшен!');
    }

    protected function rules(): array
    {
        $wordRule = 'required|string|max:255|unique:stop_words,word';
        if ($this->editingId) {
            $wordRule .= ',' . $this->editingId;
        }

        return [
            'modalWord' => $wordRule,
            'modalCategory' => 'required|string',
            'modalAction' => 'required|in:mask,reject,alert',
            'modalReplacement' => 'nullable|string|max:10',
            'modalIsActive' => 'boolean',
        ];
    }

    #[Computed]
    public function stopWords()
    {
        return StopWord::query()
            ->when($this->search, fn($q) => $q->where('word', 'like', "%{$this->search}%"))
            ->when($this->categoryFilter !== 'all', fn($q) => $q->where('category', $this->categoryFilter))
            ->when($this->actionFilter !== 'all', fn($q) => $q->where('action', $this->actionFilter))
            ->when($this->statusFilter === 'active', fn($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn($q) => $q->where('is_active', false))
            ->latest()
            ->paginate($this->perPage);
    }

    #[Computed]
    public function counts(): array
    {
        return [
            'total' => StopWord::count(),
            'active' => StopWord::where('is_active', true)->count(),
            'inactive' => StopWord::where('is_active', false)->count(),
        ];
    }
}; 
?>

<div class="space-y-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <h1 class="text-2xl font-semibold flex items-center gap-2">
            <x-lucide-shield-alert class="w-6 h-6" />
            Стоп-слова и фильтры
        </h1>

        <div class="flex items-center gap-2">
            <x-ui.button wire:click="clearCache" variant="outline" size="sm">
                <x-lucide-database class="w-4 h-4" />
                Сбросить кэш
            </x-ui.button>

            <x-ui.button wire:click="createModal" variant="default" size="sm">
                <x-lucide-plus class="w-4 h-4" />
                Добавить слово
            </x-ui.button>
        </div>
    </div>

    <!-- Фильтры -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-1.5">
            <x-ui.button wire:click="$set('statusFilter', 'all')" variant="{{ $statusFilter === 'all' ? 'default' : 'secondary' }}" size="sm">
                Все <x-ui.badge size="xs">{{ $this->counts['total'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="$set('statusFilter', 'active')" variant="{{ $statusFilter === 'active' ? 'default' : 'secondary' }}" size="sm">
                Активные <x-ui.badge size="xs" variant="success">{{ $this->counts['active'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:click="$set('statusFilter', 'inactive')" variant="{{ $statusFilter === 'inactive' ? 'default' : 'secondary' }}" size="sm">
                Выключенные <x-ui.badge size="xs" variant="warning">{{ $this->counts['inactive'] }}</x-ui.badge>
            </x-ui.button>
        </div>

        <div class="flex items-center gap-2">
            <x-ui.select wire:model.live="categoryFilter" class="min-w-40">
                <x-ui.select-trigger><x-ui.select-value placeholder="Категория" /></x-ui.select-trigger>
                <x-ui.select-content>
                    <x-ui.select-item value="all">Все категории</x-ui.select-item>
                    @foreach($this->categoriesList as $key => $cat)
                        <x-ui.select-item value="{{ $key }}">{{ $cat }}</x-ui.select-item>
                    @endforeach
                </x-ui.select-content>
            </x-ui.select>

            <x-ui.select wire:model.live="actionFilter" class="min-w-40">
                <x-ui.select-trigger><x-ui.select-value placeholder="Действие" /></x-ui.select-trigger>
                <x-ui.select-content>
                    <x-ui.select-item value="all">Все действия</x-ui.select-item>
                    <x-ui.select-item value="mask">Маскировать</x-ui.select-item>
                    <x-ui.select-item value="reject">Блокировать</x-ui.select-item>
                    <x-ui.select-item value="alert">Тревога (Антифрод)</x-ui.select-item>
                </x-ui.select-content>
            </x-ui.select>

            <div class="relative w-64">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск слова..." class="pl-9 pr-8" />
                <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            </div>
        </div>
    </div>

    <!-- Таблица -->
    <x-ui.table>
        <x-ui.table-header>
            <x-ui.table-row>
                <x-ui.table-head>Слово / Фраза</x-ui.table-head>
                <x-ui.table-head>Категория</x-ui.table-head>
                <x-ui.table-head>Действие системы</x-ui.table-head>
                <x-ui.table-head>Замена</x-ui.table-head>
                <x-ui.table-head>Статус</x-ui.table-head>
                <x-ui.table-head class="text-right">Действия</x-ui.table-row>
            </x-ui.table-row>
        </x-ui.table-header>

        <x-ui.table-body>
            @forelse ($this->stopWords as $word)
                <x-ui.table-row wire:key="stopword-{{ $word->id }}" class="{{ !$word->is_active ? 'opacity-50' : '' }}">
                    <x-ui.table-cell>
                        <div class="font-medium text-sm">{{ $word->word }}</div>
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        <x-ui.badge variant="outline" size="sm">
                            {{ $this->categoriesList[$word->category] ?? $word->category }}
                        </x-ui.badge>
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        <x-ui.badge variant="{{ $word->action_badge['variant'] }}" size="sm">
                            {{ $word->action_badge['label'] }}
                        </x-ui.badge>
                    </x-ui.table-cell>
                    <x-ui.table-cell class="text-sm text-muted-foreground font-mono">
                        {{ $word->replacement ?? '—' }}
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        <button wire:click="toggleStatus({{ $word->id }})" class="cursor-pointer">
                            @if($word->is_active)
                                <x-ui.badge variant="success" size="sm">Вкл</x-ui.badge>
                            @else
                                <x-ui.badge variant="secondary" size="sm">Выкл</x-ui.badge>
                            @endif
                        </button>
                    </x-ui.table-cell>
                    <x-ui.table-cell class="text-right">
                        <x-ui.dropdown-menu>
                            <x-ui.dropdown-menu-trigger>
                                <x-ui.button variant="ghost" size="icon-sm"><x-lucide-more-horizontal class="w-4 h-4" /></x-ui.button>
                            </x-ui.dropdown-menu-trigger>
                            <x-ui.dropdown-menu-content align="end">
                                <x-ui.dropdown-menu-item wire:click="editModal({{ $word->id }})">
                                    <x-lucide-pencil class="w-4 h-4" /> Редактировать
                                </x-ui.dropdown-menu-item>
                                <x-ui.dropdown-menu-item variant="destructive" wire:click="deleteWord({{ $word->id }})" wire:confirm="Удалить это слово?">
                                    <x-lucide-trash-2 class="w-4 h-4" /> Удалить
                                </x-ui.dropdown-menu-item>
                            </x-ui.dropdown-menu-content>
                        </x-ui.dropdown-menu>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @empty
                <x-ui.table-row>
                    <x-ui.table-cell colspan="6" class="py-12 text-center text-muted-foreground">
                        <div class="flex flex-col items-center gap-2">
                            <x-lucide-shield-x class="w-12 h-12 opacity-30" />
                            <p>Стоп-слова не найдены</p>
                        </div>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @endforelse
        </x-ui.table-body>
    </x-ui.table>

    <!-- Пагинация -->
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div class="text-xs text-muted-foreground">
            Показано {{ $this->stopWords->firstItem() ?? 0 }} - {{ $this->stopWords->lastItem() ?? 0 }} из {{ $this->stopWords->total() }}
        </div>
        {{ $this->stopWords->links('partials.pagination') }}
    </div>

    <!-- МОДАЛКА СОЗДАНИЯ/РЕДАКТИРОВАНИЯ -->
    <div x-show="$wire.showModal" x-cloak @click.self="$wire.showModal = false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" x-transition @keydown.escape.window="$wire.showModal = false">
        <div class="relative bg-card border border-border rounded-lg shadow-2xl max-w-lg w-full mx-4 overflow-hidden">
            <div class="flex items-center justify-between p-4 border-b border-border">
                <h2 class="text-lg font-semibold">{{ $editingId ? 'Редактировать слово' : 'Добавить стоп-слово' }}</h2>
                <button @click="$wire.showModal = false" class="text-muted-foreground hover:text-foreground"><x-lucide-x class="w-5 h-5" /></button>
            </div>

            <div class="p-6 space-y-4">
                <div class="flex flex-col gap-2">
                    <x-ui.label for="sw-word">Слово или фраза</x-ui.label>
                    <x-ui.input id="sw-word" wire:model="modalWord" placeholder="Например: телеграмм, мат" />
                    @error('modalWord') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <x-ui.label>Категория</x-ui.label>
                        <x-ui.select wire:model="modalCategory">
                            <x-ui.select-trigger><x-ui.select-value /></x-ui.select-trigger>
                            <x-ui.select-content>
                                @foreach($this->categoriesList as $key => $cat)
                                    <x-ui.select-item value="{{ $key }}">{{ $cat }}</x-ui.select-item>
                                @endforeach
                            </x-ui.select-content>
                        </x-ui.select>
                    </div>

                    <div class="flex flex-col gap-2">
                        <x-ui.label>Действие системы</x-ui.label>
                        <x-ui.select wire:model.live="modalAction">
                            <x-ui.select-trigger><x-ui.select-value /></x-ui.select-trigger>
                            <x-ui.select-content>
                                <x-ui.select-item value="mask">Маскировать</x-ui.select-item>
                                <x-ui.select-item value="reject">Блокировать</x-ui.select-item>
                                <x-ui.select-item value="alert">Тревога (Антифрод)</x-ui.select-item>
                            </x-ui.select-content>
                        </x-ui.select>
                    </div>
                </div>

                <!-- Поле замены показывается только для маскировки -->
                <div class="flex flex-col gap-2">
                    @if($modalAction === 'mask')
                        <x-ui.label for="sw-replace">Замена</x-ui.label>
                        <x-ui.input id="sw-replace" wire:model="modalReplacement" maxlength="10" placeholder="***" />
                        <p class="text-xs text-muted-foreground">Оставьте пустым, чтобы просто удалить слово.</p>
                    @else
                        <x-ui.label>Замена</x-ui.label>
                        <x-ui.input disabled placeholder="Не применяется для этого действия" class="opacity-50" />
                    @endif
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="modalIsActive" class="sr-only peer" />
                        <div class="w-11 h-6 bg-muted rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-primary after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:border-primary"></div>
                    </label>
                    <span class="text-sm font-medium">Фильтр активен</span>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 p-4 border-t border-border bg-muted/20">
                <x-ui.button @click="$wire.showModal = false" variant="outline" size="sm">Отмена</x-ui.button>
                <x-ui.button wire:click="{{ $editingId ? 'updateWord' : 'saveWord' }}" variant="default" size="sm">
                    <x-lucide-save class="w-4 h-4 inline" /> Сохранить
                </x-ui.button>
            </div>
        </div>
    </div>
</div>