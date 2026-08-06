@component('mail::message')
    {{-- greeting --}}
    {{ $greeting ?? 'Здравствуйте!' }}

    {{-- Твой сырой HTML --}}
    {!! $content !!}

    {{-- Подвал письма (Laravel сам добавит) --}}
@endcomponent