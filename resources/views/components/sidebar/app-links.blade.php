@props(['class' => ''])

@php
    $appStoreUrl = \App\Models\Setting::get('appstore_url', '#');
    $googlePlayUrl = \App\Models\Setting::get('googleplay_url', '#');
@endphp

<div {{ $attributes->twMerge([$class, 'space-y-3']) }}>
    <!-- App Store -->
    @if ($appStoreUrl && $appStoreUrl !== '#')
        <a href="{{ $appStoreUrl }}" target="_blank" rel="noopener noreferrer"
            class="group flex items-center gap-3 w-full px-4 py-2.5 bg-black text-white rounded-lg transition-all duration-300 hover:bg-black/80 hover:ring-1 hover:ring-primary/40 hover:shadow-lg hover:shadow-primary/30 hover:-translate-y-0.5">
            <svg class="w-6 h-6 shrink-0 transition-transform duration-300 group-hover:scale-110" fill="currentColor"
                viewBox="0 0 24 24">
                <path
                    d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.27c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.46-1.38 2.73m-5.27-13.84c.84-.99 1.41-2.4 1.26-3.79-1.21.05-2.69.82-3.56 1.82-.78.88-1.47 2.3-1.28 3.68 1.35.1 2.73-.69 3.58-1.71z" />
            </svg>
            <div class="flex flex-col text-left leading-tight">
                <span class="text-[10px] text-white/70 uppercase tracking-wide">Доступно в</span>
                <span class="text-base font-semibold">App Store</span>
            </div>
        </a>
    @endif

    <!-- Google Play -->
    @if ($googlePlayUrl && $googlePlayUrl !== '#')
        <a href="{{ $googlePlayUrl }}" target="_blank" rel="noopener noreferrer"
            class="group flex items-center gap-3 w-full px-4 py-2.5 bg-black text-white rounded-lg transition-all duration-300 hover:bg-black/80 hover:ring-1 hover:ring-primary/40 hover:shadow-lg hover:shadow-primary/30 hover:-translate-y-0.5">
            <!-- Стандартная иконка Google Play -->
            <svg class="w-5 h-5 shrink-0 transition-transform duration-300 group-hover:scale-110" fill="currentColor"
                viewBox="0 0 512 512">
                <path fill="currentColor"
                    d="M325.3 234.3L104.6 13l280.8 161.2-60.1 60.1zM47 0C34 6.8 25.3 19.2 25.3 35.3v441.3c0 16.1 8.7 28.5 21.7 35.3l256.6-256L47 0zm425.2 225.6l-58.9-34.1-65.7 64.5 65.7 64.5 58.9-34.1c18.7-10.8 18.7-39.4 0-50.8zM104.6 499l220.8-221.3 60.1 60.1L165.4 499c-18.2 10.5-42.1 10.5-60.8 0z" />
            </svg>
            <div class="flex flex-col text-left leading-tight">
                <span class="text-[10px] text-white/70 uppercase tracking-wide">Доступно в</span>
                <span class="text-base font-semibold">Google Play</span>
            </div>
        </a>
    @endif
</div>