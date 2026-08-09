<?php

use App\Models\Media;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Livewire\Volt\Component;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public string $collectionFilter = 'all';
    public string $typeFilter = 'all';

    /**
     * Инициализация. Восстанавливаем фильтры из сессии.
     */
    public function mount(): void
    {
        $saved = session('admin_media_filters', []);
        if (isset($saved['search'])) $this->search = $saved['search'];
        if (isset($saved['collectionFilter'])) $this->collectionFilter = $saved['collectionFilter'];
        if (isset($saved['typeFilter'])) $this->typeFilter = $saved['typeFilter'];
    }

    // Сброс пагинации и сохранение в сессию при изменении фильтров
    public function updatedSearch(): void 
    { 
        session(['admin_media_filters' => array_merge(session('admin_media_filters', []), ['search' => $this->search])]);
        $this->resetPage(); 
    }

    public function updatedCollectionFilter(): void 
    { 
        session(['admin_media_filters' => array_merge(session('admin_media_filters', []), ['collectionFilter' => $this->collectionFilter])]);
        $this->resetPage(); 
    }

    public function updatedTypeFilter(): void 
    { 
        session(['admin_media_filters' => array_merge(session('admin_media_filters', []), ['typeFilter' => $this->typeFilter])]);
        $this->resetPage(); 
    }

    /**
     * Сброс фильтров
     */
    public function resetFilters(): void
    {
        $this->reset(['search', 'collectionFilter', 'typeFilter']);
        $this->collectionFilter = 'all';
        $this->typeFilter = 'all';
        session()->forget('admin_media_filters');
        $this->resetPage();
    }

    /**
     * Безопасное удаление файла (вызывает метод модели, который чистит диск)
     */
    public function deleteMedia(int $id): void
    {
        $media = Media::find($id);
        if ($media) {
            $media->safeDelete();
            $this->dispatch('show-toast', type: 'success', message: 'Файл успешно удален.');
        }
    }

    /**
     * Получение списка файлов со склада
     */
    #[Computed]
    public function mediaItems()
    {
        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

        return Media::query()
            ->when($this->search, function ($q) use ($operator) {
                $search = $this->search;
                $q->where(function ($q) use ($search, $operator) {
                    $q->where('file_name', $operator, "%{$search}%");
                    if (is_numeric($search)) {
                        $q->orWhere('id', (int) $search);
                    }
                });
            })
            ->when($this->collectionFilter !== 'all', fn($q) => $q->where('collection', $this->collectionFilter))
            ->when($this->typeFilter !== 'all', fn($q) => $q->where('type', $this->typeFilter))
            ->latest()
            ->paginate(24);
    }
}; 
?>

