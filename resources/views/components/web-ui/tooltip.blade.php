@props([
    'text' => '',
    'class' => ''
])

<div 
    x-data="{ hovered: false, top: 0, left: 0 }"
    @mouseenter="let rect = $el.getBoundingClientRect(); top = rect.top; left = rect.left + rect.width / 2; hovered = true"
    @mouseleave="hovered = false"
    @scroll.window="hovered = false"
    class="inline-block relative {{ $class }}"
>
    {{ $slot }}

    <!-- Портал тултипа в <body> -->
    <template x-teleport="body">
        <div
            x-show="hovered"
            x-cloak
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-90"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-90"
            :style="`position: fixed; top: ${top}px; left: ${left}px; transform: translate(-50%, -120%); z-index: 9999;`"
            class="px-2 py-1 text-xs text-white bg-black rounded shadow-lg whitespace-nowrap pointer-events-none"
        >
            {{ $text }}
            <!-- Носик тултипа -->
            <div class="absolute left-1/2 -translate-x-1/2 top-full w-0 h-0 border-l-4 border-l-transparent border-r-4 border-r-transparent border-t-4 border-t-black/80"></div>
        </div>
    </template>
</div>