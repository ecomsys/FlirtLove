<x-layouts.web>
    <!-- === САЙДБАР (Левая колонка) === -->
    <x-slot:sidebar>
        @guest
            <x-sidebar.guest />
        @else
            <x-sidebar.inapp />
        @endguest
    </x-slot:sidebar>

    <!-- === ОСНОВНОЙ КОНТЕНТ === -->
    <div class="space-y-6">
        <x-home.user :user="$user" :is-favorited="$isFavorited" :is-blocked="$isBlocked" :is-reported="$isReported" />
    </div>
</x-layouts.web>
