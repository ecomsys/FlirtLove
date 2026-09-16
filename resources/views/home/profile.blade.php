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
        <x-home.profile 
            :user="$user" 
            :is-favorited="$isFavorited" 
            :is-blocked="$isBlocked" 
            :is-reported="$isReported" 
        />
    </div>
</x-layouts.web>