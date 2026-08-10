<?php

use App\Models\Media;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Livewire\Volt\Component;
use Livewire\Attributes\On;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    /** @var string Строка поиска по имени или ID */
    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** @var string Текущий фильтр коллекции */
    public string $collectionFilter = 'all';

    /** @var int|null ID файла для просмотра в модалке */
    public ?int $viewingMediaId = null;

    /**
     * Инициализация. Восстанавливаем фильтры из сессии.
     */
    public function mount(): void
    {
        $saved = session('admin_media_filters', []);
        if (isset($saved['search'])) $this->search = $saved['search'];
        if (isset($saved['collectionFilter'])) $this->collectionFilter = $saved['collectionFilter'];
    }

    /**
     * Хук Livewire: сброс пагинации и сохранение в сессию при поиске.
     */
    public function updatedSearch(): void 
    { 
        session(['admin_media_filters' => array_merge(session('admin_media_filters', []), ['search' => $this->search])]);
        $this->resetPage(); 
    }

    /**
     * Хук Livewire: сброс пагинации и сохранение в сессию при смене фильтра.
     */
    public function updatedCollectionFilter(): void 
    { 
        session(['admin_media_filters' => array_merge(session('admin_media_filters', []), ['collectionFilter' => $this->collectionFilter])]);
        $this->resetPage(); 
    }

    /**
     * Сброс всех фильтров к значениям по умолчанию.
     */
    public function resetFilters(): void
    {
        $this->reset(['search', 'collectionFilter']);
        $this->collectionFilter = 'all';
        session()->forget('admin_media_filters');
        $this->resetPage();
    }

    /**
     * Безопасное удаление файла (через метод модели, чистящий варианты).
     */
    public function deleteMedia(int $id): void
    {
        $media = Media::find($id);
        if ($media) {
            if ($this->viewingMediaId === $id) {
                $this->viewingMediaId = null;
            }
            $media->safeDelete();
            $this->dispatch('show-toast', type: 'success', message: 'Файл успешно удален.');
        }
    }

    /**
     * Открыть модалку просмотра информации о файле.
     */
    public function viewMedia(int $id): void
    {
        $this->viewingMediaId = $id;
    }

    /**
     * Слушатель события обновления медиа (от менеджера).
     */
    #[On('media-updated')]
    public function refreshMedia(): void
    {
        unset($this->mediaItems);
        unset($this->collectionCounts);
    }

    /**
     * Счетчики для кнопок фильтров коллекций.
     */
    #[Computed]
    public function collectionCounts(): array
    {
        $counts = ['all' => Media::where('type', 'image')->count()];
        
        foreach (\App\Enums\MediaCollection::cases() as $case) {
            $counts[$case->value] = Media::where('type', 'image')->where('collection', $case->value)->count();
        }
        
        return $counts;
    }

    /**
     * Данные для модалки просмотра.
     */
    #[Computed]
    public function viewingMedia()
    {
        if (!$this->viewingMediaId) return null;
        return Media::find($this->viewingMediaId);
    }

    /**
     * Получение списка файлов со склада с пагинацией и поиском.
     */
    #[Computed]
    public function mediaItems()
    {
        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

        return Media::query()
            ->where('type', 'image') // Строго только картинки
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
                <p class="text-sm text-muted-foreground">Управление загруженными изображениями</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <x-ui.button type="button" wire:click="$dispatch('open-media-manager', { collection: 'default' })" variant="default" size="sm">
                <x-lucide-upload class="w-4 h-4" /> Загрузить изображения
            </x-ui.button>
        </div>
    </div>

    <!-- Фильтры-кнопки -->
    <div class="flex items-center gap-2 flex-wrap">
        <x-ui.button wire:click="$set('collectionFilter', 'all')" variant="{{ $collectionFilter === 'all' ? 'default' : 'secondary' }}" size="sm">
            Все <x-ui.badge size="xs" class="ml-1.5">{{ $this->collectionCounts['all'] }}</x-ui.badge>
        </x-ui.button>
        
        @foreach(\App\Enums\MediaCollection::cases() as $case)
            @if($this->collectionCounts[$case->value] > 0)
                <x-ui.button wire:click="$set('collectionFilter', '{{ $case->value }}')" variant="{{ $collectionFilter === $case->value ? 'default' : 'secondary' }}" size="sm">
                    {{ $case->label() }} <x-ui.badge size="xs" class="ml-1.5">{{ $this->collectionCounts[$case->value] }}</x-ui.badge>
                </x-ui.button>
            @endif
        @endforeach

        <div class="flex items-center gap-2 ml-auto">
            <x-ui.button wire:click="resetFilters" variant="outline" size="sm" class="flex items-center gap-1 {{ (!empty($search) || $collectionFilter !== 'all') ? '' : 'opacity-50 pointer-events-none' }}">
                <x-lucide-filter-x class="w-4 h-4" />
                Сбросить фильтр
            </x-ui.button>

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
            @if(!empty($search) || $collectionFilter !== 'all')
                <x-ui.button wire:click="resetFilters" variant="outline" size="sm" class="mt-4">Сбросить фильтры</x-ui.button>
            @endif
        </div>
    @else
        <div x-data="{ activeMedia: { id: null, url: '' } }">            
            <x-ui.context-menu>
                <x-ui.context-menu-trigger asChild>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                        @foreach($this->mediaItems as $media)
                            @php $collectionEnum = \App\Enums\MediaCollection::tryFrom($media->collection); @endphp
                            
                            <div wire:key="media-{{ $media->id }}" 
                                 @contextmenu.prevent="activeMedia = { id: {{ $media->id }}, url: '{{ asset($media->url) }}' }"
                                 class="relative group aspect-square rounded-lg overflow-hidden border border-border bg-muted/10">
                                
                                <a href="{{ $media->url }}" data-fancybox="media-gallery" data-caption="{{ $media->file_name }}" class="absolute inset-0 w-full h-full block z-10">
                                    <x-media-image src="{{ $media->url }}" class="w-full h-full object-cover object-center group-hover:scale-110 transition-transform duration-300"/>
                                </a>

                                <!-- Бейджи (Коллекция и ID) -->
                                <div class="absolute top-1 left-1 flex flex-col gap-1 z-20 pointer-events-none">                            
                                    @if($collectionEnum)
                                        <span class="text-[10px] px-1.5 py-0.5 rounded font-medium border {{ $collectionEnum->color() }}">
                                            {{ $collectionEnum->label() }}
                                        </span>
                                    @endif
                                </div>

                                <div class="absolute top-1 right-1 z-20 pointer-events-none">                            
                                    <span class="bg-black/60 text-white text-[10px] font-mono font-bold px-1.5 py-0.5 rounded">#{{ $media->id }}</span>
                                </div>

                                {{-- Спиннер фоновой обработки --}}
                                @if(empty($media->variants))
                                    <div class="absolute inset-0 flex items-center justify-center bg-black/30 z-30 pointer-events-none">
                                        <x-lucide-loader-2 class="w-6 h-6 text-white animate-spin" />
                                    </div>
                                @endif

                                <!-- Быстрая кнопка удаления -->
                                <button wire:click.stop="deleteMedia({{ $media->id }})" wire:confirm="Удалить файл навсегда с диска?" class="absolute bottom-1 right-1 z-40 opacity-0 group-hover:opacity-100 transition-opacity bg-destructive/80 hover:bg-destructive text-white p-1 rounded-sm">
                                    <x-lucide-trash-2 class="w-3 h-3" />                                    
                                </button>
                            </div>
                        @endforeach
                    </div>
                </x-ui.context-menu-trigger>

                {{-- Контекстное меню --}}
                <x-ui.context-menu-content class="w-56">
                    <x-ui.context-menu-item @click="Fancybox.show([{ src: activeMedia.url, type: 'image' }]);">
                        <x-lucide-maximize-2 class="w-4 h-4 mr-2" /> Открыть в лайтбоксе
                    </x-ui.context-menu-item>
                    
                    <x-ui.context-menu-item @click="$wire.viewMedia(activeMedia.id)">
                        <x-lucide-info class="w-4 h-4 mr-2" /> Посмотреть данные
                    </x-ui.context-menu-item>

                    <x-ui.context-menu-separator />
                    
                    <x-ui.context-menu-item @click="navigator.clipboard.writeText(activeMedia.url); $wire.dispatch('show-toast', {type: 'success', message: 'URL скопирован'});">
                        <x-lucide-link class="w-4 h-4 mr-2" /> Копировать URL (ориг.) 
                    </x-ui.context-menu-item>

                    <x-ui.context-menu-separator />

                    <x-ui.context-menu-item variant="destructive" wire:click="deleteMedia(activeMedia.id)" wire:confirm="Удалить файл навсегда с диска?">
                        <x-lucide-trash-2 class="w-4 h-4 mr-2" /> Удалить файл
                    </x-ui.context-menu-item>
                </x-ui.context-menu-content>
               
            </x-ui.context-menu>
        </div>

        <div class="mt-6">{{ $this->mediaItems->links('partials.pagination') }}</div>
    @endif
    
    <livewire:admin.media-manager />  

    <!-- МОДАЛКА ПРОСМОТРА ИНФОРМАЦИИ (Всегда в DOM) -->
    <div x-data="{ show: @entangle('viewingMediaId') }" 
         x-show="show" 
         x-cloak
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
         wire:click="$set('viewingMediaId', null)"
         style="display: none;">
         
        <div x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="relative bg-card border border-border rounded-lg shadow-2xl max-w-2xl w-full mx-4 overflow-hidden flex flex-col max-h-[90vh]" wire:click.stop>
             
            {{-- Защита от null, чтобы не падала верстка при первой загрузке --}}
            @if($this->viewingMedia)
                <div class="flex items-center justify-between p-4 border-b border-border shrink-0">
                    <h2 class="text-md font-semibold truncate">{{ $this->viewingMedia->file_name }}</h2>
                    <x-ui.button variant="ghost" size="icon-sm" wire:click="$set('viewingMediaId', null)">
                        <x-lucide-x class="w-5 h-5" />
                    </x-ui.button>
                </div>

                <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-6 overflow-y-auto">
                    {{-- Большое превью --}}
                    <div class="bg-muted/10 rounded-lg overflow-hidden border border-border flex items-center justify-center aspect-square">
                        <img src="{{ $this->viewingMedia->url }}" alt="{{ $this->viewingMedia->file_name }}" class="w-full h-full object-contain">
                    </div>

                    {{-- Метаданные --}}
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-xs text-muted-foreground">ID файла</p>
                                <p class="font-medium">#{{ $this->viewingMedia->id }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Размер оригинала</p>
                                <p class="font-medium">{{ number_format($this->viewingMedia->size / 1048576, 2) }} MB</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Тип MIME</p>
                                <p class="font-medium">{{ $this->viewingMedia->mime_type }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Дата загрузки</p>
                                <p class="font-medium">{{ $this->viewingMedia->created_at->format('d.m.Y H:i') }}</p>
                            </div>
                        </div>

                        @if(!empty($this->viewingMedia->variants))
                            <div class="pt-4 border-t border-border">
                                <h4 class="text-sm font-semibold mb-2">Сгенерированные варианты:</h4>
                                <div class="space-y-2">
                                    @foreach($this->viewingMedia->variants as $key => $path)
                                        @php 
                                            $variantUrl = asset(\Illuminate\Support\Facades\Storage::url($path));
                                            // Достаем правила кропа для этого варианта из конфига
                                            $variantConfig = config("media.collections.{$this->viewingMedia->collection}.variants.{$key}");
                                            $sizeStr = $variantConfig['size'] ?? '—';
                                            $fitStr = $variantConfig['fit'] ?? '—';
                                            $formatStr = $variantConfig['format'] ?? '—';
                                        @endphp
                                        <div class="flex items-center justify-between gap-2 bg-muted/20 p-2 rounded-md">
                                            <div class="flex flex-col min-w-0 gap-0.5">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-mono text-xs font-bold">{{ $key }}</span>
                                                    <span class="text-[10px] text-muted-foreground">({{ $sizeStr }}, {{ $fitStr }}, {{ $formatStr }})</span>
                                                </div>
                                                <span class="text-[10px] text-muted-foreground truncate">{{ $variantUrl }}</span>
                                            </div>
                                            <x-ui.button title="Скопировать ссылку" x-data @click="navigator.clipboard.writeText('{{ $variantUrl }}'); $wire.dispatch('show-toast', {type: 'success', message: 'Путь скопирован: {{ $key }}'});" variant="outline" size="icon-xs">
                                                <x-lucide-copy class="w-3.5 h-3.5" />
                                            </x-ui.button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="pt-4 border-t border-border flex items-center gap-2 text-yellow-500">
                                <x-lucide-loader-2 class="w-4 h-4 animate-spin" />
                                <span class="text-sm">Идет фоновая нарезка вариантов...</span>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
    <script>
        document.addEventListener('livewire:navigated', () => {
            if (typeof Fancybox !== 'undefined') {
                Fancybox.defaults.Hash = false; 
                Fancybox.bind('[data-fancybox]'); 
            }
        });
    </script>
</div>