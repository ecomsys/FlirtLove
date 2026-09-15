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
        <!-- Передаём is-search-page="true" -->
        <x-home.feed 
            :search-config="$searchConfig" 
            :adv-filters="$advFilters"  
            :default-search-gender="$defaultSearchGender" 
            :auto-open-login="$autoOpenLogin" 
            is-search-page="true"
        />
    </div>
</x-layouts.web>