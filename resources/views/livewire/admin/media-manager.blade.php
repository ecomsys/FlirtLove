<?php

use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithFileUploads;
    use WithPagination;

    /** @var bool Видимость модального окна */
    public bool $isVisible = false;
    
    /** @var \App\Enums\MediaCollection Текущая коллекция для загрузки/просмотра */
    public \App\Enums\MediaCollection $collection = \App\Enums\MediaCollection::Default;
    
    /** @var bool Флаг жесткой привязки к коллекции (скрывает селект切换ения) */
    public bool $isCollectionForced = false;
    
    /** @var array Загружаемые файлы (Livewire Temporary) */
    public $files = [];
    
    /** @var string Строка поиска по имени или ID */
    public string $search = '';
    
    /** @var int|null ID выбранной картинки (для подсветки и подтверждения) */
    public ?int $selectedMediaId = null;

    /**
     * Слушатель открытия менеджера.
     * Определяет, можно ли менять коллекцию (если передана 'default').
     */
    #[On('open-media-manager')]
    public function openModal(string $collection = 'default'): void
    {
        $this->isCollectionForced = $collection !== 'default';
        $this->collection = \App\Enums\MediaCollection::tryFrom($collection) ?? \App\Enums\MediaCollection::Default;
        
        $this->isVisible = true;
        $this->search = '';
        $this->selectedMediaId = null;
    }

    /**
     * Закрытие модалки и оповещение родительского компонента об обновлении сетки.
     */
    public function closeModal(): void
    {
        $this->isVisible = false;
        $this->files = [];
        $this->selectedMediaId = null;
        $this->dispatch('media-updated');
    }

    /**
     * Обработка загруженных файлов: валидация, сохранение во temp и отправка в очередь.
     */
        public function updatedFiles(): void
    {
        // ФИКС: Защита от двойного срабатывания при очистке массива files
        if (empty($this->files)) {
            return;
        }

        $maxSize = config("media.collections.{$this->collection->value}.max_file_size_kb", 5120);
        
        $this->validate([
            'files.*' => "file|mimes:jpg,jpeg,png,webp,gif|max:{$maxSize}",
        ]);

        foreach ($this->files as $file) {
            try {
                $tempPath = $file->store('media/temp', 'public');
                
                $media = Media::create([
                    'collection' => $this->collection->value,
                    'file_name' => $file->getClientOriginalName(),
                    'disk_path' => $tempPath,
                    'url' => Storage::url($tempPath),
                    'type' => 'image',
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'uploaded_by' => auth()->id(),
                    'variants' => null,
                ]);

                \App\Jobs\ProcessMediaUploadJob::dispatch(
                    $media->id, 
                    $tempPath, 
                    $this->collection, 
                    $file->getClientOriginalName()
                );

            } catch (\Exception $e) {
                $this->dispatch('show-toast', type: 'error', message: 'Ошибка загрузки: ' . $e->getMessage());
            }
        }

        $this->reset('files');
        $this->dispatch('show-toast', type: 'success', message: 'Файлы загружены! Нарезка вариантов запущена в фоне.');
        
        $this->dispatch('media-updated');
        unset($this->mediaItems);
    }

    /**
     * Выбор картинки (Toggle: повторный клик снимает выделение).
     */
    public function selectMedia(int $id): void
    {
        $this->selectedMediaId = ($this->selectedMediaId === $id) ? null : $id;
    }

    /**
     * Подтверждение выбора (кнопка "Принять").
     * Защита: нельзя выбрать файл, пока воркер не нарежет variants.
     */
    public function confirmSelection(): void
    {
        if (!$this->selectedMediaId) return;

        $media = Media::find($this->selectedMediaId);
        
        // ФИКС: Запрещаем выбирать не картинки или картинки без вариантов
        if (!$media || $media->type !== 'image' || empty($media->variants)) {
            $this->dispatch('show-toast', type: 'error', message: 'Файл еще обрабатывается. Дождитесь окончания нарезки.');
            $this->selectedMediaId = null;
            return;
        }

        $this->dispatch('media-selected', mediaId: $media->id, diskPath: $media->disk_path, collection: $this->collection->value);
        $this->closeModal();
    }

    /**
     * Удаление файла (вызывает safeDelete в модели, который чистит варианты).
     */
    public function deleteMedia(int $id): void
    {
        $media = Media::find($id);
        if ($media) {
            if ($this->selectedMediaId === $id) {
                $this->selectedMediaId = null;
            }
            
            $media->safeDelete();
            $this->dispatch('show-toast', type: 'success', message: 'Файл удален');
            $this->dispatch('media-updated');
            unset($this->mediaItems);
        }
    }

    /**
     * Сброс поиска и пагинации при смене коллекции в селекте.
     */
    public function updatedCollection(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    /**
     * Получение списка файлов со склада с пагинацией и поиском.
     */
    #[Computed]
    public function mediaItems()
    {
        return Media::query()
            ->where('type', 'image') // ФИКС: Тянем только картинки
            ->where('collection', $this->collection->value)
            ->when($this->search, function ($q) {
                $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
                $search = $this->search;
                
                $q->where(function ($q) use ($search, $operator) {
                    $q->where('file_name', $operator, "%{$search}%");
                    if (is_numeric($search)) {
                        $q->orWhere('id', (int) $search);
                    }
                });
            })
            ->latest()
            ->paginate(24);
    }

    /**
     * Вычисление правил кропа для текущей коллекции (для HoverCard).
     */
    #[Computed]
    public function cropRules(): array
    {
        $config = config("media.collections.{$this->collection->value}");
        
        $variants = [];
        if (isset($config['variants'])) {
            foreach ($config['variants'] as $key => $variant) {
                $variants[] = [
                    'key' => $key,
                    'size' => $variant['size'],
                    'fit' => $variant['fit'],
                    'format' => $variant['format'],
                    'quality' => $variant['quality'] ?? 80,
                ];
            }
        }

        return [
            'keep_original' => $config['keep_original'] ?? false,
            'alpha' => $config['alpha'] ?? false,
            'max_size_mb' => round(($config['max_file_size_kb'] ?? 5120) / 1024, 1),
            'variants' => $variants,
        ];
    }
}; 
?>

