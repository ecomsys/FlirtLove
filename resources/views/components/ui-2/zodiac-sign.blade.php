@props([
    'sign' => null
])

@php
    $zodiacs = [
        1  => ['symbol' => '♈', 'name' => 'Овен'],
        2  => ['symbol' => '♉', 'name' => 'Телец'],
        3  => ['symbol' => '♊', 'name' => 'Близнецы'],
        4  => ['symbol' => '♋', 'name' => 'Рак'],
        5  => ['symbol' => '♌', 'name' => 'Лев'],
        6  => ['symbol' => '♍', 'name' => 'Дева'],
        7  => ['symbol' => '♎', 'name' => 'Весы'],
        8  => ['symbol' => '♏', 'name' => 'Скорпион'],
        9  => ['symbol' => '♐', 'name' => 'Стрелец'],
        10 => ['symbol' => '♑', 'name' => 'Козерог'],
        11 => ['symbol' => '♒', 'name' => 'Водолей'],
        12 => ['symbol' => '♓', 'name' => 'Рыбы'],
    ];

    $signId = (int) $sign;
    $zodiac = $zodiacs[$signId] ?? null;
@endphp

@if($zodiac)
    <x-ui-2.tooltip :text="$zodiac['name']">
        {{-- 
            Символ &#xFE0E; (Variation Selector-15) принудительно отключает цветные эмодзи.
            Font-family с 'Segoe UI Symbol' и 'Apple Symbols' заставляет браузер 
            рисовать тонкие, векторные линии, которые выглядят дорого и стильно.
        --}}
        <span 
            class="text-xl text-muted-foreground cursor-default leading-none"
            style="font-family: 'Segoe UI Symbol', 'Apple Symbols', 'Times New Roman', serif; font-variant-emoji: text;"
        >{{ $zodiac['symbol'] }}&#xFE0E;</span>
    </x-ui-2.tooltip>
@endif