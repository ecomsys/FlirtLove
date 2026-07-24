@props(['breadcrumbs' => []])

@if (!empty($breadcrumbs))

    <div class="max-w-7xl mx-auto pt-4 px-4 sm:px-6 lg:px-8">
        <x-ui.breadcrumb>
            <x-ui.breadcrumb-list>
                @foreach ($breadcrumbs as $crumb)
                    <x-ui.breadcrumb-item>
                        @if ($loop->last)
                            <x-ui.badge variant="outline" class="border-primary text-primary font-normal">
                                {{ $crumb['title'] }}
                            </x-ui.badge>
                        @else
                            <x-ui.badge variant="outline" href="{{ $crumb['url'] }}" wire:navigate
                                class="text-muted-foreground hover:text-foreground font-normal">
                                {{ $crumb['title'] }}
                            </x-ui.badge>
                        @endif
                    </x-ui.breadcrumb-item>
                    @if (!$loop->last)
                        <x-ui.breadcrumb-separator />
                    @endif
                @endforeach
            </x-ui.breadcrumb-list>
        </x-ui.breadcrumb>
    </div>

@endif
