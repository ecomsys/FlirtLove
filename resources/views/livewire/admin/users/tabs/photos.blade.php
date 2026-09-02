<?php

use App\Actions\Admin\ModeratePhotoAction;
use App\Enums\PhotoRejectReason;
use App\Models\Photo;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component 
{
    public int $userId;

    public bool $isRejectModalVisible = false;
    public ?int $rejectingPhotoId = null;
    public string $rejectReason = '';

    private ModeratePhotoAction $moderatePhotoAction;

    public function boot(ModeratePhotoAction $moderatePhotoAction): void
    {
        $this->moderatePhotoAction = $moderatePhotoAction;
    }

    public function mount(int $userId): void
    {
        $this->userId = $userId;
    }

    #[Computed]
    public function user(): User
    {
        return User::withTrashed()->findOrFail($this->userId);
    }

    #[Computed]
    public function albums()
    {
        $allPhotos = Photo::withTrashed()
            ->where('user_id', $this->userId)
            ->where('type', 'profile')
            ->with('album:id,name')
            ->orderByDesc('is_primary')
            ->latest('created_at')
            ->get();

        return $allPhotos->groupBy(function($photo) {
            return $photo->album_id ?? 'no_album';
        })->map(function($photos, $albumId) {
            $albumName = $albumId === 'no_album' ? 'Без альбома' : $photos->first()->album?->name ?? 'Без альбома';
            return [
                'name' => $albumName,
                'photos' => [
                    'pending'  => $photos->filter(fn($p) => $p->status === 'pending' && !$p->deleted_at)->values(),
                    'approved' => $photos->filter(fn($p) => $p->status === 'approved' && !$p->deleted_at)->values(),
                    'rejected' => $photos->filter(fn($p) => $p->status === 'rejected' && !$p->deleted_at)->values(),
                    'trashed'  => $photos->filter(fn($p) => $p->deleted_at)->values(),
                ]
            ];
        });
    }

    #[On('user-action-performed')] 
    public function refreshUser(): void
    {
        unset($this->user);
        unset($this->albums);
    }

    public function approve(int $photoId): void
    {
        $photo = Photo::withTrashed()->find($photoId);
        if (!$photo) return;
        $this->moderatePhotoAction->approve($photo, auth()->user());
        $this->dispatch('show-toast', type: 'success', message: 'Фото одобрено');
        unset($this->albums);
    }

    public function openRejectModal(int $photoId): void
    {
        $this->rejectingPhotoId = $photoId;
        $this->rejectReason = '';
        $this->isRejectModalVisible = true;
    }

    public function closeRejectModal(): void
    {
        $this->isRejectModalVisible = false;
        $this->rejectingPhotoId = null;
    }

    public function rejectPhoto(): void
    {
        $this->validate([
            'rejectReason' => ['required', 'in:' . implode(',', array_column(PhotoRejectReason::cases(), 'value'))],
        ]);

        $photo = Photo::withTrashed()->find($this->rejectingPhotoId);
        if (!$photo) {
            $this->closeRejectModal();
            return;
        }

        $this->moderatePhotoAction->reject($photo, auth()->user(), $this->rejectReason);

        $this->closeRejectModal();
        $this->dispatch('show-toast', type: 'error', message: 'Фото отклонено и помечено как нарушение');
        unset($this->albums);
    }

    public function softDelete(int $photoId): void
    {
        $photo = Photo::find($photoId);
        if (!$photo) return;

        // ФИКС: Делегируем в Action
        $this->moderatePhotoAction->softDelete($photo, auth()->user());
        
        $this->dispatch('show-toast', type: 'warning', message: 'Фото перемещено в карантин');
        unset($this->albums);
    }

    public function setPrimary(int $photoId): void
    { 
        $photo = Photo::withTrashed()->find($photoId);
        if (!$photo) return;
        if ($photo->deleted_at) {
            $this->dispatch('show-toast', type: 'error', message: 'Нельзя поставить карантинное фото как аватар!');
            return;
        }       
        $this->moderatePhotoAction->setPrimary($photo, auth()->user());
        $this->dispatch('show-toast', type: 'success', message: 'Установлено как аватар');
        $this->dispatch('user-action-performed');
        unset($this->albums);
    }

    public function restorePhoto(int $photoId): void
    {
        $photo = Photo::withTrashed()->find($photoId);
        if (!$photo) return;

        // ФИКС: Делегируем в Action
        $this->moderatePhotoAction->restore($photo, auth()->user());
        
        $this->dispatch('show-toast', type: 'success', message: 'Фото восстановлено в очередь');
        unset($this->albums);
    }

    public function destroy(int $photoId): void
    {
        $photo = Photo::withTrashed()->find($photoId);
        if (!$photo) return;
        $this->moderatePhotoAction->destroy($photo, auth()->user());
        $this->dispatch('show-toast', type: 'success', message: 'Фото навсегда удалено.');
        unset($this->albums);
    }
}; 
?>

