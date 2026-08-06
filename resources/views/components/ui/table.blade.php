@props([
    'variant' => 'default', // default | card
    'poll' => false         // false | '5s' | '10s' и т.д.
])   

@php
    $container = $variant === 'card'
        ? 'relative w-full overflow-x-auto rounded-lg border bg-card shadow-xs'
        : 'relative w-full overflow-x-auto';

    // Формируем атрибут wire:poll динамически
    $pollingDirective = $poll ? "wire:poll.{$poll}" : '';
@endphp

<div data-slot="table-container" data-variant="{{ $variant }}" class="{{ $container }}" {{ $pollingDirective }}>
    <table data-slot="table" {{ $attributes->twMerge('w-full caption-bottom text-sm') }}>
        {{ $slot }}
    </table>
</div>