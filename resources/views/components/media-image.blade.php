@props([
    'src' => null,
    'class' => '',
])

@php
    $placeholder = asset('images/no-image-placeholder.png');
    
    // Проверяем, передал ли Alpine динамический src
    $bindSrc = $attributes->get('x-bind:src') ?: $attributes->get(':src');
    
    if ($bindSrc) {
        // Удаляем старый атрибут
        $attributes->offsetUnset('x-bind:src');
        $attributes->offsetUnset(':src');
        
        // Подставляем динамический плейсхолдер от Laravel в выражение Alpine
        $newBindSrc = "({$bindSrc}) || '{$placeholder}'";
        $attributes->offsetSet('x-bind:src', $newBindSrc);
        
        $finalSrc = null;
    } else {
        // Используем ?: чтобы отлавливать и null, и пустую строку ""
        $finalSrc = $src ?: $placeholder;
    }
@endphp

<img 
    @if($finalSrc) src="{{ $finalSrc }}" @endif
    {{ $attributes->merge(['class' => $class]) }}
    loading="lazy"
    @if($finalSrc) onerror="if (this.src !== '{{ $placeholder }}') { this.src = '{{ $placeholder }}'; }" @endif
>