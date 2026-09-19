@props(['count' => 0])

@php
    // Правильное склонение слова "человек" для тултипа
    $word = 'человек';
    if ($count % 10 == 1 && $count % 100 != 11) {
        $word = 'человек';
    } elseif ($count % 10 >= 2 && $count % 10 <= 4 && ($count % 100 < 10 || $count % 100 >= 20)) {
        $word = 'человека';
    }
    $tooltipText = "Сейчас эту анкету просматривает {$count} {$word}";
@endphp

<x-web-ui.tooltip :text="$tooltipText">
    <div class="flex items-center gap-1 text-xs text-muted-foreground cursor-default">
        <!-- Залитый силуэт (голова и плечи) -->
        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
        </svg>
        <span>{{ $count }}</span>
    </div>
</x-web-ui.tooltip>