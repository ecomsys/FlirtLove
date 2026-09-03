<?php

use App\Actions\Admin\ManageMediaAction;
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

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'col', except: 'all')]
    public string $collectionFilter = 'all';

    public ?int $viewingMediaId = null;

    public string $backUrl = '';

       public function mount(): void
    {
        $previousUrl = url()->previous();
        $this->backUrl = ($previousUrl && $previousUrl !== url()->current()) 
            ? $previousUrl 
            : route('admin.dashboard');

        // ФИКС: Умный отлов ID прямо из URL (?q=123)
        $qParam = request()->query('q', '');
        if (!empty($qParam)) {
            $this->search = (string) $qParam;
            
            // ФИКС: Ищем коллекцию файла, чтобы переключить вкладку и применить ПРАВИЛЬНЫЕ ПРОПОРЦИИ!
            if (is_numeric($qParam)) {
                $media = Media::find((int) $qParam);
                if ($media) {
                    $this->collectionFilter = $media->collection;
                } else {
                    $this->collectionFilter = 'all';
                }
            }
        }
    }

    public function updatedSearch(): void 
    { 
        $this->resetPage(); 

        // ФИКС: Умный поиск. Если ввели число, ищем коллекцию этого файла
        if (is_numeric($this->search) && !empty($this->search)) {
            $media = Media::find((int) $this->search);
            
            if ($media) {
                // Если файл нашли — переключаем фильтр на его коллекцию
                $this->collectionFilter = $media->collection;
            } else {
                // Если файла нет — переключаем на "Все", чтобы не смотреть пустой экран
                $this->collectionFilter = 'all';
            }
            
            // Сбрасываем кэш, чтобы список перерисовался с новой коллекцией
            unset($this->mediaItems);
        }
    }
    
    //  Вызывается только при ручном клике на кнопки фильтров
    public function setCollection(string $collection): void
    {
        $this->collectionFilter = $collection;
        $this->search = ''; // Очищаем поиск только при ручном клике!
        $this->resetPage(); 
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'collectionFilter']);
        $this->collectionFilter = 'all';
        $this->resetPage();
    }

    public function openManagerModal(string $collection = 'default'): void
    {
        $this->dispatch('open-media-manager', collection: $collection)->to('admin.media-manager');
    }

    public function deleteMedia(int $id, ManageMediaAction $action): void
    {
        $media = Media::find($id);
        if (!$media) return;

        if ($this->viewingMediaId === $id) {
            $this->viewingMediaId = null;
        }

        $action->delete($media, auth()->user());

        $this->dispatch('show-toast', type: 'success', message: 'Файл успешно удален.');
        
        unset($this->mediaItems);
        unset($this->collectionCounts);
    }

    public function viewMedia(int $id): void
    {
        $this->viewingMediaId = $id;
    }

    #[On('media-updated')]
    public function refreshMedia(): void
    {
        unset($this->mediaItems);
        unset($this->collectionCounts);
    }

    #[Computed]
    public function collectionCounts(): array
    {
        $dbCounts = Media::where('type', 'image')
            ->selectRaw('collection, count(*) as count')
            ->groupBy('collection')
            ->pluck('count', 'collection');

        $counts = ['all' => $dbCounts->sum()];
        
        foreach (\App\Enums\MediaCollection::cases() as $case) {
            $counts[$case->value] = $dbCounts[$case->value] ?? 0;
        }
        
        return $counts;
    }

    #[Computed]
    public function viewingMedia()
    {
        if (!$this->viewingMediaId) return null;
        return Media::find($this->viewingMediaId);
    }

    #[Computed]
    public function mediaItems()
    {
        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

        return Media::query()
            ->where('type', 'image')
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

       // НОВЫЙ ХЕЛПЕР: Жестко берет пропорции из конфига media.php
    public function getAspectRatio(string $collection): string
    {
        // Читаем прямо из конфига: media.collections.ПОСТ.thumb.size
        $thumbSize = config("media.collections.{$collection}.variants.thumb.size", '300x300');
        
        // Если размер указан как '800w' (только ширина), делаем квадрат
        if (str_contains($thumbSize, 'w') && !str_contains($thumbSize, 'x')) {
            $ratioW = (int) rtrim($thumbSize, 'w');
            return "{$ratioW} / {$ratioW}";
        }
        
        // Разбиваем '320x180' на ширину и высоту
        $parts = explode('x', strtolower($thumbSize));
        $ratioW = (int) ($parts[0] ?? 300);
        $ratioH = (int) ($parts[1] ?? $ratioW);
        
        return "{$ratioW} / {$ratioH}";
    }
}; 
?>

