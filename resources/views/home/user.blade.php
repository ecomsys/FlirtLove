<x-layouts.web>
    <!-- === САЙДБАР (Левая колонка) === -->
    <x-slot:sidebar>
        @guest
            @include('livewire.web.sidebar.guest')
        @else
            @include('livewire.web.sidebar.inapp')
        @endguest
    </x-slot:sidebar>

    <!-- === ОСНОВНОЙ КОНТЕНТ === -->
    <div class="space-y-6">
        <x-home.user :user="$user" />
    </div>
</x-layouts.web>