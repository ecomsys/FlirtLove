@props([
    'lastSeen' => null,
    'isOnline' => false
])

@php
    $statusText = null;
    $textColor = 'text-muted-foreground';
    $iconSvg = '';

    if ($isOnline) {
        $statusText = 'Сейчас онлайн';
        $textColor = 'text-green-600';
        $iconSvg = '<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" /></svg>';
    } elseif ($lastSeen) {
        $lastSeenCarbon = \Illuminate\Support\Carbon::parse($lastSeen);
        $diffInMinutes = $lastSeenCarbon->diffInMinutes(now());
        $diffInHours = $lastSeenCarbon->diffInHours(now());
        $diffInDays = $lastSeenCarbon->diffInDays(now());

        if ($diffInMinutes < 5) {
            $statusText = 'Онлайн';
            $textColor = 'text-green-600';
            // $iconSvg = '<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" /></svg>';
        } elseif ($diffInHours < 1) {
            $statusText = 'Недавно активен';
            $textColor = 'text-green-600';
        } elseif ($diffInDays < 7) {
            $statusText = 'Нечасто активен';
            $textColor = 'text-muted-foreground'; // Серый
        }
        // Если больше 7 дней — $statusText остается null, компонент ничего не выведет
    }
@endphp

@if($statusText)
<div class="flex items-center gap-1.5 text-sm {{ $textColor }}">
    @if($iconSvg)
        {!! $iconSvg !!}
    @endif
    {{ $statusText }}
</div>
@endif