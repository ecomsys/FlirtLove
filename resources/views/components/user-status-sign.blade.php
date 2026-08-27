{{-- <x-user-status-sign :user="$user" /> --}}

@props(['user'])

@if($user?->trashed())
    <span class="text-blue-400 font-bold leading-none" title="Статус: Деактивирован (Удален)">
        !
    </span>
@endif

@if($user?->status === 'banned')
    <span class="text-destructive font-bold leading-none" title="Статус: Забанен. Причина: {{ $user->ban_reason ?? 'не указана' }}">
        !
    </span>
@endif

@if($user?->status === 'shadowbanned')
    <span class="text-yellow-500 font-bold leading-none" title="Статус: Теневой бан. Причина: {{ $user->ban_reason ?? 'не указана' }}">
        !
    </span>
@endif
{{-- active — ничего не отображаем --}}