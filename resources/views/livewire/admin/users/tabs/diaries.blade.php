<?php

use App\Models\Diary;
use App\Models\Rubric;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component 
{
    use WithPagination;

    public int $userId;
    public string $search = '';
    public string $rubricFilter = 'all';

    public function mount(int $userId): void
    {
        $this->userId = $userId;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function setRubricFilter(string $filter): void
    {
        $this->rubricFilter = $filter;
        $this->resetPage();
        unset($this->diaries);
    }

    #[On('user-action-performed')] 
    public function refreshDiaries(): void
    {
        unset($this->diaries);
        unset($this->userRubrics);
    }

    // Получаем только те рубрики, в которых юзер реально имеет посты
    #[Computed]
    public function userRubrics()
    {
        return Rubric::whereHas('diaries', fn($q) => $q->where('user_id', $this->userId))
            ->orderBy('user_id') // Системные (null) будут сверху
            ->orderBy('name')
            ->get();
    }

    
    #[Computed]
    public function diaries()
    {
        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

        return Diary::where('user_id', $this->userId)
            ->with('rubric')
            ->withTrashed()
            ->when($this->search, function ($q) use ($operator) {
                // ФИКС: Обернули в where(), чтобы OR не сломал фильтр user_id
                $q->where(function ($q) use ($operator) {
                    $q->where('title', $operator, "%{$this->search}%");
                    if (is_numeric($this->search)) {
                        $q->orWhere('id', (int) $this->search);
                    }
                });
            })
            ->when($this->rubricFilter !== 'all', function ($q) {
                if ($this->rubricFilter === 'none') {
                    $q->whereNull('rubric_id');
                } else {
                    $q->where('rubric_id', $this->rubricFilter);
                }
            })
            ->latest('created_at')
            ->paginate(10);
    }
}; 
?>

<div class="space-y-4">

    <div class="flex justify-between gap-4">
    <!-- Кнопки фильтров по рубрикам -->
    <div class="flex flex-wrap gap-1.5">
        <x-ui.button wire:click="setRubricFilter('all')" variant="{{ $rubricFilter === 'all' ? 'default' : 'secondary' }}" size="sm">
            Все записи
        </x-ui.button>
        <x-ui.button wire:click="setRubricFilter('none')" variant="{{ $rubricFilter === 'none' ? 'default' : 'secondary' }}" size="sm">
            Без рубрики
        </x-ui.button>
        @foreach($this->userRubrics as $r)
            <x-ui.button wire:click="setRubricFilter('{{ $r->id }}')" variant="{{ $rubricFilter == $r->id ? 'default' : 'secondary' }}" size="sm">
                <span class="flex items-center gap-1">
                    @if($r->user_id) 
                        <x-lucide-user class="w-3 h-3 text-muted-foreground" />
                    @else 
                        <x-lucide-globe class="w-3 h-3 text-blue-500" />
                    @endif
                    {{ $r->name }}
                </span>
            </x-ui.button>
        @endforeach
    </div>
    <!-- Поиск по записям этого юзера -->
    <div class="flex justify-end">
        <div class="relative w-64">
            <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск по названию..." class="pl-9 pr-8" />
            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground z-10" />
        </div>
    </div>    
    </div>

    <x-ui.table>
        <x-ui.table-header>
            <x-ui.table-row>
                <x-ui.table-head class="w-12">ID</x-ui.table-head>
                <x-ui.table-head>Заголовок</x-ui.table-head>
                <x-ui.table-head>Рубрика</x-ui.table-head>
                <x-ui.table-head>Статус</x-ui.table-head>
                <x-ui.table-head>Дата</x-ui.table-head>
            </x-ui.table-row>
        </x-ui.table-header>

        <x-ui.table-body>
            @forelse ($this->diaries as $diary)
                <x-ui.table-row wire:key="user-diary-{{ $diary->id }}" class="{{ $diary->trashed() ? 'opacity-50 bg-red-500/5' : '' }}">
                    <x-ui.table-cell class="text-xs font-mono whitespace-nowrap">
                        <a href="{{ route('admin.moderation.diary.moderate', $diary->id) }}" wire:navigate class="text-blue-500 hover:underline" title="Открыть запись">
                            #{{ $diary->id }}
                        </a>
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        <a href="{{ route('admin.moderation.diary.moderate', $diary->id) }}" wire:navigate class="text-sm font-medium hover:text-primary line-clamp-1">
                            {{ $diary->title }}
                        </a>
                    </x-ui.table-cell>
                   <x-ui.table-cell>
                        @if($diary->rubric)
                            <div class="flex items-center gap-1">
                                @if(!$diary->rubric->is_active)
                                    <x-lucide-alert-triangle class="w-3.5 h-3.5 text-yellow-500 shrink-0" title="Рубрика скрыта админом (не видна юзерам)" />
                                @endif
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
                            </div>
                        @else
                            <span class="text-xs text-muted-foreground italic">Без рубрики</span>
                        @endif
                    </x-ui.table-cell>
                    <x-ui.table-cell>
                        @if($diary->trashed())
                            <x-ui.badge variant="warning" size="sm">Карантин</x-ui.badge>
                        @elseif($diary->status === 'published')
                            <x-ui.badge variant="success" size="sm">Опубл.</x-ui.badge>
                        @elseif($diary->status === 'pending')
                            <x-ui.badge variant="warning" size="sm">Модерация</x-ui.badge>
                        @elseif($diary->status === 'rejected')
                            <x-ui.badge variant="destructive" size="sm">Отклонено</x-ui.badge>
                        @else
                            <x-ui.badge variant="secondary" size="sm">Черновик</x-ui.badge>
                        @endif
                    </x-ui.table-cell>
                    <x-ui.table-cell class="text-xs text-muted-foreground whitespace-nowrap">
                        {{ $diary->created_at->format('d.m.y H:i') }}
                    </x-ui.table-cell>
                </x-ui.table-row>
            @empty
                <x-ui.table-row wire:key="empty-user-diaries">
                    <x-ui.table-cell colspan="5" class="py-12 text-center text-muted-foreground">
                        <x-lucide-notebook-pen class="w-12 h-12 opacity-30 mx-auto mb-2" />
                        <p>У пользователя нет записей в дневнике</p>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @endforelse
        </x-ui.table-body>
    </x-ui.table>

    <div class="mt-4">
        {{ $this->diaries->links('partials.pagination') }}
    </div>
</div>