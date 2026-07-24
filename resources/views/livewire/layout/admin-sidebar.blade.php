<?php

use App\Models\Broadcast;
use App\Models\Photo;
use App\Models\PhotoComment;
use App\Models\Report;
use App\Models\User;
use Livewire\Volt\Component;

new class extends Component {
    /**
     * Получить данные для сайдбара
     */
    public function with(): array
    {
        return [
            'newUsers' => User::whereDate('created_at', today())->count(),
            'pendingReports' => Report::where('status', 'pending')->count(),
            'pendingPhotos' => Photo::where('status', 'pending')->count(),
            'pendingComments' => PhotoComment::where('status', 'pending')->count(),
            'pendingBroadcasts' => Broadcast::whereIn('status', ['draft', 'scheduled'])->count(),
        ];
    }
}; ?>


<nav class="flex flex-col gap-1 flex-1">    
    
    <!-- Главное -->
    <p class="px-3 text-xs uppercase text-muted-foreground/60 mt-2 mb-1">Главное</p>
    <a href="{{ route('admin.dashboard') }}"
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.dashboard') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-layout-dashboard class="w-4 h-4" />
        Дашборд
    </a>

    <!-- Пользователи -->
    <p class="px-3 text-xs uppercase text-muted-foreground/60 mt-4 mb-1">Пользователи</p>
    <a href="{{ route('admin.users.index') }}"
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.users.*') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-users class="w-4 h-4" />
        Список юзеров
        @if ($newUsers > 0)
            <span class="ml-auto text-xs bg-primary/10 text-primary px-2 py-0.5 rounded-full">
                +{{ $newUsers }}
            </span>
        @endif
    </a>

    <a href="{{ route('admin.reports') }}"
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.reports') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-flag class="w-4 h-4" />
        Жалобы
        @if ($pendingReports > 0)
            <span class="ml-auto text-xs bg-destructive/10 text-destructive px-2 py-0.5 rounded-full">
                {{ $pendingReports }}
            </span>
        @endif
    </a>

    <!-- Контент -->
    <p class="px-3 text-xs uppercase text-muted-foreground/60 mt-4 mb-1">Контент</p>
    <a href="{{ route('admin.moderate-photos.index') }}"
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.moderate-photos.*') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-image class="w-4 h-4" />
        Модерация фото
        @if ($pendingPhotos > 0)
            <span class="ml-auto text-xs bg-yellow-500/10 text-yellow-600 px-2 py-0.5 rounded-full">
                {{ $pendingPhotos }}
            </span>
        @endif
    </a>

    <a href="{{ route('admin.moderate-photo-comments') }}"
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.moderate-photo-comments') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-message-circle class="w-4 h-4" />
        Комментарии к фото
        @if ($pendingComments > 0)
            <span class="ml-auto text-xs bg-yellow-500/10 text-yellow-600 px-2 py-0.5 rounded-full">
                {{ $pendingComments }}
            </span>
        @endif
    </a>

    <!-- Система -->
    <p class="px-3 text-xs uppercase text-muted-foreground/60 mt-4 mb-1">Система</p>

    <a href="{{ route('admin.broadcasts') }}"
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.broadcasts') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-bell class="w-4 h-4" />
        Оповещения
        @if ($pendingBroadcasts > 0)
            <span class="ml-auto text-xs bg-blue-500/10 text-blue-600 px-2 py-0.5 rounded-full">
                {{ $pendingBroadcasts }}
            </span>
        @endif
    </a>

    <a href="{{ route('admin.logs') }}"
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.logs') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-file-text class="w-4 h-4" />
        Системные логи
    </a>

    <a href="{{ route('admin.finances') }}"
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.finances') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-wallet class="w-4 h-4" />
        Финансы
    </a>

    <a href="{{ route('admin.settings') }}"
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.settings') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-settings class="w-4 h-4" />
        Настройки
    </a>   
</nav>
