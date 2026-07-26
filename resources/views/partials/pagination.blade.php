@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="flex flex-row-reverse items-center justify-between gap-2">
        <div class="flex items-center gap-1">
            <!-- Кнопка "Назад" -->
            @if ($paginator->onFirstPage())
                <span class="inline-flex items-center justify-center w-9 h-9 rounded-md border border-border text-muted-foreground/50 cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                </span>
            @else
                <button wire:click="previousPage" wire:loading.attr="disabled" class="inline-flex items-center justify-center w-9 h-9 rounded-md border border-border bg-background hover:bg-accent text-foreground transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                </button>
            @endif

            <!-- Номера страниц и троеточия -->
            @foreach ($elements as $element)
                {{-- 1. Если это строка, значит Laravel прислал троеточие --}}
                @if (is_string($element))
                    <span class="w-9 h-9 flex items-center justify-center text-muted-foreground select-none">•••</span>
                @endif

                {{-- 2. Если это массив, значит это группы кнопок страниц --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="inline-flex items-center justify-center w-9 h-9 rounded-md bg-primary text-primary-foreground text-sm font-medium" aria-current="page">
                                {{ $page }}
                            </span>
                        @else
                            <button wire:click="gotoPage({{ $page }})" class="inline-flex items-center justify-center w-9 h-9 rounded-md border border-border bg-background hover:bg-accent text-foreground text-sm font-medium transition-colors">
                                {{ $page }}
                            </button>
                        @endif
                    @endforeach
                @endif
            @endforeach

            <!-- Кнопка "Вперед" -->
            @if ($paginator->hasMorePages())
                <button wire:click="nextPage" wire:loading.attr="disabled" class="inline-flex items-center justify-center w-9 h-9 rounded-md border border-border bg-background hover:bg-accent text-foreground transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                </button>
            @else
                <span class="inline-flex items-center justify-center w-9 h-9 rounded-md border border-border text-muted-foreground/50 cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                </span>
            @endif
        </div>

        <!-- Текст "Показано с 1 по 6 из 10" -->
        <div class="text-xs text-muted-foreground hidden sm:block">
            {{ __('Показано') }} {{ $paginator->firstItem() }} {{ __('до') }} {{ $paginator->lastItem() }} {{ __('из') }} {{ $paginator->total() }}
        </div>
    </nav>
@endif