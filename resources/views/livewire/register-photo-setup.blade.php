<?php

use App\Models\Photo;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

new #[Layout('layouts.onboarding')] class extends Component 
{
    use WithFileUploads;

    public array $photos = [];
    public array $newPhotos = [];
    public array $intimateFlags = [];
    public bool $showModal = false;
    
    public $existingPhotos = [];

    public function mount(): void
    {
        $this->existingPhotos = Auth::user()->photos()->orderBy('is_primary', 'desc')->get()->toArray();
    }

    protected function rules(): array
    {
        return [
            'photos.*' => 'image|mimes:jpg,jpeg,png,webp|min:10|max:5120',
            'newPhotos.*' => 'image|mimes:jpg,jpeg,png,webp|min:10|max:5120',
        ];
    }

    public function updatedNewPhotos(): void
    {
        if (empty($this->newPhotos)) {
            return;
        }

        $validFiles = [];
        $errors = [];
        $existingNames = collect($this->photos)
            ->filter(fn ($file) => is_object($file) && method_exists($file, 'getClientOriginalName'))
            ->map(fn ($file) => $file->getClientOriginalName())
            ->toArray();

        foreach ($this->newPhotos as $file) {
            $fileName = $file->getClientOriginalName();

            if (in_array($fileName, $existingNames)) {
                $errors[] = __('common.photo_file_exists', ['name' => $fileName]);
                continue;
            }

            $validator = \Illuminate\Support\Facades\Validator::make(
                ['file' => $file],
                ['file' => 'image|mimes:jpg,jpeg,png,webp|min:10|max:5120'],
                [
                    'file.image' => __('common.photo_must_be_image'),
                    'file.mimes' => __('common.photo_invalid_format'),
                    'file.min' => __('common.photo_too_small'),
                    'file.max' => __('common.photo_too_large'),
                ],
                ['file' => $fileName],
            );

            if ($validator->fails()) {
                $errors[] = $validator->errors()->first('file');
            } else {
                $validFiles[] = $file;
                $existingNames[] = $fileName;
            }
        }

        if (!empty($errors)) {
            foreach (array_unique($errors) as $errorMessage) {
                $this->dispatch('show-toast', type: 'error', message: $errorMessage);
            }
            $this->newPhotos = $validFiles;
            if (empty($validFiles)) return;
        }

        $addedCount = 0;
        $skippedCount = 0;

        foreach ($validFiles as $newFile) {
            $fileName = $newFile->getClientOriginalName();
            $isDuplicate = collect($this->photos)->contains(function ($existingFile) use ($fileName) {
                return is_object($existingFile) && method_exists($existingFile, 'getClientOriginalName') && $existingFile->getClientOriginalName() === $fileName;
            });

            if (!$isDuplicate) {
                $this->photos[] = $newFile;
                $this->intimateFlags[] = false;
                $addedCount++;
            } else {
                $skippedCount++;
            }
        }

        if ($addedCount > 0 || !empty($this->photos)) {
            $this->showModal = true;
        }

        if ($addedCount > 0) $this->dispatch('show-toast', type: 'success', message: __('common.photo_added', ['count' => $addedCount]));
        if ($skippedCount > 0) $this->dispatch('show-toast', type: 'warning', message: __('common.photo_duplicates_skipped', ['count' => $skippedCount]));

        $this->reset('newPhotos');
    }

    public function removePhoto(int $index): void
    {
        if (isset($this->photos[$index])) {
            unset($this->photos[$index]);
            $this->photos = array_values($this->photos);
        }
        if (isset($this->intimateFlags[$index])) {
            unset($this->intimateFlags[$index]);
            $this->intimateFlags = array_values($this->intimateFlags);
        }
        
        if (empty($this->photos) && empty($this->existingPhotos)) {
            $this->showModal = false;
        }
    }
    
    // public function removeExistingPhoto(int $photoId): void
    // {
    //     $photo = Photo::find($photoId);
    //     if ($photo && $photo->user_id === Auth::id()) {
    //         \Illuminate\Support\Facades\Storage::disk('public')->delete($photo->path);
    //         $photo->delete();
            
    //         $this->existingPhotos = collect($this->existingPhotos)
    //             ->filter(function ($p) use ($photoId) {
    //                 return is_array($p) && isset($p['id']) && $p['id'] !== $photoId;
    //             })
    //             ->values()
    //             ->toArray();
            
    //         $this->dispatch('show-toast', type: 'success', message: __('common.photo_deleted'));
            
    //         if (empty($this->existingPhotos) && empty($this->photos)) {
    //             $this->showModal = false;
    //         }
    //     }
    // }

    public function removeExistingPhoto(int $photoId): void
    {
        $photo = Photo::find($photoId);
        if ($photo && $photo->user_id === Auth::id()) {
            $wasPrimary = $photo->is_primary; // Запоминаем, было ли оно главным
            
            \Illuminate\Support\Facades\Storage::disk('public')->delete($photo->path);
            $photo->delete();
            
            // Если удалили главную, делаем главное другое фото
            if ($wasPrimary) {
                $nextPhoto = Auth::user()->photos()->first();
                if ($nextPhoto) {
                    $nextPhoto->update(['is_primary' => true]);
                }
            }
            
            // Обновляем массив в интерфейсе
            $this->existingPhotos = Auth::user()->photos()->orderBy('is_primary', 'desc')->get()->toArray();
            
            $this->dispatch('show-toast', type: 'success', message: __('common.photo_deleted'));
            
            if (empty($this->existingPhotos) && empty($this->photos)) {
                $this->showModal = false;
            }
        }
    }
    

    public function getTotalSizeProperty(): string
    {
        $bytes = collect($this->photos)->sum(function ($file) {
            if (is_object($file) && method_exists($file, 'getSize')) return $file->getSize();
            return 0;
        });

        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        return max(0, round($bytes / 1024, 2)) . ' KB';
    }


    public function save(): void
    {
        $validator = \Illuminate\Support\Facades\Validator::make(
            ['photos' => $this->photos],
            ['photos.*' => 'image|mimes:jpg,jpeg,png,webp|min:10|max:5120'],
            [
                'photos.*.image' => __('common.photo_must_be_image'),
                'photos.*.mimes' => __('common.photo_invalid_format'),
                'photos.*.min' => __('common.photo_too_small'),
                'photos.*.max' => __('common.photo_too_large'),
            ],
            ['photos.*' => 'фото'],
        );

        if ($validator->fails()) {
            $this->dispatch('show-toast', type: 'error', message: __('common.check_all_photos'));
            return;
        }

        if (empty($this->photos) && empty($this->existingPhotos)) {
            $this->dispatch('show-toast', type: 'error', message: __('common.add_at_least_one_photo'));
            return;
        }

        foreach ($this->existingPhotos as $existingPhoto) {
            $photo = Photo::find($existingPhoto['id']);
            if ($photo && $photo->user_id === Auth::id()) {
                $photo->update([
                    'is_intimate' => filter_var($existingPhoto['is_intimate'] ?? false, FILTER_VALIDATE_BOOLEAN)
                ]);
            }
        }

        $hasPrimary = collect($this->existingPhotos)->contains(fn ($p) => filter_var($p['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN));
        $isFirstPhoto = !$hasPrimary;

        // Внутри save()
        foreach ($this->photos as $index => $photo) {
            if ($photo instanceof TemporaryUploadedFile) {
                // Сохраняем оригинал во временную папку модерации
                $path = $photo->store('photos/pending', 'public');

                Photo::create([
                    'user_id' => Auth::id(),
                    'path' => $path, // Пока это оригинал
                    'is_primary' => $isFirstPhoto && $index === 0,
                    'is_intimate' => filter_var($this->intimateFlags[$index] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'status' => 'pending', // Ждет модерации
                ]);
                
                $isFirstPhoto = false;
            }
        }
        
        Auth::user()->update(['has_completed_onboarding' => true]);

        $this->dispatch('show-toast', type: 'success', message: __('common.photos_uploaded_success'));
        $this->redirect(route('verification.notice'), navigate: true);
    }

    public function skip(): void
    {
        Auth::user()->update(['has_completed_onboarding' => true]);
        $this->redirect(route('verification.notice'), navigate: true);
    }
}; ?>

<div class="max-w-5xl mx-auto px-4 py-12 md:py-20">

    <!-- Две колонки на десктопе -->
    <div class="grid md:grid-cols-2 gap-12 items-center">

        <!-- ЛЕВАЯ КОЛОНКА (Информация) -->
        <div class="max-w-[21rem] mx-auto">
        <label for="main-photo-input" class="relative w-54 h-54 mb-10 mx-auto md:mx-[initial] block cursor-pointer group">
            
            <div class="absolute -inset-2 rounded-full border-4 border-dashed border-primary/40 group-hover:border-primary group-hover:animate-spin group-hover:[animation-duration:100s] transition-colors"></div>

            <div class="absolute inset-0 rounded-full bg-muted flex items-center justify-center border-4 border-border group-hover:bg-accent transition-colors">
                <svg class="w-20 h-20 text-muted-foreground group-hover:text-primary transition-colors" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                </svg>
            </div>
        </label>

            <div class="flex gap-9 mb-8 justify-center md:justify-start">
                <div class="relative inline-flex">
                    <x-avatar src="https://i.pravatar.cc/150?img=1&blur=5" name="Math" size="lg" />
                    <span class="border-background bg-red-500 text-white absolute -end-1 -bottom-1 flex size-5 items-center justify-center rounded-full border-2">
                        <svg class="size-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </span>
                </div>
                <div class="relative inline-flex">
                    <x-avatar src="https://i.pravatar.cc/150?img=2&dark=1" name="Iren" size="lg" />
                    <span class="border-background bg-red-500 text-white absolute -end-1 -bottom-1 flex size-5 items-center justify-center rounded-full border-2">
                        <svg class="size-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </span>
                </div>
                <div class="relative inline-flex">
                    <x-avatar src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&h=150&fit=crop&crop=face&auto=format" name="Joe" size="lg" />
                    <span class="border-background bg-green-500 text-white absolute -end-1 -bottom-1 flex size-5 items-center justify-center rounded-full border-2">
                        <svg class="size-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    </span>
                </div>
            </div>

            <ul class="space-y-1 text-sm text-muted-foreground inline-block text-left mx-auto">
                <li class="flex items-start gap-3">
                    <span>·</span>
                    <span>{{ __('common.rule_no_other_people') }}</span>
                </li>
                <li class="flex items-start gap-3">
                    <span>·</span>
                    <span>{{ __('common.rule_no_indecent') }}</span>
                </li>
                <li class="flex items-start gap-3">
                    <span>·</span>
                    <span>{{ __('common.rule_clear_face') }}</span>
                </li>
            </ul>
        </div>


        <!-- ПРАВАЯ КОЛОНКА (Загрузка) -->
        <div class="bg-card border border-border rounded-xl p-8 shadow-sm">
            <h1 class="text-2xl font-semibold text-foreground mb-2">{{ __('common.upload_photos') }}</h1>
            <p class="text-muted-foreground mb-8">{{ __('common.upload_photos_desc') }}</p>               

            <label for="main-photo-input" class="cursor-pointer w-full inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors h-12 px-8 bg-primary text-primary-foreground hover:bg-primary/90 shadow-lg shadow-primary/20">
                {{ __('common.select_photos') }}
            </label>
            <input id="main-photo-input" type="file" wire:model.live="newPhotos"
                wire:key="main-file-{{ count($photos) }}" class="hidden" accept="image/jpeg, image/png, image/webp"
                multiple>

            <div class="mt-8 pt-8 border-t border-border">
                <p class="text-sm text-center text-muted-foreground mb-4">{{ __('common.or_upload_from_social') }}</p>
                <div class="flex justify-center gap-4">

                    <x-ui.button class="text-white bg-[#0077FF] hover:bg-[#3493ff]">
                        <x-slot:before>
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000">
                                <path fill="currentColor"
                                    d="M196.031.063C88.252.063.062 88.253.062 196.032V804.22c0 107.779 88.19 195.969 195.969 195.969h608.188c107.779 0 195.969-88.19 195.969-195.969V196.032c0-107.779-88.19-195.969-195.969-195.969zm112.188 225.656H505.5c40.972 0 72.094 1.499 93.219 4.719c21.125 3.059 40.968 9.616 59.406 19.594c19.953 10.749 34.941 25.224 44.813 43.156c9.87 18.038 14.875 38.771 14.875 62.344c0 27.244-6.798 51.461-20.5 72.719c-13.57 21.258-32.399 36.89-56.344 47v2.906c34.454 7.317 62.087 22.036 82.813 44.438c20.757 22.428 31.125 52.618 31.125 90.531c0 27.75-5.239 52.227-15.75 73.219c-10.507 20.992-24.577 38.29-42.375 52.125c-20.992 16.496-44.033 28.137-69.281 35.188c-25.089 7.024-56.959 10.531-95.75 10.531H308.22V225.72zM447.75 328.094v118.188h17.656c23.945 0 40.864-.246 50.469-.75s19.601-3.077 29.844-7.813c11.121-5.241 18.953-12.518 23.156-21.75c4.23-9.339 6.406-20.123 6.406-32.281c0-9.072-2.308-18.31-6.938-27.781c-4.603-9.472-11.754-16.395-21.625-20.625c-9.206-4.097-20.091-6.257-32.781-6.656c-12.664-.4-31.482-.531-56.438-.531zm0 213.844v139.813h7.563c36.503 0 61.715-.275 75.656-.781c13.941-.504 28.303-3.815 42.75-10.094c12.824-5.507 22.031-13.577 27.938-24.219c5.88-10.616 8.813-22.658 8.813-36.094c0-17.294-3.418-30.731-10.469-40.469c-7.024-9.844-17.532-17.154-31.5-22.156c-8.461-3.326-20.248-5.128-35.094-5.5s-34.799-.5-59.781-.5z" />
                            </svg>
                        </x-slot:before>
                        {{ __('common.vk') }}
                    </x-ui.button>

                    <x-ui.button class="text-white bg-[#FF7700] hover:bg-[#fd9a42]">
                        <x-slot:before>
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000">
                                <path fill="currentColor"
                                    d="M140.719 0C62.769 0 0 62.769 0 140.719v718.563c0 77.95 62.769 140.719 140.719 140.719h718.563c77.95 0 140.719-62.769 140.719-140.719V140.719C1000.001 62.769 937.232 0 859.282 0zm381.688 102.188c113.484 0 205.75 92.304 205.75 205.781c0 113.468-92.266 205.719-205.75 205.719c-113.487 0-205.813-92.251-205.813-205.719c0-113.477 92.325-205.781 205.813-205.781m-2.188 120.563c-45.968 1.17-83.031 38.984-83.031 85.219c0 46.949 38.24 85.156 85.219 85.156c46.97 0 85.156-38.207 85.156-85.156c0-46.968-38.187-85.219-85.156-85.219c-.734 0-1.457-.019-2.188 0M352.031 520.689c10.9.037 21.897 3.036 31.813 9.281c84.228 52.962 192.808 52.997 277.063 0c28.21-17.765 65.391-9.237 83.156 18.969c17.745 28.159 9.221 65.389-18.969 83.125a385.4 385.4 0 0 1-119.469 49.469l115.063 115.063c23.555 23.506 23.555 61.709 0 85.25c-23.567 23.55-61.694 23.55-85.219 0L522.344 768.783l-113 113.063c-11.767 11.772-27.207 17.656-42.625 17.656c-15.434 0-30.822-5.9-42.625-17.656c-23.551-23.556-23.544-61.694 0-85.25l115-115.063c-41.878-9.548-82.286-26.122-119.5-49.469c-28.134-17.736-36.594-54.926-18.844-83.125c11.062-17.628 29.75-27.586 49.094-28.219a59 59 0 0 1 2.188-.031z" />
                            </svg>
                        </x-slot:before>
                        {{ __('common.ok') }}
                    </x-ui.button>
                </div>
            </div>

            <button wire:click="skip" wire:navigate class="underline block w-full text-center mt-6 text-sm text-primary hover:no-underline transition-colors">
                {{ __('common.skip_photos') }}
            </button>
        </div>
    </div>

    <!-- МОДАЛЬНОЕ ОКНО ПРЕДПРОСМОТРА -->
    <div x-cloak x-show="$wire.showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm" style="display: none;">
        <div x-show="$wire.showModal" x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95" @click.away="$wire.showModal = false"
            class="bg-card w-full max-w-2xl rounded-xl shadow-2xl border border-border flex flex-col max-h-[90vh]">
            
            <div class="flex items-center p-6 border-b border-border">
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-foreground">{{ __('common.photo_preview') }}</h3>
                    <p class="text-xs text-muted-foreground mt-1">
                        {{ __('common.photos_count', ['count' => count($this->photos), 'size' => $this->total_size]) }}
                    </p>
                </div>
                <div class="shrink-0">
                    <x-ui.button wire:click="save" wire:loading.attr="disabled" class="bg-primary text-primary-foreground hover:bg-primary/90">
                        <span wire:loading.remove wire:target="save">{{ __('common.save') }}</span>
                        <div wire:loading wire:target="save" class="whitespace-nowrap inline-flex items-center gap-3">
                            <x-lucide-loader-circle class="inline-block size-4 animate-spin shrink-0" />
                            <span>{{ __('common.loading') }}</span>
                        </div>
                    </x-ui.button>
                </div>
            </div>

            <div class="p-6 overflow-y-auto flex-1 space-y-6">
                
                @if (!empty($existingPhotos))
                <div>
                    <h4 class="text-sm text-muted-foreground uppercase font-semibold mb-3">{{ __('common.your_current_photos') }}</h4>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        @foreach ($existingPhotos as $index => $photo)
                            @if (!is_array($photo) || !isset($photo['id'])) @continue @endif
                            
                            <div wire:key="existing-{{ $photo['id'] }}" class="relative group border border-border rounded-lg overflow-hidden bg-muted">
                                <img src="{{ asset('storage/' . $photo['path']) }}" class="w-full h-40 object-cover" alt="Photo">
                                
                                @if ($photo['is_primary'])
                                    <span class="absolute top-2 left-2 bg-primary text-primary-foreground text-[10px] px-2 py-1 rounded">{{ __('common.avatar') }}</span>
                                @endif

                                <button wire:click="removeExistingPhoto({{ $photo['id'] }})" wire:loading.attr="disabled" class="absolute top-2 right-2 bg-destructive/90 hover:bg-destructive text-destructive-foreground rounded-full p-1.5 opacity-0 group-hover:opacity-100 transition-all shadow-sm">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>

                                <div class="absolute bottom-2 left-2 bg-background/90 backdrop-blur-sm px-2 py-1.5 rounded-md border border-border/50 inline-flex items-center gap-2">
                                    <x-ui.checkbox wire:model.live="existingPhotos.{{ $loop->index }}.is_intimate" id="existing-intimate-{{ $photo['id'] }}" />
                                    <x-ui.label for="existing-intimate-{{ $photo['id'] }}" class="text-xs font-medium cursor-pointer select-none">18+</x-ui.label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

                @if (count($this->photos) > 0)
                    <div>
                        @if (!empty($existingPhotos))
                            <h4 class="text-sm text-muted-foreground uppercase font-semibold mb-3">{{ __('common.new_photos') }}</h4>
                        @endif
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            @foreach ($this->photos as $index => $photo)
                                <div wire:key="photo-{{ is_object($photo) ? $photo->getFilename() : $index }}" class="relative group border border-border rounded-lg overflow-hidden bg-muted">
                                    @if (is_object($photo))
                                        <img src="{{ $photo->temporaryUrl() }}" class="w-full h-40 object-cover" alt="Preview">
                                    @else
                                        <div class="w-full h-40 flex items-center justify-center text-xs text-muted-foreground bg-muted">{{ __('common.preview_error') }}</div>
                                    @endif
                                    
                                    <button wire:click="removePhoto({{ $index }})" class="absolute top-2 right-2 bg-destructive/90 hover:bg-destructive text-destructive-foreground rounded-full p-1.5 opacity-0 group-hover:opacity-100 transition-all shadow-sm">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>

                                    <div class="absolute bottom-2 left-2 bg-background/90 backdrop-blur-sm px-2 py-1.5 rounded-md border border-border/50 inline-flex items-center gap-2">
                                        <x-ui.checkbox wire:model="intimateFlags.{{ $index }}" id="intimate-{{ $index }}" />
                                        <x-ui.label for="intimate-{{ $index }}" class="text-xs font-medium cursor-pointer select-none">18+</x-ui.label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (empty($existingPhotos) && count($this->photos) === 0)
                    <div class="text-center py-12 text-muted-foreground">{{ __('common.no_photos_selected') }}</div>
                @endif
            </div>

            <div class="p-6 border-t border-border bg-muted/30">
                <label for="add-more-photos" class="cursor-pointer w-full inline-flex items-center justify-center gap-2 rounded-md text-sm font-medium h-10 px-4 border border-border bg-background hover:bg-accent text-foreground transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('common.add_more_photos') }}
                </label>
                <input id="add-more-photos" type="file" wire:model.live="newPhotos"
                    wire:key="add-more-{{ count($photos) }}" class="hidden"
                    accept="image/jpeg, image/png, image/webp" multiple>
            </div>
        </div>
    </div>

</div>