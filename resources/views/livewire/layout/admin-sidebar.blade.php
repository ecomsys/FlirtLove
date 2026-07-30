<?php

use App\Models\Broadcast;
use App\Models\Photo;
use App\Models\Chat;
use App\Models\PhotoComment;
use App\Models\Report;
use App\Models\User;
use App\Models\UserMatch;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Cache;

new class extends Component {
    /**
     * Получить данные для сайдбара
     */
    public function with(): array
    {
        $stats = Cache::remember('admin_sidebar_stats', 300, function () {
            return [
                // ✅ ИСПРАВЛЕНО: excludeAdmins() → where('is_admin', false)
                'newUsers' => User::whereDate('created_at', today())
                    ->where('is_admin', false)
                    ->count(),
                
                'pendingReports' => Report::where('status', 'pending')
                    ->whereHas('reportedUser', fn($q) => $q->where('is_admin', false))
                    ->count(),
                
                'pendingPhotos' => Photo::where('status', 'pending')
                    ->whereHas('user', fn($q) => $q->where('is_admin', false))
                    ->count(),
                
                'pendingComments' => PhotoComment::where('status', 'pending')
                    ->whereHas('user', fn($q) => $q->where('is_admin', false))
                    ->count(),
                
                'pendingBroadcasts' => Broadcast::whereIn('status', ['draft', 'scheduled'])->count(),
                
                'newMatchesToday' => UserMatch::whereDate('created_at', today())
                    ->whereHas('user1', fn($q) => $q->where('is_admin', false))
                    ->whereHas('user2', fn($q) => $q->where('is_admin', false))
                    ->count(),
                
                'activeChatsToday' => Chat::where('type', 'private')
                    ->whereDate('last_message_at', today())
                    ->whereHas('user1', fn($q) => $q->where('is_admin', false))
                    ->whereHas('user2', fn($q) => $q->where('is_admin', false))
                    ->count(),
                
                'unreadSupport' => Chat::where('type', 'support')
                    ->where('user1_id', auth()->id())
                    ->whereHas('participants', fn($q) => $q->where('user_id', auth()->id())->where('unread_count', '>', 0))
                    ->count(),
            ];
        });

        return $stats;
    }
};
?>

<nav class="flex flex-col gap-1 flex-1">    
    
    <!-- Главное -->
    <p class="px-3 text-xs uppercase text-muted-foreground/60 mt-2 mb-1">Главное</p>
    <a href="{{ route('admin.dashboard') }}" wire:navigate
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.dashboard') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-layout-dashboard class="w-4 h-4" />
        Дашборд
    </a>

    <!-- Пользователи -->
    <p class="px-3 text-xs uppercase text-muted-foreground/60 mt-4 mb-1">Пользователи</p>
    <a href="{{ route('admin.users.index') }}" wire:navigate title="Добавились сегодня"
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.users.*') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-users class="w-4 h-4" />
        Список юзеров
        @if ($newUsers > 0)
            <span class="ml-auto text-xs bg-primary/10 text-primary px-2 py-0.5 rounded-full">
                +{{ $newUsers }}
            </span>
        @endif
    </a>

    <a href="{{ route('admin.chats.index') }}" wire:navigate title="Активные чаты сегодня"
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.chats.index') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-messages-square class="w-4 h-4" />
        Чаты юзеров        
        @if ($activeChatsToday > 0)
            <span class="ml-auto text-xs bg-primary/10 text-success px-2 py-0.5 rounded-full">
                +{{ $activeChatsToday }}
            </span>
        @endif
    </a>
    
    <a href="{{ route('admin.dating') }}" wire:navigate title="Новые матчи сегодня"
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.dating') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-heart class="w-4 h-4" />
        Знакомства
        @if ($newMatchesToday > 0)
            <span class="ml-auto text-xs bg-destructive/10 text-destructive px-2 py-0.5 rounded-full">
                +{{ $newMatchesToday }}
            </span>
        @endif
    </a>

    <a href="{{ route('admin.reports') }}" wire:navigate title="Ожидают модерации"
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.reports') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-flag class="w-4 h-4" />
        Жалобы
        @if ($pendingReports > 0)
            <span class="ml-auto text-xs bg-destructive/10 text-destructive px-2 py-0.5 rounded-full">
                {{ $pendingReports }}
            </span>
        @endif
    </a>

    <a href="{{ route('admin.support.index') }}" wire:navigate title="Непрочитанные чаты"
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.support.*') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-life-buoy class="w-4 h-4" />
        Поддержка
        @if ($unreadSupport > 0)
            <span class="ml-auto text-xs bg-destructive text-white px-2 py-0.5 rounded-full animate-pulse">
                +{{ $unreadSupport }}
            </span>
        @endif
    </a>

    <!-- Контент -->
    <p class="px-3 text-xs uppercase text-muted-foreground/60 mt-4 mb-1">Контент</p>
    <a href="{{ route('admin.photos.index') }}" wire:navigate title="Ожидают модерации"
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.photos.*') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-image class="w-4 h-4" />
        Фотки
        @if ($pendingPhotos > 0)
            <span class="ml-auto text-xs bg-yellow-500/10 text-yellow-600 px-2 py-0.5 rounded-full">
                {{ $pendingPhotos }}
            </span>
        @endif
    </a>

    <a href="{{ route('admin.photo-comments') }}" wire:navigate title="Ожидают модерации"
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.photo-comments') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-message-circle class="w-4 h-4" />
        Комментарии к фоткам
        @if ($pendingComments > 0)
            <span class="ml-auto text-xs bg-yellow-500/10 text-yellow-600 px-2 py-0.5 rounded-full">
                {{ $pendingComments }}
            </span>
        @endif
    </a>   

    <!-- История действий -->
    <a href="{{ route('admin.action-history.index') }}" wire:navigate title="История действий"
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.action-history.*') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-scroll-text class="w-4 h-4" />
        История
    </a>
    
    <!-- Система -->
    <p class="px-3 text-xs uppercase text-muted-foreground/60 mt-4 mb-1">Система</p>

    <a href="{{ route('admin.broadcasts') }}" wire:navigate title="В очереди + черновики"
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.broadcasts') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-bell class="w-4 h-4" />
        Оповещения
        @if ($pendingBroadcasts > 0)
            <span class="ml-auto text-xs bg-blue-500/10 text-blue-600 px-2 py-0.5 rounded-full">
                {{ $pendingBroadcasts }}
            </span>
        @endif
    </a>

    <a href="{{ route('admin.logs') }}" wire:navigate
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.logs') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-file-text class="w-4 h-4" />
        Системные логи
    </a>

    <a href="{{ route('admin.finances') }}" wire:navigate
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.finances') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-wallet class="w-4 h-4" />
        Финансы
    </a>   
    

    <a href="{{ route('admin.settings') }}" wire:navigate
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.settings') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-settings class="w-4 h-4" />
        Настройки
    </a>   
</nav>