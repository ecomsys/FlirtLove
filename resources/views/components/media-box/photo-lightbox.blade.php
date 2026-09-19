@props([
    'photos' => [],
    'name' => 'User',
    'isAuth' => false
])

@php
    $photosArray = $photos->values()->map(fn($p) => [
        'id' => $p->id,
        'large' => $p->large_url,
        'thumb' => $p->thumb_url,
        'album_id' => $p->album_id,
        'album_name' => $p->album?->name ?? 'Основной альбом',
        'title' => $p->title,             
        'description' => $p->description  
    ])->all();
@endphp

<div
    x-data="{ 
        open: false, 
        activePhotoId: null,
        allPhotos: @js($photosArray),
        isAuth: {{ $isAuth ? 'true' : 'false' }},
        init() {
            window.addEventListener('keydown', (e) => {
                if (!this.open) return;
                if (e.key === 'Escape') this.open = false;
                if (e.key === 'ArrowRight') this.next();
                if (e.key === 'ArrowLeft') this.prev();
            });
        },
        get activePhoto() {
            return this.allPhotos.find(p => p.id === this.activePhotoId) || this.allPhotos[0];
        },
        get currentAlbumId() {
            return this.activePhoto?.album_id ?? null;
        },
        // Фильтруем фото, оставляя только те, что из того же альбома
        get albumPhotos() {
            if (!this.currentAlbumId) return this.allPhotos;
            return this.allPhotos.filter(p => p.album_id === this.currentAlbumId);
        },
        get activeAlbumIndex() {
            return this.albumPhotos.findIndex(p => p.id === this.activePhotoId);
        },
        get albumName() {
            return this.activePhoto?.album_name || 'Все фото';
        },
        next() { 
            let nextIndex = this.activeAlbumIndex + 1;
            if (nextIndex >= this.albumPhotos.length) nextIndex = 0; // Зацикливаем
            this.activePhotoId = this.albumPhotos[nextIndex].id; 
        },
        prev() { 
            let prevIndex = this.activeAlbumIndex - 1;
            if (prevIndex < 0) prevIndex = this.albumPhotos.length - 1;
            this.activePhotoId = this.albumPhotos[prevIndex].id; 
        }
    }"
    @open-lightbox.window="activePhotoId = $event.detail.id; open = true"
    x-show="open"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    x-cloak
    class="fixed inset-0 z-50 bg-black/90 flex items-center justify-center p-4"
    style="display: none;"
>
    <!-- Кнопка закрытия -->
    <button @click="open = false" class="absolute top-4 right-4 text-white/70 hover:text-white z-50">
        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
    </button>

    <!-- Название альбома, название фото и счетчик (Сверху по центру) -->
    <div class="absolute top-6 left-1/2 -translate-x-1/2 z-30 text-center text-white pointer-events-none w-full max-w-md px-4">
        <h3 x-text="albumName" class="text-sm font-medium"></h3>
        <!-- Если у фото есть название, выводим его под альбомом -->
        <p x-show="activePhoto?.title" x-text="'«' + activePhoto?.title + '»'" class="text-xs text-white/70 mt-1"></p>
        <span class="text-xs text-white/50 mt-1 block" x-text="(activeAlbumIndex + 1) + ' / ' + albumPhotos.length"></span>
    </div>

    <div class="bg-background rounded-xl shadow-2xl w-full max-w-6xl h-[90vh] flex flex-col md:flex-row overflow-hidden relative">
        
        <!-- ЛЕВАЯ ЧАСТЬ: Галерея -->
        <div class="flex-1 flex flex-col bg-black p-4 space-y-4 relative">
            
            <!-- Главное фото (Плавная анимация смены через трюк с x-for) -->
            <div class="flex-1 flex items-center justify-center relative overflow-hidden">
                <template x-for="photo in [activePhoto]" :key="photo.id">
                    <img :src="photo.large" :alt="name" class="max-h-full max-w-full object-contain"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100">
                </template>
            </div>

            <!-- Стрелочки навигации -->
            <button x-show="albumPhotos.length > 1" @click="prev()" class="absolute left-2 top-1/2 -translate-y-1/2 text-white/50 hover:text-white p-2 bg-black/40 rounded-full backdrop-blur-sm">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
            </button>
            <button x-show="albumPhotos.length > 1" @click="next()" class="absolute right-2 top-1/2 -translate-y-1/2 text-white/50 hover:text-white p-2 bg-black/40 rounded-full backdrop-blur-sm">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
            </button>

            <!-- Тумбнейлы (Все фото из этого альбома) -->
            <div x-show="albumPhotos.length > 1" class="h-20 flex gap-2 overflow-x-auto justify-center items-center">
                <template x-for="(photo, index) in albumPhotos" :key="photo.id">
                    <img :src="photo.thumb" @click="activePhotoId = photo.id" class="h-full w-auto object-cover cursor-pointer rounded transition-all" :class="activePhotoId === photo.id ? 'ring-2 ring-primary opacity-100' : 'opacity-50 hover:opacity-100'">
                </template>
            </div>
        </div>

        <!-- ПРАВАЯ ЧАСТЬ: Комментарии -->
        <div class="w-full md:w-[360px] flex flex-col border-l border-border bg-background">
            
            <div class="p-4 border-b border-border flex justify-between items-center">
                <h3 class="font-semibold text-foreground">Комментарии</h3>
            </div>
            
            <div class="flex-1 overflow-y-auto p-4 space-y-4">
                <div class="flex gap-3">
                    <div class="w-8 h-8 rounded-full bg-muted shrink-0"></div>
                    <div>
                        <span class="text-sm font-medium text-foreground">Анна</span>
                        <p class="text-sm text-muted-foreground mt-1">Отличное фото! Где это было снято?</p>
                    </div>
                </div>
            </div>

            <div class="p-4 border-t border-border">
                <template x-if="isAuth">
                    <div class="flex gap-2">
                        <textarea class="flex-1 h-10 resize-none border border-border rounded-md p-2 text-sm bg-background focus:ring-2 focus:ring-primary outline-none" placeholder="Оставить комментарий..."></textarea>
                        <button class="bg-primary text-primary-foreground px-4 rounded-md text-sm font-medium hover:bg-primary/90 transition-colors">ОК</button>
                    </div>
                </template>
                <template x-if="!isAuth">
                    <div class="text-center text-sm text-muted-foreground py-2">
                        Войдите, чтобы оставлять комментарии
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>