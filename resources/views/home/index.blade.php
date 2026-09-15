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
    <div class="space-y-12">
        
        <!-- 1. компонент ленты с поиском -->
        <x-home.feed 
            :search-config="$searchConfig" 
            :default-search-gender="$defaultSearchGender" 
            :auto-open-login="$autoOpenLogin" 
        />
        
        
    </div>
</x-layouts.web>