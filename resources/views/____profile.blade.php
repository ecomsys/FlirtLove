@php
    $breadcrumbs = [
        ['title' => __('common.home'), 'url' => route('home')],
        ['title' => __('common.profile'), 'url' => route('profile')],
    ];
@endphp

<x-app-layout :breadcrumbs="$breadcrumbs">
    <div class="pt-6 pb-24">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- update profile info --}}
            <x-ui.card class="w-full py-6 px-4 sm:px-6 lg:px-8">
                <livewire:profile.update-profile-information-form />
            </x-ui.card>

            {{-- change password --}}
            <x-ui.card class="w-full py-6 px-4 sm:px-6 lg:px-8">
                <livewire:profile.update-password-form />
            </x-ui.card>

            {{-- delete account --}}
            <x-ui.card class="w-full py-6 px-4 sm:px-6 lg:px-8">
                <livewire:profile.delete-user-form />
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