<div class="space-y-6">
    @if($this->albums->isEmpty())
        <div class="bg-card border border-border rounded-lg p-16 text-center">
            <div class="w-16 h-16 mx-auto rounded-full bg-muted flex items-center justify-center mb-4">
                <x-lucide-image-off class="w-8 h-8 text-muted-foreground" />
            </div>
            <h3 class="text-lg font-medium">Нет фотографий</h3>
            <p class="text-muted-foreground mt-1">Пользователь еще не загрузил ни одной фотографии.</p>
        </div>
    @else
        @foreach($this->albums as $albumId => $albumData)
            <div class="bg-card border border-border rounded-xl overflow-hidden" wire:key="album-{{ $albumId }}">
                
                <div class="p-4 border-b border-border flex items-center justify-between bg-muted/20">
                    <h3 class="font-semibold text-foreground flex items-center gap-2">
                        <x-lucide-folder class="w-4 h-4 text-muted-foreground" /> 
                        Альбом: «{{ $albumData['name'] }}»
                    </h3>
                </div>

                <div class="p-4 space-y-6">
                    
                    {{-- Блок: Ожидающие --}}
                    @if($albumData['photos']['pending']->isNotEmpty())
                        <div>
                            <h4 class="text-xs uppercase font-semibold text-yellow-500 mb-3 flex items-center gap-1.5">
                                <x-lucide-clock class="w-3.5 h-3.5" /> Ожидают ({{ $albumData['photos']['pending']->count() }})
                            </h4>
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                                @foreach($albumData['photos']['pending'] as $photo)
                                    <div class="bg-muted/10 border border-border rounded-lg overflow-hidden" wire:key="photo-{{ $photo->id }}">
                                        <div class="relative aspect-square group overflow-hidden">
                                            <a href="{{ $photo->original_url ?: $photo->medium_url ?: '#' }}" data-fancybox="gallery-user-{{ $this->user->id }}" data-caption="Фото #{{ $photo->id }}" class="block w-full h-full cursor-pointer">
                                                <img src="{{ $photo->thumb_url ?: $photo->medium_url ?: asset('images/no-image-placeholder.png') }}" class="w-full h-full object-cover">
                                            </a>
                                            
                                            <div class="absolute top-2 left-2 z-10 flex flex-col gap-1">
                                                @if ($photo->is_primary) <x-ui.badge size="xs">Аватар</x-ui.badge> @endif
                                                @if ($photo->is_intimate) <x-ui.badge variant="destructive" size="xs">18+</x-ui.badge> @endif
                                                <x-ui.badge variant="warning" size="xs">Ожидает</x-ui.badge>
                                            </div>

                                             <div class="absolute top-2 right-2 z-10 inline-flex flex-col gap-1">                                
                                                <span class="bg-black/60 text-white text-[0.625rem] px-1.5 py-0.5 rounded font-medium">#{{ $photo->id }}</span>
                                            </div>            

                                            <div class="absolute bottom-2 left-2 right-2 z-10 flex gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity bg-black/60 p-1.5 rounded-lg backdrop-blur-sm">
                                                <x-ui.button wire:click="approve({{ $photo->id }})" wire:target="approve({{ $photo->id }})" variant="success" size="sm" class="flex-1 h-8 text-xs">
                                                    <span wire:loading.remove wire:target="approve({{ $photo->id }})">Одобрить</span>
                                                    <x-lucide-loader-2 wire:loading wire:target="approve({{ $photo->id }})" class="w-4 h-4 animate-spin" />
                                                </x-ui.button>
                                                <x-ui.button wire:click="openRejectModal({{ $photo->id }})" variant="warning" size="sm" class="flex-1 h-8 text-xs">Отклонить</x-ui.button>
                                                <!-- ФИКС: Текст "Карантин" -->
                                                <x-ui.button wire:click="softDelete({{ $photo->id }})" wire:confirm="Переместить в карантин без причины?" wire:target="softDelete({{ $photo->id }})" variant="destructive" size="sm" class="h-8 text-xs">
                                                    <span wire:loading.remove wire:target="softDelete({{ $photo->id }})">Карантин</span>
                                                    <x-lucide-loader-2 wire:loading wire:target="softDelete({{ $photo->id }})" class="w-4 h-4 animate-spin" />
                                                </x-ui.button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Блок: Одобренные --}}
                    @if($albumData['photos']['approved']->isNotEmpty())
                        <div>
                            <h4 class="text-xs uppercase font-semibold text-green-500 mb-3 flex items-center gap-1.5">
                                <x-lucide-check-circle class="w-3.5 h-3.5" /> Одобрены ({{ $albumData['photos']['approved']->count() }})
                            </h4>
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                                @foreach($albumData['photos']['approved'] as $photo)
                                    <div class="bg-muted/10 border border-border rounded-lg overflow-hidden" wire:key="photo-{{ $photo->id }}">
                                        <div class="relative aspect-square group overflow-hidden">
                                            <a href="{{ $photo->original_url ?: $photo->medium_url ?: '#' }}" data-fancybox="gallery-user-{{ $this->user->id }}" data-caption="Фото #{{ $photo->id }}" class="block w-full h-full cursor-pointer">
                                                <img src="{{ $photo->thumb_url ?: $photo->medium_url ?: asset('images/no-image-placeholder.png') }}" class="w-full h-full object-cover">
                                            </a>
                                            
                                            <div class="absolute top-2 left-2 z-10 flex flex-col gap-1">
                                                @if ($photo->is_primary) <x-ui.badge size="xs">Аватар</x-ui.badge> @endif
                                                @if ($photo->is_intimate) <x-ui.badge variant="destructive" size="xs">18+</x-ui.badge> @endif
                                                <x-ui.badge variant="success" size="xs">Одобрено</x-ui.badge>
                                            </div>
                                            
                                             <div class="absolute top-2 right-2 z-10 inline-flex flex-col gap-1">                                
                                                <span class="bg-black/60 text-white text-[0.625rem] px-1.5 py-0.5 rounded font-medium">#{{ $photo->id }}</span>
                                            </div>   

                                            <div class="absolute bottom-2 left-2 right-2 z-10 flex gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity bg-black/60 p-1.5 rounded-lg backdrop-blur-sm">
                                                @if(!$photo->is_primary)
                                                    <x-ui.button wire:click="setPrimary({{ $photo->id }})" wire:target="setPrimary({{ $photo->id }})" variant="default" size="sm" class="flex-1 h-8 text-xs">
                                                        <span wire:loading.remove wire:target="setPrimary({{ $photo->id }})">В аватары</span>
                                                        <x-lucide-loader-2 wire:loading wire:target="setPrimary({{ $photo->id }})" class="w-4 h-4 animate-spin" />
                                                    </x-ui.button>
                                                @endif
                                                <x-ui.button wire:click="openRejectModal({{ $photo->id }})" variant="warning" size="sm" class="flex-1 h-8 text-xs">Отклонить</x-ui.button>
                                                <!-- ФИКС: Текст "Карантин" -->
                                                <x-ui.button wire:click="softDelete({{ $photo->id }})" wire:confirm="Переместить в карантин без причины?" wire:target="softDelete({{ $photo->id }})" variant="destructive" size="sm" class="h-8 text-xs">
                                                    <span wire:loading.remove wire:target="softDelete({{ $photo->id }})">Карантин</span>
                                                    <x-lucide-loader-2 wire:loading wire:target="softDelete({{ $photo->id }})" class="w-4 h-4 animate-spin" />
                                                </x-ui.button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Блок: Отклоненные (Нарушители правил) --}}
                    @if($albumData['photos']['rejected']->isNotEmpty())
                        <div>
                            <h4 class="text-xs uppercase font-semibold text-destructive mb-3 flex items-center gap-1.5">
                                <x-lucide-x-circle class="w-3.5 h-3.5" /> Отклонены ({{ $albumData['photos']['rejected']->count() }})
                            </h4>
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                                @foreach($albumData['photos']['rejected'] as $photo)
                                    @php $reasonEnum = $photo->reject_reason ? PhotoRejectReason::tryFrom($photo->reject_reason) : null; @endphp
                                    <div class="bg-muted/10 border border-destructive/20 rounded-lg overflow-hidden" wire:key="photo-{{ $photo->id }}-rejected">
                                        <div class="relative aspect-square group overflow-hidden">
                                            <a href="{{ $photo->original_url ?: $photo->medium_url ?: '#' }}" data-fancybox="gallery-user-{{ $this->user->id }}" data-caption="Фото #{{ $photo->id }}" class="block w-full h-full cursor-pointer">
                                                <img src="{{ $photo->thumb_url ?: $photo->medium_url ?: asset('images/no-image-placeholder.png') }}" class="w-full h-full object-cover opacity-70">
                                            </a>
                                            
                                            <div class="absolute top-2 left-2 z-10 flex flex-col gap-1">
                                                <x-ui.badge variant="destructive" size="xs">{{ $reasonEnum?->label() ?? 'Отклонено' }}</x-ui.badge>
                                            </div>

                                             <div class="absolute top-2 right-2 z-10 inline-flex flex-col gap-1">                                
                                                <span class="bg-black/60 text-white text-[0.625rem] px-1.5 py-0.5 rounded font-medium">#{{ $photo->id }}</span>
                                            </div>   

                                            <div class="absolute bottom-2 left-2 right-2 z-10 flex gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity bg-black/60 p-1.5 rounded-lg backdrop-blur-sm">
                                                <x-ui.button wire:click="approve({{ $photo->id }})" wire:target="approve({{ $photo->id }})" variant="success" size="sm" class="flex-1 h-8 text-xs">
                                                    <span wire:loading.remove wire:target="approve({{ $photo->id }})">Одобрить</span>
                                                    <x-lucide-loader-2 wire:loading wire:target="approve({{ $photo->id }})" class="w-4 h-4 animate-spin" />
                                                </x-ui.button>
                                                <x-ui.button wire:click="softDelete({{ $photo->id }})" wire:confirm="Переместить в карантин?" wire:target="softDelete({{ $photo->id }})" variant="destructive" size="sm" class="h-8 text-xs">
                                                    <span wire:loading.remove wire:target="softDelete({{ $photo->id }})">В карантин</span>
                                                    <x-lucide-loader-2 wire:loading wire:target="softDelete({{ $photo->id }})" class="w-4 h-4 animate-spin" />
                                                </x-ui.button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Блок: В карантине (Удаленные) --}}
                    @if($albumData['photos']['trashed']->isNotEmpty())
                        <div>
                            <h4 class="text-xs uppercase font-semibold text-muted-foreground mb-3 flex items-center gap-1.5">
                                <x-lucide-archive class="w-3.5 h-3.5" /> В карантине ({{ $albumData['photos']['trashed']->count() }})
                            </h4>
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                                @foreach($albumData['photos']['trashed'] as $photo)
                                    @php 
                                        $reasonEnum = $photo->reject_reason ? PhotoRejectReason::tryFrom($photo->reject_reason) : null;
                                        $trashLabel = $reasonEnum ? $reasonEnum->label() : 'Удалено модератором';
                                    @endphp
                                    <div class="bg-muted/10 border border-border rounded-lg overflow-hidden" wire:key="photo-{{ $photo->id }}-trashed">
                                        <div class="relative aspect-square group overflow-hidden">
                                            <a href="{{ $photo->original_url ?: $photo->medium_url ?: '#' }}" data-fancybox="gallery-user-{{ $this->user->id }}" data-caption="Фото #{{ $photo->id }}" class="block w-full h-full cursor-pointer">
                                                <img src="{{ $photo->thumb_url ?: $photo->medium_url ?: asset('images/no-image-placeholder.png') }}" class="w-full h-full object-cover opacity-50 grayscale">
                                            </a>
                                            
                                            <div class="absolute top-2 left-2 z-10 flex flex-col gap-1">
                                                <x-ui.badge variant="secondary" size="xs">Карантин</x-ui.badge>
                                                <x-ui.badge variant="destructive" size="xs">{{ $trashLabel }}</x-ui.badge>
                                            </div>

                                            <div class="absolute bottom-2 left-2 right-2 z-10 flex gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity bg-black/60 p-1.5 rounded-lg backdrop-blur-sm">
                                                <x-ui.button wire:click="restorePhoto({{ $photo->id }})" wire:target="restorePhoto({{ $photo->id }})" variant="success" size="sm" class="flex-1 h-8 text-xs">
                                                    <span wire:loading.remove wire:target="restorePhoto({{ $photo->id }})">Вернуть</span>
                                                    <x-lucide-loader-2 wire:loading wire:target="restorePhoto({{ $photo->id }})" class="w-4 h-4 animate-spin" />
                                                </x-ui.button>
                                                <x-ui.button wire:click="destroy({{ $photo->id }})" wire:confirm="Удалить фото НАВСЕГДА вместе с файлами?" wire:target="destroy({{ $photo->id }})" variant="destructive" size="sm" class="flex-1 h-8 text-xs">
                                                    <span wire:loading.remove wire:target="destroy({{ $photo->id }})">Удалить навсегда</span>
                                                    <x-lucide-loader-2 wire:loading wire:target="destroy({{ $photo->id }})" class="w-4 h-4 animate-spin" />
                                                </x-ui.button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                </div>
            </div>
        @endforeach
    @endif

    {{-- МОДАЛКА ОТКЛОНЕНИЯ (Починенная) --}}
    <div x-data="{ show: @entangle('isRejectModalVisible') }" x-show="show" x-cloak 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" 
         style="display: none;"
         @click.self="$wire.closeRejectModal()"
         @keydown.escape.window="$wire.closeRejectModal()">
         
        <div class="relative bg-card border border-border rounded-lg shadow-2xl max-w-md w-full mx-4 overflow-hidden">
            <div class="p-6 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-destructive/10 rounded-full">
                        <x-lucide-shield-x class="w-6 h-6 text-destructive" />
                    </div>
                    <h2 class="text-lg font-semibold">Отклонить фото?</h2>
                </div>
                <p class="text-sm text-muted-foreground">Выберите причину отклонения. Пользователь получит уведомление.</p>

                <div class="space-y-2">
                    <x-ui.label class="text-xs">Причина отклонения</x-ui.label>
                    <x-ui.select wire:model="rejectReason">
                        <x-ui.select-trigger class="w-full"><x-ui.select-value placeholder="Выберите причину..." /></x-ui.select-trigger>
                        <x-ui.select-content>
                            <x-ui.select-item value="">Выберите причину...</x-ui.select-item>
                            @foreach(PhotoRejectReason::options() as $value => $label)
                                <x-ui.select-item value="{{ $value }}" wire:key="reason-{{ $value }}">{{ $label }}</x-ui.select-item>
                            @endforeach
                        </x-ui.select-content>
                    </x-ui.select>
                    @error('rejectReason') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 p-4 border-t border-border bg-muted/20">
                <x-ui.button @click="$wire.closeRejectModal()" variant="outline" size="sm">Отмена</x-ui.button>
                <x-ui.button wire:click="rejectPhoto" variant="warning" size="sm" wire:loading.attr="disabled" wire:target="rejectPhoto">
                    <span wire:loading.remove wire:target="rejectPhoto">Отклонить фото</span>
                    <x-lucide-loader-2 wire:loading wire:target="rejectPhoto" class="w-4 h-4 animate-spin" />
                </x-ui.button>
            </div>
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