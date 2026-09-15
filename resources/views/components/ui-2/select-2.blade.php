@props([
    'placeholder' => 'Выберите',
    'options' => [],
    'excludeValues' => '[]'
])

<div
    x-data="{
        open: false,
        value: '',
        activeIndex: 0,
        allOptions: @js($options),
        get excludeVals() {
            return {{ $excludeValues }};
        },
        get label() {
            const selected = this.allOptions.find(o => o.value === this.value);
            return selected ? selected.label : '';
        },
        get filteredOptions() {
            return this.allOptions.filter(o => o.value === 'none' || !this.excludeVals.includes(o.value));
        }
    }"
    x-modelable="value"
    {{ $attributes->merge(['class' => 'relative w-full']) }}
>
    <button
        type="button"
        @click="open = !open; if(open) { const i = filteredOptions.findIndex(o => o.value === value); activeIndex = i !== -1 ? i : 0; }"
        @keydown.down.prevent.stop="if(!open) { open = true; activeIndex = 0; } else if (activeIndex < filteredOptions.length - 1) activeIndex++"
        @keydown.up.prevent.stop="if(open && activeIndex > 0) activeIndex--"
        @keydown.enter.prevent.stop="if(open && filteredOptions[activeIndex]) { value = filteredOptions[activeIndex].value; open = false; }"
        @keydown.escape.prevent.stop="open = false"
        role="combobox"
        aria-haspopup="listbox"
        :aria-expanded="open"
        class="border-input focus-visible:border-ring focus-visible:ring-ring/50 flex w-full items-center justify-between gap-2 rounded-md border bg-transparent px-3 py-2 text-sm whitespace-nowrap shadow-xs outline-none focus-visible:ring-[3px] data-[size=default]:h-9 hover:bg-accent transition-colors"
        :data-state="open ? 'open' : 'closed'"
    >
        <span class="truncate" :class="{ 'text-muted-foreground': !label }" x-text="label || '{{ $placeholder }}'"></span>
        <svg class="size-4 opacity-75 shrink-0 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <div
        x-show="open"
        @click.outside="open = false"
        @keydown.down.prevent="if (activeIndex < filteredOptions.length - 1) activeIndex++"
        @keydown.up.prevent="if (activeIndex > 0) activeIndex--"
        @keydown.enter.prevent="if(filteredOptions[activeIndex]) { value = filteredOptions[activeIndex].value; open = false; }"
        @keydown.escape.prevent="open = false"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="bg-popover text-popover-foreground absolute z-50 mt-1 max-h-60 w-full origin-top overflow-y-auto little-scroll rounded-md border shadow-md"
        style="display: none;"
        role="listbox"
        tabindex="-1"
    >
        <div class="p-1">
           <template x-for="(opt, index) in filteredOptions" :key="opt.value">
                <button
                    type="button"
                    :data-index="index"
                    x-effect="if (activeIndex === index) $el.scrollIntoView({ block: 'nearest' })"
                    @click="value = opt.value; open = false"
                    role="option"
                    :aria-selected="value === opt.value"
                    tabindex="-1"
                    class=" text-left hover:bg-accent hover:text-accent-foreground relative flex w-full cursor-default items-center gap-2 rounded-sm py-1.5 pr-8 pl-2 text-sm outline-hidden select-none transition-colors"
                    :class="value === opt.value || activeIndex === index ? 'bg-accent text-accent-foreground' : ''"
                >
                    <span x-text="opt.label"></span>
                    <span x-show="value === opt.value" class="absolute right-2 flex size-3.5 items-center ">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </span>
                </button>
            </template>
        </div>
    </div>
</div>