{{-- <x-user-status-sign :user="$user" /> --}}

@props(['user'])

@if($user->status === 'banned')
    <span class="text-destructive font-bold leading-none" title="Статус: Забанен. Причина: {{ $user->ban_reason ?? 'не указана' }}">
        !
    </span>
@elseif($user->status === 'shadowbanned')
    <span class="text-yellow-500 font-bold leading-none" title="Статус: Теневой бан. Причина: {{ $user->ban_reason ?? 'не указана' }}">
        !
    </span>
@elseif($user->status === 'deactivated')
    <span class="text-blue-400 font-bold leading-none" title="Статус: Деактивирован">
        !
    </span>
@endif
{{-- active — ничего не отображаем --}}