<div class="space-y-6">
    <!-- Шапка -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-4">
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

        <div class="flex gap-2 items-center">
         <div class="flex items-center gap-2 ml-auto">
            <x-ui.button wire:click="resetFilters" variant="outline" size="sm" class="flex items-center gap-1 {{ (!empty($search) || $collectionFilter !== 'all') ? '' : 'opacity-50 pointer-events-none' }}">
                <x-lucide-filter-x class="w-4 h-4" />
                Сбросить
            </x-ui.button>

            <div class="relative w-64">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск по имени или ID..." class="pl-9 pr-8" />
                <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                @if(!empty($search))
                    <button wire:click="$set('search', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"><x-lucide-x class="w-4 h-4" /></button>
                @endif
            </div>
        </div>
        <div class="flex items-center gap-2">
            <x-ui.button type="button" wire:click="openManagerModal('default')" variant="default" size="sm">
                <x-lucide-upload class="w-4 h-4" /> Загрузить изображения
            </x-ui.button>
        </div>
        </div>
    </div>

    <!-- Фильтры-кнопки -->
    <div class="flex items-center gap-2 flex-wrap">
        <x-ui.button wire:key="col-all" wire:click="setCollection('all')" variant="{{ $collectionFilter === 'all' ? 'default' : 'secondary' }}" size="sm">
            Все <x-ui.badge size="xs" class="ml-1.5">{{ $this->collectionCounts['all'] }}</x-ui.badge>
        </x-ui.button>
        
        @foreach(\App\Enums\MediaCollection::cases() as $case)
            @if($this->collectionCounts[$case->value] > 0)
                <x-ui.button wire:key="col-{{ $case->value }}" wire:click="setCollection('{{ $case->value }}')" variant="{{ $collectionFilter === $case->value ? 'default' : 'secondary' }}" size="sm">
                    {{ $case->label() }} <x-ui.badge size="xs" class="ml-1.5">{{ $this->collectionCounts[$case->value] }}</x-ui.badge>
                </x-ui.button>
            @endif
        @endforeach       
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
        @php 
            // ФИКС: Группируем по коллекциям только если выбран фильтр "Все" и выключен поиск
            $isGroupedView = ($collectionFilter === 'all' && empty($this->search));
            $groups = $isGroupedView ? $this->mediaItems->groupBy('collection') : ['_single' => $this->mediaItems];
        @endphp

        @foreach($groups as $collectionName => $items)
            @php 
                $collectionEnum = $collectionName !== '_single' ? \App\Enums\MediaCollection::tryFrom($collectionName) : null;
                $ratio = $this->getAspectRatio($collectionName !== '_single' ? $collectionName : $collectionFilter);
         
                // Адаптивная сетка для прямоугольных коллекций (делаем крупнее)
                $currentColName = $collectionName !== '_single' ? $collectionName : $collectionFilter;
                $wideCollections = ['post', 'notifications', 'banner_desktop'];
                $gridCols = in_array($currentColName, $wideCollections) 
                    ? 'lg:grid-cols-4'  // Крупнее для прямоугольных
                    : 'lg:grid-cols-6'; // Стандартно для квадратных
            @endphp

            @if($isGroupedView && $items->isNotEmpty())
                <div class="mb-6">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground mb-3 flex items-center gap-2">
                        @if($collectionEnum)
                            <span class="px-2 py-0.5 rounded font-medium border {{ $collectionEnum->color() }}">{{ $collectionEnum->label() }}</span>
                        @endif
                    </h3>
            @endif
            
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 {{ $gridCols }} gap-4 mb-8"
                 x-data="{
                    menuOpen: false,
                    activeMediaId: null,
                    activeMediaUrl: '',
                    openMenu(e, id, url) {
                        e.preventDefault();
                        this.$refs.menu.style.left = e.pageX + 'px';
                        this.$refs.menu.style.top = e.pageY + 'px';
                        this.activeMediaId = id;
                        this.activeMediaUrl = url;
                        this.menuOpen = true;
                    },
                    init() {
                        // Слушаем скролл на ВСЕМ документе (включая кастомные скроллбары внутри div-ов)
                        window.addEventListener('scroll', () => { this.menuOpen = false; }, { capture: true });
                    }
                 }">
                 
                @foreach($items as $media)
                    @php $itemCollectionEnum = \App\Enums\MediaCollection::tryFrom($media->collection); @endphp
                    @php $isHighlighted = is_numeric($this->search) && $media->id === (int)$this->search; @endphp
                    
                    <div wire:key="media-{{ $media->id }}" 
                         x-data="{ isHi: {{ $isHighlighted ? 'true' : 'false' }} }"
                         x-init="isHi && setTimeout(() => { $el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 200)"
                         @contextmenu="openMenu($event, {{ $media->id }}, '{{ $media->getVariantUrl('lg') }}')"
                         class="relative">
                        
                        {{-- Сама карточка картинки --}}
                        <div class="relative group rounded-lg overflow-hidden border border-border bg-muted/10 {{ $isHighlighted ? 'ring-2 ring-blue-500/50 z-10' : '' }}"
                             style="aspect-ratio: {{ $ratio }};">
               
                            <a href="{{ $media->getVariantUrl('lg') }}" data-fancybox="media-gallery-{{ $media->collection }}" data-caption="{{ $media->file_name }}" class="absolute inset-0 w-full h-full block z-10">
                                <x-media-image src="{{ $media->getVariantUrl('thumb') }}" class="w-full h-full object-cover object-center group-hover:scale-110 transition-transform duration-300"/>
                            </a>

                            <div class="absolute top-1 left-1 flex flex-col gap-1 z-20 pointer-events-none">                            
                                @if($itemCollectionEnum && !$isGroupedView)
                                    <span class="text-[10px] px-1.5 py-0.5 rounded font-medium border {{ $itemCollectionEnum->color() }}">
                                        {{ $itemCollectionEnum->label() }}
                                    </span>
                                @endif
                            </div>

                            <div class="absolute top-1 right-1 z-20 pointer-events-none">                            
                                <span class="bg-black/60 text-white text-[10px] font-mono font-bold px-1.5 py-0.5 rounded">#{{ $media->id }}</span>
                            </div>

                            @if(empty($media->variants))
                                <div class="absolute inset-0 flex items-center justify-center bg-black/30 z-30 pointer-events-none">
                                    <x-lucide-loader-2 class="w-6 h-6 text-white animate-spin" />
                                </div>
                            @endif

                            <button wire:click="deleteMedia({{ $media->id }})" wire:confirm="Удалить файл навсегда с диска?" class="absolute bottom-1 right-1 z-40 opacity-0 group-hover:opacity-100 transition-opacity bg-destructive/80 hover:bg-destructive text-white p-1 rounded-sm">
                                <x-lucide-trash-2 class="w-3 h-3" />                                    
                            </button>
                        </div>
                    </div>
                @endforeach

                {{-- ЕДИНОЕ КОНТЕКСТНОЕ МЕНЮ ДЛЯ ВСЕЙ СЕТКИ --}}
                <div x-show="menuOpen" x-cloak 
                     x-ref="menu"
                     @click.outside="menuOpen = false"
                     class="fixed z-[100] min-w-[200px] bg-card border border-border rounded-md shadow-xl py-1"
                     style="display: none;">
                    
                    <div @click="Fancybox.show([{ src: activeMediaUrl, type: 'image' }]); menuOpen = false" class="flex items-center gap-2 px-3 py-2 text-sm hover:bg-accent cursor-pointer">
                        <x-lucide-maximize-2 class="w-4 h-4" /> Открыть
                    </div>
                    
                    <div @click="$wire.viewMedia(activeMediaId); menuOpen = false" class="flex items-center gap-2 px-3 py-2 text-sm hover:bg-accent cursor-pointer">
                        <x-lucide-info class="w-4 h-4" /> Данные
                    </div>

                    <div class="h-px bg-border my-1"></div>

                    <div @click="navigator.clipboard.writeText(activeMediaUrl); $wire.dispatch('show-toast', {type: 'success', message: 'URL скопирован'}); menuOpen = false" class="flex items-center gap-2 px-3 py-2 text-sm hover:bg-accent cursor-pointer">
                        <x-lucide-link class="w-4 h-4" /> Копировать URL
                    </div>

                    <div class="h-px bg-border my-1"></div>

                    <div @click="$wire.deleteMedia(activeMediaId); menuOpen = false" wire:confirm="Удалить файл навсегда с диска?" class="flex items-center gap-2 px-3 py-2 text-sm text-destructive hover:bg-destructive/10 cursor-pointer">
                        <x-lucide-trash-2 class="w-4 h-4" /> Удалить
                    </div>
                </div>
            </div>
            
            @if($isGroupedView && $items->isNotEmpty())
                </div>

                <hr class="mb-6">
            @endif
        @endforeach

        <div class="mt-6">{{ $this->mediaItems->links('partials.pagination') }}</div>
    @endif
    
    <livewire:admin.media-manager />  

    <!-- МОДАЛКА ПРОСМОТРА ИНФОРМАЦИИ -->
    @if($viewingMediaId)
    <div wire:key="view-media-modal-{{ $viewingMediaId }}"
         x-data
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
         wire:click="$set('viewingMediaId', null)">
         
        <div x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="relative bg-card border border-border rounded-lg shadow-2xl max-w-2xl w-full mx-4 overflow-hidden flex flex-col max-h-[90vh]" @click.stop>
             
        @if($this->viewingMedia)
            <div class="flex items-center justify-between p-4 border-b border-border shrink-0">
                <h2 class="text-md font-semibold truncate">{{ $this->viewingMedia->file_name }}</h2>
                <x-ui.button variant="ghost" size="icon-sm" wire:click="$set('viewingMediaId', null)">
                    <x-lucide-x class="w-5 h-5" />
                </x-ui.button>
            </div>

            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-6 overflow-y-auto little-scroll">
                <div class="bg-muted/10 rounded-lg overflow-hidden border border-border flex items-center justify-center aspect-square">
                    <img src="{{ $this->viewingMedia->getVariantUrl('lg') }}" alt="{{ $this->viewingMedia->file_name }}" class="w-full h-full object-contain">
                </div>

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
                                       <x-ui.button title="Скопировать ссылку" @click="navigator.clipboard.writeText('{{ $variantUrl }}'); $wire.dispatch('show-toast', { type: 'success', message: 'Путь скопирован: {{ $key }}' })" variant="outline" size="icon-xs">
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
    @endif

    <script>
        document.addEventListener('livewire:navigated', () => {
            if (typeof Fancybox !== 'undefined') {
                Fancybox.defaults.Hash = false; 
                Fancybox.bind('[data-fancybox]'); 
            }
        });
    </script>
</div>