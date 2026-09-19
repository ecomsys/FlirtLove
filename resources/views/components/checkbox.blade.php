@props([
    'name'         => null,
    'id'           => null,
    'value'        => '1',
    'checked'      => false,
    'required'     => false,
    'disabled'     => false,
    'error'        => null,
    'variant'      => 'primary',
    'size'         => 'md',
    'class'        => null,
    'wrapperClass' => null,
    'labelClass'   => null,
])

@php
    $id = $id ?? 'checkbox-' . uniqid();
    $hasError = !empty($error);
    
    // Безопасное определение состояния (понимает строки "true", "1", "false", "0" и булевы)
    $isMultiple = is_array($checked);
    $isChecked = $isMultiple 
        ? in_array($value, $checked ?? []) 
        : filter_var($checked, FILTER_VALIDATE_BOOLEAN);

    $variantClasses = [
        'primary' => [
            'box' => 'peer-checked:bg-primary peer-checked:border-primary',
            'icon' => 'text-primary-foreground',
        ],
        'destructive' => [
            'box' => 'peer-checked:bg-destructive peer-checked:border-destructive',
            'icon' => 'text-destructive-foreground',
        ],
        'success' => [
            'box' => 'peer-checked:bg-success peer-checked:border-success',
            'icon' => 'text-success-foreground',
        ],
        'warning' => [
            'box' => 'peer-checked:bg-warning peer-checked:border-warning',
            'icon' => 'text-warning-foreground',
        ],
        'info' => [
            'box' => 'peer-checked:bg-info peer-checked:border-info',
            'icon' => 'text-info-foreground',
        ],
        'transparent' => [
            'box' => 'peer-checked:bg-transparent peer-checked:border-primary',
            'icon' => 'text-primary',
        ],
    ];

    $currentColors = $variantClasses[$variant] ?? $variantClasses['primary'];

    $sizeClasses = [
        'sm' => ['box' => 'w-4 h-4', 'icon' => 'w-3 h-3'],
        'md' => ['box' => 'w-5 h-5', 'icon' => 'w-4 h-4'],
        'lg' => ['box' => 'w-6 h-6', 'icon' => 'w-5 h-5'],
    ];
    $currentSize = $sizeClasses[$size] ?? $sizeClasses['md'];

    $hasAlpineModel = $attributes->has('x-model') || $attributes->has('wire:model');
    $hasAlpineValue = $attributes->has('x-bind:value') || $attributes->has(':value');

    // Оставляем только то, что не объявлено в @props, чтобы передать в <input>
    $inputAttributes = $attributes->except([
        'class', 'wrapperClass', 'variant', 'size', 'error', 'checked', 
        'id', 'name', 'value', 'required', 'disabled', 'labelClass'
    ]);
@endphp

<label
    for="{{ $id }}"
    class="inline-flex items-center {{ $disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer' }} {{ $wrapperClass ?? '' }}"
>
    <input
        type="checkbox"
        id="{{ $id }}"
        @if($name) name="{{ $name }}" @endif
        @if($hasAlpineValue)
            {{-- Alpine/Livewire сами подставят значение --}}
        @else
            value="{{ $value }}"
        @endif
        
        @if(!$hasAlpineModel)
            @if($isChecked) checked @endif
        @endif

        {{ $disabled ? 'disabled' : '' }}
        {{ $required ? 'required' : '' }}
        {{ $inputAttributes }}
        aria-required="{{ $required ? 'true' : 'false' }}"
        @if($hasError) aria-invalid="true" @endif
        class="peer sr-only"
    />

    <span
        class="
            {{ $currentSize['box'] }}
            inline-flex items-center justify-center
            border-2 rounded
            transition-all duration-200
            bg-background border-input
            {{ $currentColors['box'] }}
            peer-not-checked:hover:border-foreground/40
            peer-focus-visible:ring-2 peer-focus-visible:ring-offset-2 peer-focus-visible:ring-ring peer-focus-visible:ring-offset-background
            peer-disabled:opacity-50 peer-disabled:cursor-not-allowed
            peer-checked:[&>svg]:opacity-100 peer-checked:[&>svg]:scale-100
            @if($hasError) border-destructive @endif
            {{ $class ?? '' }}
        "
    >
        <svg
            class="{{ $currentSize['icon'] }} {{ $currentColors['icon'] }} opacity-0 scale-50 transition-all duration-150"
            fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"
        >
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
        </svg>
    </span>

    {{-- ЕСЛИ ЕСТЬ ТЕКСТ ВНУТРИ КОМПОНЕНТА, ВЫВОДИМ ЕГО --}}
    @if (isset($slot) && trim($slot) !== '')
        <span class="ml-2 text-xs font-medium cursor-pointer select-none {{ $labelClass ?? '' }}">
            {{ $slot }}
        </span>
    @endif
</label>