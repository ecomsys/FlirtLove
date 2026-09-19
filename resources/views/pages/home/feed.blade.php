<x-layouts.web>
    <!-- === САЙДБАР === -->
    <x-slot:sidebar>
        @guest
            <x-sidebar.guest />
        @else
            <x-sidebar.inapp />
        @endguest
    </x-slot:sidebar>

    <!-- === ОСНОВНОЙ КОНТЕНТ === -->
    <div class="space-y-12">
        <!-- is-search-page не передаём, по умолчанию false -->
        <x-home.feed :search-config="$searchConfig" :adv-filters="$advFilters" :default-search-gender="$defaultSearchGender" :auto-open-login="$autoOpenLogin" />
    </div>
</x-layouts.web>
