@props(['fixed' => false])

<div wire:loading.delay 
     class="{{ $fixed ? 'fixed left-[16rem] top-[4rem] right-0 bottom-0' : 'absolute inset-0' }} z-10 grid place-items-center bg-background/50">
     <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2">
        <x-lucide-loader-circle class="w-8 h-8 animate-spin text-primary" />
    </div>
</div>