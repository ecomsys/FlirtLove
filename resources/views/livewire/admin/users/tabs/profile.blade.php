<?php

use App\Models\User;
use Livewire\Volt\Component;

new class extends Component 
{
    public User $user;

    // Хелпер для перевода ID опций в текст (из нашего файла profile_options)
    public function getOptionLabel(string $type, int $value): ?string
    {
        return config("profile_options.{$type}.{$value}");
    }
}; 
?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    {{-- ЛЕВАЯ КОЛОНКА --}}
    <div class="space-y-4">
        <div class="p-4 bg-muted/20 rounded-lg border border-border">
            <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold">Основная информация</p>
            <div class="divide-y divide-border/50">
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Пол</span>
                    <span class="text-sm font-medium">
                        {{ $user->profile?->gender === 'male' ? 'Мужской' : ($user->profile?->gender === 'female' ? 'Женский' : 'Не указан') }}
                    </span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Возраст</span>
                    <span class="text-sm font-medium">
                        {{ $user->profile?->age ? $user->profile->age . ' лет' : 'Не указан' }}
                    </span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Город</span>
                    <span class="text-sm font-medium">{{ $user->profile?->city ?? 'Не указан' }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Цель</span>
                    <span class="text-sm font-medium">{{ $user->profile?->dating_goal ?? 'Не указана' }}</span>
                </div>
            </div>
        </div>

        <div class="p-4 bg-muted/20 rounded-lg border border-border">
            <p class="text-xs text-muted-foreground uppercase mb-1">О себе</p>
            <p class="text-sm text-muted-foreground">{{ $user->profile?->bio ?? 'Пусто' }}</p>
        </div>

        <div class="p-4 bg-muted/20 rounded-lg border border-border">
            <p class="text-xs text-muted-foreground uppercase mb-2">Интересы</p>
            <div class="flex flex-wrap gap-1">
                @foreach ($user->profile?->interests ?? [] as $interest)
                    <x-ui.badge variant="secondary" size="xs">{{ $interest }}</x-ui.badge>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ПРАВАЯ КОЛОНКА --}}
    <div class="space-y-4">
        <div class="p-4 bg-muted/20 rounded-lg border border-border">
            <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold">Статусы аккаунта</p>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-xs text-muted-foreground mb-1">Подписка</p>
                    @if ($user->has_active_premium)
                        <x-ui.badge variant="warning" size="xs"><x-lucide-crown class="w-3 h-3 inline mr-1" />Premium до {{ $user->premium_expires_at?->format('d.m.Y') }}</x-ui.badge>
                    @else
                        <x-ui.badge variant="secondary" size="xs">Бесплатный</x-ui.badge>
                    @endif
                </div>
                <div>
                    <p class="text-xs text-muted-foreground mb-1">Верификация</p>
                    @if ($user->is_verified) <x-ui.badge variant="success" size="xs">Верифицирован</x-ui.badge>
                    @else <x-ui.badge variant="destructive" size="xs">Не верифицирован</x-ui.badge> @endif
                </div>
                <div>
                    <p class="text-xs text-muted-foreground mb-1">Онбординг</p>
                    @if ($user->has_completed_onboarding) <x-ui.badge variant="success" size="xs">Пройден</x-ui.badge>
                    @else <x-ui.badge variant="warning" size="xs">Не завершен</x-ui.badge> @endif
                </div>
                <div>
                    <p class="text-xs text-muted-foreground mb-1">Email</p>
                    @if ($user->email_verified_at) <x-ui.badge variant="success" size="xs">Подтвержден</x-ui.badge>
                    @else <x-ui.badge variant="destructive" size="xs">Не подтвержден</x-ui.badge> @endif
                </div>
            </div>
        </div>

        <div class="p-4 bg-muted/20 rounded-lg border border-border">
            <p class="text-xs text-muted-foreground uppercase mb-2 font-semibold">Системные данные</p>
            <div class="divide-y divide-border/50">
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Регистрация</span>
                    <span class="text-sm font-medium">{{ $user->created_at->format('d.m.Y H:i') }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Последний визит</span>
                    <span class="text-sm font-medium">{{ $user->last_seen ? $user->last_seen->diffForHumans() : 'Никогда' }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">IP адрес</span>
                    <span class="text-sm font-mono font-medium">{{ $user->last_login_ip ?? 'Нет данных' }}</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-xs text-muted-foreground">Device ID</span>
                    <span class="text-sm font-mono font-medium truncate ml-4">{{ $user->device_id ?? 'Нет данных' }}</span>
                </div>
            </div>
        </div>
    </div>
</div>