<div class="space-y-6">
    <!-- Шапка -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-4">
            @php
                $previousUrl = url()->previous();
                $backUrl = ($previousUrl && $previousUrl !== url()->current()) 
                    ? $previousUrl 
                    : route('admin.dashboard');
            @endphp

            <a href="{{ $backUrl }}" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-semibold flex items-center gap-2">
                    <x-lucide-image class="w-6 h-6" />
                    Медиа-хранилище
                </h1>
                <p class="text-sm text-muted-foreground">Управление загруженными файлами и картинками</p>
            </div>
        </div>

        {{-- Кнопка открытия менеджера загрузки --}}
        <x-ui.button type="button" wire:click="$dispatch('open-media-manager', { collection: 'default' })" variant="default" size="sm">
            <x-lucide-upload class="w-4 h-4" /> Загрузить файлы
        </x-ui.button>
    </div>

    <!-- Фильтры -->
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-2">
               
                <x-ui.select wire:model.live="collectionFilter" class="w-40">
                    <x-ui.select-trigger><x-ui.select-value placeholder="Коллекция" /></x-ui.select-trigger>
                    <x-ui.select-content>
                        <x-ui.select-item value="all">Все коллекции</x-ui.select-item>
                        @foreach(\App\Enums\MediaCollection::options() as $value => $label)
                            <x-ui.select-item value="{{ $value }}">{{ $label }}</x-ui.select-item>
                        @endforeach
                    </x-ui.select-content>
                </x-ui.select>

            <div class="flex gap-1.5">
                <x-ui.button wire:click="$set('typeFilter', 'all')" variant="{{ $typeFilter === 'all' ? 'default' : 'secondary' }}" size="sm">Все</x-ui.button>
                <x-ui.button wire:click="$set('typeFilter', 'image')" variant="{{ $typeFilter === 'image' ? 'default' : 'secondary' }}" size="sm">
                    <x-lucide-image class="w-4 h-4 inline mr-1" /> Фото
                </x-ui.button>
                <x-ui.button wire:click="$set('typeFilter', 'video')" variant="{{ $typeFilter === 'video' ? 'default' : 'secondary' }}" size="sm">
                    <x-lucide-video class="w-4 h-4 inline mr-1" /> Видео
                </x-ui.button>
            </div>
        </div>

        <div class="flex items-center gap-2">
        <x-ui.button wire:click="resetFilters" variant="outline" size="sm">Сбросить фильтры</x-ui.button>

        <div class="relative w-64">
            <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск по имени или ID..." class="pl-9 pr-8" />
            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            @if(!empty($search))
                <button wire:click="$set('search', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"><x-lucide-x class="w-4 h-4" /></button>
            @endif
        </div>
        </div>
    </div>

    <!-- Склад (Сетка) -->
    @if($this->mediaItems->isEmpty())
        <div class="bg-card border border-border rounded-lg p-16 text-center text-muted-foreground">
            <x-lucide-image-off class="w-12 h-12 opacity-30 mx-auto mb-2" />
            <p>Файлов не найдено</p>
            @if(!empty($search) || $collectionFilter !== 'all' || $typeFilter !== 'all')
                <x-ui.button wire:click="resetFilters" variant="outline" size="sm" class="mt-4">Сбросить фильтры</x-ui.button>
            @endif
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
            @foreach($this->mediaItems as $media)
               <div wire:key="media-{{ $media->id }}" class="relative group rounded-lg overflow-hidden border border-border bg-muted/10 flex flex-col">
    
                    <div class="relative flex-1 w-full  aspect-square overflow-hidden">
                        @if($media->type === 'image')
                            <a href="{{ $media->url }}" data-fancybox="media-gallery" data-caption="{{ $media->file_name }}" class="absolute inset-0 w-full h-full cursor-pointer block">
                                <img src="{{ $media->url }}" class="w-full h-full object-cover object-center group-hover:scale-110 transition-transform duration-300">
                            </a>
                        @else
                            <div class="absolute inset-0 w-full h-full flex items-center justify-center bg-muted">
                                <x-lucide-video class="w-8 h-8 text-muted-foreground" />
                            </div>
                        @endif

                        <!-- Бейджи -->
                        <div class="absolute top-1 left-1 flex flex-col gap-1">                            
                            @php $collectionEnum = \App\Enums\MediaCollection::tryFrom($media->collection); @endphp
                            @if($collectionEnum)
                                <span class="text-[10px] px-1.5 py-0.5 rounded font-medium border {{ $collectionEnum->color() }}">
                                    {{ $collectionEnum->label() }}
                                </span>
                            @endif
                        </div>

                        <div class="absolute top-1 right-1 flex flex-col gap-1 items-end">                            
                            <span class="bg-black/60 text-white text-[10px] font-mono font-bold px-1.5 py-0.5 rounded">#{{ $media->id }}</span>
                            <button wire:click="deleteMedia({{ $media->id }})" wire:confirm="Удалить файл навсегда с диска?" class="flex justify-center items-center opacity-0 group-hover:opacity-100 transition-opacity bg-destructive/80 hover:bg-destructive text-white p-1 rounded-sm">
                                <x-lucide-trash-2 class="w-3 h-3" />
                            </button>
                        </div>

                        {{-- Всплывающая подсказка с правилами обработки --}}
                        @if($collectionEnum)
                            <div class="absolute bottom-0 left-0 right-0 bg-black/80 text-white text-[10px] px-2 py-1 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10 text-center backdrop-blur-sm">
                                {{ $collectionEnum->dimensionsHint() }}
                            </div>
                        @endif
                    </div>

                    <!-- Инфо о файле -->
                    <div class="p-2 bg-card border-t border-border shrink-0">
                        <p class="text-xs font-medium truncate text-foreground" title="{{ $media->file_name }}">{{ $media->file_name }}</p>
                        <div class="flex items-center justify-between text-[10px] text-muted-foreground mt-1">
                            <span>{{ number_format($media->size / 1048576, 2) }} MB</span>
                            <span>{{ $media->created_at->format('d.m.y') }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">{{ $this->mediaItems->links('partials.pagination') }}</div>
    @endif
    
{{-- Подключаем менеджер, чтобы работала кнопка "Загрузить" --}}
<livewire:admin.media-manager />

<script>
    // Инициализация Fancybox
    document.addEventListener('livewire:navigated', () => {
        if (typeof Fancybox !== 'undefined') {
            Fancybox.defaults.Hash = false; 
            Fancybox.bind('[data-fancybox]'); 
        }
    });
</script>
</div>