<div wire:key="media-manager-root">
    @if($isVisible)
    {{-- Используем @click.self на фоне, чтобы не блокировать всплытие событий для контекстного меню --}}
    <div wire:key="media-manager-modal"
         x-data
         @click.self="$wire.closeModal()"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
         
        <div x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             class="relative bg-card border border-border rounded-lg shadow-2xl max-w-5xl w-full mx-4 overflow-hidden flex flex-col max-h-[90vh]">
             
            <!-- Шапка менеджера -->
            <div class="flex items-center justify-between p-4 border-b border-border">
                <h2 class="text-lg font-semibold flex items-center gap-2">
                    <x-lucide-image class="w-5 h-5" /> Медиа хранилище 
                    @if($isCollectionForced)
                        <span class="text-muted-foreground font-normal">({{ $collection->label() }})</span>
                    @else
                        <span class="text-muted-foreground font-normal">(выберите коллекцию для действия)</span>
                    @endif            
                </h2>          
                <x-ui.button variant="ghost" size="icon-sm" wire:click="closeModal"><x-lucide-x class="w-5 h-5" /></x-ui.button>
            </div>

            <!-- Тулбар: Поиск и Загрузка -->
            <div class="p-4 border-b border-border flex items-center gap-3 flex-wrap">                     
                <div class="flex items-center gap-2">
                    @if(!$isCollectionForced)
                        <x-ui.select wire:model.live="collection">
                            <x-ui.select-trigger class="w-46"><x-ui.select-value placeholder="Коллекция" /></x-ui.select-trigger>
                            <x-ui.select-content>
                                @foreach(\App\Enums\MediaCollection::cases() as $case)
                                    <x-ui.select-item value="{{ $case->value }}">{{ $case->label() }}</x-ui.select-item>
                                @endforeach
                            </x-ui.select-content>
                        </x-ui.select>
                    @else
                        <span class="text-xs text-muted-foreground font-normal px-2 py-1 rounded bg-muted border border-border">
                            Коллекция: {{ $collection->label() }}
                        </span>
                    @endif

                    <x-ui.hover-card wire:key="crop-rules-{{ $collection->value }}">
                        <x-ui.hover-card-trigger>
                            <button class="text-xs text-blue-500 hover:underline flex items-center gap-1 cursor-pointer">
                                <x-lucide-scissors class="w-3.5 h-3.5" />
                                показать правила кропа !
                            </button>
                        </x-ui.hover-card-trigger>
                        <x-ui.hover-card-content class="w-80">
                            <div class="space-y-2 p-2">
                                <h4 class="text-sm font-semibold">Правила для «{{ $collection->label() }}»</h4>
                                <div class="text-xs text-muted-foreground space-y-1">
                                    <div class="flex justify-between">
                                        <span>Макс. размер файла:</span>
                                        <span class="font-medium text-foreground">{{ $this->cropRules['max_size_mb'] }} MB</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Альфа-канал (прозрачность):</span>
                                        <span class="font-medium text-foreground">{{ $this->cropRules['alpha'] ? 'Да' : 'Нет' }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Сохранять оригинал:</span>
                                        <span class="font-medium text-foreground">{{ $this->cropRules['keep_original'] ? 'Да' : 'Нет' }}</span>
                                    </div>
                                    <div class="pt-2 mt-2 border-t border-border">
                                        <p class="font-medium text-foreground mb-1">Варианты нарезки:</p>
                                        <ul class="space-y-1">
                                            @foreach($this->cropRules['variants'] as $variant)
                                                <li class="flex justify-between gap-2">
                                                    <span class="font-mono text-[10px] bg-muted px-1 rounded">{{ $variant['key'] }}</span>
                                                    <span>{{ $variant['size'] }} ({{ $variant['fit'] }}, {{ $variant['format'] }} {{ $variant['quality'] }}%)</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </x-ui.hover-card-content>
                    </x-ui.hover-card>
                </div>

                <div class="ml-auto inline-flex items-center gap-2">
                    <div class="relative w-64">
                        <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="По названию или id..." class="pl-9 pr-8" />
                        <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                    </div>

                    <x-ui.button type="button" variant="default" size="sm" @click="$refs.fileInput.click()">
                        <x-lucide-upload class="w-4 h-4" /> Загрузить в «{{ $collection->label() }}»
                    </x-ui.button>
                    <input x-ref="fileInput" type="file" wire:model="files" multiple class="hidden" accept="image/*">
                </div>

                <div wire:loading wire:target="files" class="text-xs text-primary flex items-center gap-2 w-full">
                    <x-lucide-loader-2 class="w-3 h-3 animate-spin inline" /> Идет оптимизация и загрузка файлов...
                </div>
                
                @error('files.*')
                    <div class="text-xs text-destructive bg-destructive/10 px-3 py-2 rounded-md w-full flex items-center gap-2">
                        <x-lucide-alert-circle class="w-4 h-4" />
                        <span>{{ $message }}</span>
                    </div>
                @enderror
            </div>
            
            <!-- Сетка галереи с Поллингом и Контекстным меню -->
            @php 
                $hasProcessing = $this->mediaItems->contains(fn($m) => $m->type === 'image' && empty($m->variants));
            @endphp

                        <div x-data="{ activeMedia: { id: null, url: '' } }" 
                 class="p-4 overflow-y-auto min-h-[15rem] max-h-[calc(100vh-10rem)] flex-1 bg-muted/10"
                 @if($hasProcessing) wire:poll.3s @endif>
                
                @php 
                    // Вычисляем пропорции тумбнейла для текущей коллекции
                    $thumbConfig = collect($this->cropRules['variants'])->firstWhere('key', 'thumb');
                    $thumbSize = $thumbConfig['size'] ?? '300x300';
                    $parts = explode('x', strtolower($thumbSize));
                    $ratioW = (int) rtrim($parts[0] ?? '300', 'w');
                    // Если указан только один размер (например '800w'), делаем квадрат
                    $ratioH = isset($parts[1]) ? (int) $parts[1] : $ratioW;
                @endphp

                @if($this->mediaItems->isEmpty())
                    <div class="flex flex-col items-center justify-center py-12 text-muted-foreground">
                        <x-lucide-image-off class="w-12 h-12 opacity-30 mb-2" />
                        <p>Медиа-хранилище пусто. <br>Загрузите первый файл!</p>
                    </div>
                @else
                    <x-ui.context-menu>
                        <x-ui.context-menu-trigger asChild>
                            <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-3">
                                @foreach($this->mediaItems as $media)
                                   @php $isProcessing = ($media->type === 'image' && empty($media->variants)); @endphp
                                    <div wire:click="{{ $isProcessing ? '' : 'selectMedia(' . $media->id . ')' }}" 
                                         wire:key="media-{{ $media->id }}" 
                                         @contextmenu.prevent="activeMedia = { id: {{ $media->id }}, url: '{{ asset($media->url) }}' }"
                                         class="relative group rounded-lg overflow-hidden transition-all {{ $isProcessing ? 'opacity-50 cursor-wait' : 'cursor-pointer ' . ($selectedMediaId === $media->id ? 'ring-2 ring-primary ring-offset-2 ring-offset-card border-transparent' : 'border border-border hover:border-primary/50') }} bg-background" 
                                         style="aspect-ratio: {{ $ratioW }} / {{ $ratioH }};">                                        
                                        
                                        <div class="w-full h-full block" title="{{ $isProcessing ? 'Идет обработка...' : 'Выбрать: ' . $media->file_name }}">
                                            <x-media-image src="{{ $media->getVariantUrl('thumb') }}" class="w-full h-full object-cover {{ $selectedMediaId === $media->id ? 'opacity-90' : 'group-hover:scale-110 transition-transform' }}"/>
                                        </div>
                                        
                                        @if($isProcessing)
                                            <div class="absolute inset-0 flex items-center justify-center bg-black/30 pointer-events-none">
                                                <x-lucide-loader-2 class="w-6 h-6 text-white animate-spin" />
                                            </div>
                                        @endif

                                        <div class="absolute top-1 left-1 bg-black/60 text-white text-[10px] font-mono font-bold px-1.5 py-0.5 rounded backdrop-blur-sm">
                                            #{{ $media->id }}
                                        </div>

                                        @if(!$isProcessing && $selectedMediaId === $media->id)
                                            <div class="absolute inset-0 flex items-center justify-center bg-primary/20 pointer-events-none">
                                                <div class="bg-primary text-primary-foreground rounded-full p-1.5">
                                                    <x-lucide-check class="w-5 h-5" />
                                                </div>
                                            </div>
                                        @endif

                                        <div class="absolute bottom-0 left-0 right-0 bg-black/70 text-white text-[10px] px-1 py-0.5 truncate opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                                            {{ $media->file_name }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </x-ui.context-menu-trigger>

                        {{-- TallStackUI автоматически закрывает меню при wire:click --}}
                        <x-ui.context-menu-content class="w-56">
                            <x-ui.context-menu-item wire:click="selectMedia(activeMedia.id)">
                                <x-lucide-check class="w-4 h-4 mr-2" /> Выбрать
                            </x-ui.context-menu-item>
                            
                            <x-ui.context-menu-item @click="navigator.clipboard.writeText(activeMedia.url); $wire.dispatch('show-toast', {type: 'success', message: 'URL скопирован'});">
                                <x-lucide-link class="w-4 h-4 mr-2" /> Копировать URL
                            </x-ui.context-menu-item>

                            <x-ui.context-menu-separator />

                            <x-ui.context-menu-item variant="destructive" wire:click="deleteMedia(activeMedia.id)" wire:confirm="Удалить файл навсегда?">
                                <x-lucide-trash-2 class="w-4 h-4 mr-2" /> Удалить
                            </x-ui.context-menu-item>
                        </x-ui.context-menu-content>
                    </x-ui.context-menu>
                @endif
            </div>

            <!-- Пагинация -->
            @if($this->mediaItems->hasPages())
                <div class="p-3 border-t border-border bg-card shrink-0">
                    {{ $this->mediaItems->links('partials.pagination') }}
                </div>
            @endif

            <!-- Футер с кнопкой подтверждения -->
            <div class="p-4 border-t border-border bg-muted/20 flex items-center justify-end gap-2 shrink-0">
                <x-ui.button variant="outline" size="sm" wire:click="closeModal">Отмена</x-ui.button>
                
                <x-ui.button wire:click="confirmSelection" variant="default" size="sm" wire:loading.attr="disabled" wire:target="confirmSelection" class="{{ $selectedMediaId ? '' : 'opacity-50 cursor-not-allowed pointer-events-none' }}">
                    <span wire:loading.remove wire:target="confirmSelection"><x-lucide-check class="w-4 h-4 mr-1 inline" /> Выбрать</span>
                    <span wire:loading wire:target="confirmSelection" class="flex items-center gap-2">
                        <x-lucide-loader-2 class="w-4 h-4 animate-spin inline" /> Выбор...
                    </span>
                </x-ui.button>
            </div>
        </div>
    </div>
    @endif
</div>