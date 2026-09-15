@props([
    'photos' => [],
    'photosCount' => 0,
    'isPremium' => false,
    'name' => 'User',
    'userId' => null
])

@php
    $photos = $photos->values();
    $initialPhoto = $photos->firstWhere('is_primary', true) ?? $photos->first();
    $initialPhotoIndex = $photos->search(function($item) use ($initialPhoto) { return $item->id === $initialPhoto?->id; });
    $totalPhotos = $photosCount > 0 ? $photosCount : $photos->count();
    
    $visiblePhotos = collect([]);
    $remainingCount = 0;

    if ($totalPhotos > 5) {
        $visiblePhotos = $photos->take(5);
        $remainingCount = $totalPhotos - 5;
    } else {
        $visiblePhotos = $photos;
    }
@endphp

<!-- Добавили x-data с проверкой авторизации -->
<div x-data="{ isAuth: {{ auth()->check() ? 'true' : 'false' }} }" class="w-full flex flex-col gap-4">
    
    <!-- Контейнер с рамкой VIP -->
    <div class="relative rounded-sm @if($isPremium) border-5 border-yellow-400 shadow-lg shadow-yellow-400/30 @endif">
        
        <!-- Главное фото -->
        <div 
            class="aspect-[3/4] bg-muted relative rounded-sm cursor-pointer" 
            @click="isAuth ? $dispatch('open-lightbox', { id: {{ $initialPhoto->id ?? 0 }} }) : $dispatch('open-login-modal')"
        >
            @if($initialPhoto)
                <img src="{{ $initialPhoto->large_url }}" alt="{{ $name }}" class="absolute inset-0 w-full h-full object-cover">
            @else
                <div class="w-full h-full flex items-center justify-center text-muted-foreground">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
            @endif

            <!-- Счетчик -->
            @if($totalPhotos > 0)
                <div class="rounded-sm absolute bottom-2 left-2 bg-black/60 text-white text-xs px-2 py-1 flex items-center gap-1 backdrop-blur-sm z-10">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                    </svg>
                    <span>{{ $totalPhotos }}</span>
                </div>
            @endif

            <!-- Корона VIP -->
            @if($isPremium)
                <div class="absolute bottom-0 right-0 w-16 h-16 z-20 pointer-events-none">
                    <div style="width: 0; height: 0; border-bottom: 4rem solid #facc15; border-left: 4rem solid transparent;"></div>
                    <svg class="absolute bottom-2 right-2 w-6 h-6 text-black z-10" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M5 16L3 5l5.5 4L12 4l3.5 5L21 5l-2 11H5zm0 2h14v2H5v-2z"/>
                    </svg>
                </div>
            @endif
        </div>
    </div>

    <!-- Тумбнейлы -->
    @if($visiblePhotos->count() > 1)
        <div class="grid grid-cols-5 gap-2">
           @foreach($visiblePhotos as $index => $photo)
                <div 
                    class="aspect-square bg-muted rounded-sm overflow-hidden cursor-pointer relative group" 
                    @click="isAuth ? $dispatch('open-lightbox', { id: {{ $photo->id }} }) : $dispatch('open-login-modal')"
                >
                    <img src="{{ $photo->thumb_url }}" alt="{{ $name }}" class="w-full h-full object-cover transition-transform duration-200 group-hover:scale-105">  
                    @if($photo->is_primary)
                        <span class="absolute bottom-0 right-0 bg-primary text-primary-foreground text-[8px] font-bold px-1 py-0.5">AV</span>
                    @endif                
                </div>
            @endforeach
        </div>
        
        @if($remainingCount > 0)
            <a href="{{ $userId ? '/user/' . $userId . '/photos' : '#' }}" class="mt-2 rounded-sm block w-full h-10 flex items-center justify-center bg-muted border border-border text-sm font-medium text-foreground hover:bg-card transition-colors">
                Ещё {{ $remainingCount }} фото
            </a>
        @endif
    @endif
</div>