<?php

use App\Models\Media;
use Livewire\WithFileUploads;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithFileUploads;
    use WithPagination; 

    public bool $isVisible = false;
    public \App\Enums\MediaCollection $collection = \App\Enums\MediaCollection::Default;
    
    // ФИКС: Флаг, указывающий, заблокирована ли коллекция (передана родителем)
    public bool $isCollectionForced = false;
    
    public $files = [];
    public string $search = '';

    #[On('open-media-manager')]
    public function openModal(string $collection = 'default'): void
    {
        // Если коллекция передана и она не 'default', значит мы открыли из конкретного раздела
        $this->isCollectionForced = $collection !== 'default';
        $this->collection = \App\Enums\MediaCollection::tryFrom($collection) ?? \App\Enums\MediaCollection::Default;
        
        $this->isVisible = true;
        $this->search = '';
    }

    public function closeModal(): void
    {
        $this->isVisible = false;
        $this->files = [];
    }

    public function updatedFiles(): void
    {
        $maxSize = $this->collection->maxFileSizeKb();
        
        $this->validate([
            'files.*' => "file|max:{$maxSize}",
        ]);

        foreach ($this->files as $file) {
            try {
                Media::createFromFile($file, $this->collection, auth()->id());
            } catch (\Exception $e) {
                $this->dispatch('show-toast', type: 'error', message: 'Ошибка загрузки: ' . $e->getMessage());
            }
        }

        $this->reset('files');
        $this->dispatch('show-toast', type: 'success', message: 'Файлы загружены на склад!');
        
        // Оповещаем родительский компонент (например, страницу хранилища), чтобы он обновил свою сетку
        $this->dispatch('media-updated');
        
        unset($this->mediaItems);
    }

    public function selectMedia(int $id): void
    {
        $media = Media::find($id);
        if (!$media) return;

        $this->dispatch('media-selected', mediaId: $media->id, diskPath: $media->disk_path, collection: $this->collection->value);
        $this->closeModal();
    }

    public function deleteMedia(int $id): void
    {
        $media = Media::find($id);
        if ($media) {
            $media->safeDelete();
            $this->dispatch('show-toast', type: 'success', message: 'Файл удален');
            $this->dispatch('media-updated');
            unset($this->mediaItems);
        }
    }

    // При смене коллекции в селекте (если она не заблокирована) сбрасываем поиск
    public function updatedCollection(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    #[Computed]
    public function mediaItems()
    {
        return Media::query()
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
}; 
?>

<div x-data="{ open: @entangle('isVisible') }" 
     x-show="open" 
     x-cloak
     x-transition.opacity
     @click.self="open = false"
     @keydown.escape.window="open = false"
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
     
    <div x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         class="relative bg-card border border-border rounded-lg shadow-2xl max-w-5xl w-full mx-4 overflow-hidden flex flex-col max-h-[90vh]">
         
                <!-- Шапка менеджера -->
        <div class="flex items-center justify-between p-4 border-b border-border">
            <div class="flex items-center gap-3 flex-wrap">
                <h2 class="text-lg font-semibold flex items-center gap-2">
                    <x-lucide-image class="w-5 h-5" /> Медиа хранилище                
                </h2>
                
                {{-- ФИКС: Динамический селект коллекции (показывается только если не передан жестко) --}}
                @if(!$isCollectionForced)
                    <x-ui.select wire:model.live="collection">
                        <x-ui.select-trigger  class="w-40"><x-ui.select-value placeholder="Коллекция" /></x-ui.select-trigger>
                        <x-ui.select-content>
                            @foreach(\App\Enums\MediaCollection::cases() as $case)
                                <x-ui.select-item value="{{ $case->value }}">{{ $case->label() }}</x-ui.select-item>
                            @endforeach
                        </x-ui.select-content>
                    </x-ui.select>
                @else
                    {{-- Если открыли из раздела подарков, просто показываем бейдж --}}
                    <span class="text-xs text-muted-foreground font-normal px-2 py-1 rounded bg-muted border border-border">
                        Коллекция: {{ $collection->label() }}
                    </span>
                @endif

                {{-- НОВОЕ: Подсказка с правилами обработки картинки --}}
                <span class="text-xs text-blue-500 bg-blue-500/10 px-2 py-2 rounded flex items-center gap-1.5 border border-blue-500/20">
                    <x-lucide-info class="w-3.5 h-3.5" />
                    {{ $collection->dimensionsHint() }}
                </span>
            </div>

            <x-ui.button variant="ghost" size="icon-sm" @click="open = false"><x-lucide-x class="w-5 h-5" /></x-ui.button>
        </div>

        <!-- Тулбар: Поиск и Загрузка -->
        <div class="p-4 border-b border-border flex items-center gap-3 flex-wrap">           
            
            <div class="relative w-64">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="По названию или id..." class="pl-9 pr-8" />
                <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            </div>

            <div class="ml-auto">
                <x-ui.button type="button" variant="default" size="sm" @click="$refs.fileInput.click()">
                    <x-lucide-upload class="w-4 h-4" /> Загрузить в «{{ $collection->label() }}»
                </x-ui.button>
                <input x-ref="fileInput" type="file" wire:model="files" multiple class="hidden" accept="image/*,video/*">
            </div>

            <!-- Спиннер загрузки -->
            <div wire:loading wire:target="files" class="text-xs text-primary flex items-center gap-2 w-full">
                <x-lucide-loader-2 class="w-3 h-3 animate-spin inline" /> Идет оптимизация и загрузка файлов...
            </div>
        </div>

                  <!-- Сетка галереи -->
        <div class="p-4 overflow-y-auto max-h-[calc(100vh-5rem)] flex-1 bg-muted/10">
            @if($this->mediaItems->isEmpty())
                <div class="flex flex-col items-center justify-center py-16 text-muted-foreground">
                    <x-lucide-image-off class="w-12 h-12 opacity-30 mb-2" />
                    <p>Медиа-хранилище пусто. Загрузите первый файл!</p>
                </div>
            @else
                {{-- ФИКС: Сетка до 6 колонок на больших экранах --}}
                <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-3">
                    @foreach($this->mediaItems as $media)
                        <div wire:key="media-{{ $media->id }}" class="relative group aspect-square rounded-lg overflow-hidden border border-border bg-background">
                            <button wire:click="selectMedia({{ $media->id }})" class="block w-full h-full" title="Выбрать: {{ $media->file_name }}">
                                @if($media->type === 'image')
                                    <img src="{{ $media->url }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform">
                                @else
                                    <div class="w-full h-full flex items-center justify-center bg-muted">
                                        <x-lucide-video class="w-8 h-8 text-muted-foreground" />
                                    </div>
                                @endif
                            </button>
                            
                            {{-- НОВОЕ: Бейдж ID в левом верхнем углу --}}
                            <div class="absolute top-1 left-1 bg-black/60 text-white text-[10px] font-mono font-bold px-1.5 py-0.5 rounded backdrop-blur-sm">
                                #{{ $media->id }}
                            </div>

                            {{-- Имя файла внизу картинки (появляется при наведении) --}}
                            <div class="absolute bottom-0 left-0 right-0 bg-black/70 text-white text-[10px] px-1 py-0.5 truncate opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                                {{ $media->file_name }}
                            </div>

                            <!-- Кнопка удаления (в правом верхнем углу) -->
                            <button wire:click="deleteMedia({{ $media->id }})" wire:confirm="Удалить файл навсегда?" class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition-opacity bg-destructive/80 hover:bg-destructive text-white p-1 rounded-sm">
                                <x-lucide-trash-2 class="w-3 h-3" />
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
        <!-- Пагинация -->
        @if($this->mediaItems->hasPages())
            <div class="p-3 border-t border-border bg-card">
                {{ $this->mediaItems->links('partials.pagination') }}
            </div>
        @endif
    </div>
</div>