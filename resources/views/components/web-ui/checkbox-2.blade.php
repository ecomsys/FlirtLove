@props([
    'checkedExpr' => 'false',
    'clickExpr' => ''
])

<div 
    class="flex items-center gap-2 cursor-pointer select-none transition-colors" 
    @click="{{ $clickExpr }}"
>
    <input type="checkbox" class="sr-only" x-bind:checked="{{ $checkedExpr }}" />
    <span 
        class="w-5 h-5 border-2 rounded flex items-center justify-center transition-all duration-200"
        :class="{{ $checkedExpr }} ? 'bg-primary border-primary' : 'bg-background border-input'"
    >
        <svg 
            class="w-4 h-4 text-primary-foreground transition-all duration-150" 
            :class="{{ $checkedExpr }} ? 'opacity-100 scale-100' : 'opacity-0 scale-50'"
            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"
        >
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
        </svg>
    </span>
    
    @if (isset($slot) && trim($slot) !== '')
        <span class="text-xs cursor-pointer">
            {{ $slot }}
        </span>
    @endif
</div>