@props([
    'src' => null,
    'alt' => '',
    'class' => '',
])

@php
    $placeholder = asset('images/no-image-placeholder.png');
@endphp

<img 
    src="{{ $src ?? $placeholder }}" 
    alt="{{ $alt }}" 
    class="{{ $class }}"
    loading="lazy"
    onerror="if (this.src !== '{{ $placeholder }}') { this.src = '{{ $placeholder }}'; }"
>