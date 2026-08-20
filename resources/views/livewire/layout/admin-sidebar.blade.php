<?php

use App\Models\Photo;
use App\Models\PhotoComment;
use App\Models\Report;
use App\Models\User;
use App\Models\Verification;
use App\Models\FraudAlert;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Component;

new class extends Component {
    public function with(): array
    {
        // Статистику считаем только для тех, кто имеет к ней доступ
        $stats = [];
        
        if (auth()->user()->role === 'admin' || auth()->user()->role === 'moderator') {
            $stats = Cache::remember('admin_sidebar_stats', 300, function () {
                return [
                    'newUsers' => User::excludeStaff()->whereDate('created_at', today())->count(),
                    'pendingPhotos' => Photo::pending()->count(),
                    'pendingVerifications' => Verification::pending()->count(),
                    'pendingComments' => PhotoComment::pending()->count(),
                    'pendingReports' => Report::pending()->count(),
                    'highSeverityAlerts' => FraudAlert::open()->where('severity', 'high')->count(),
                ];
            });
        }

        return $stats;
    }
};
?>

<nav class="flex flex-col gap-1 flex-1 pb-8 leading-[0.9]">    
    
    <!-- 📊 Дашборд (Доступно всем staff) -->
    <p class="px-3 text-xs uppercase text-muted-foreground/60 mt-2 mb-1">Главное</p>
    @php $route = 'admin.dashboard'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-layout-dashboard class="w-4 h-4" />
        Дашборд
    </a>

     @php $route = 'admin.media.index'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-layout-dashboard class="w-4 h-4" />
        Медиа
    </a>


    <!-- 👥 Пользователи (Доступно всем staff) -->
    <p class="px-3 text-xs uppercase text-muted-foreground/60 mt-4 mb-1">Пользователи</p>
    @php $route = 'admin.users.index'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.users.*') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-users class="w-4 h-4" />
        Все юзеры
        @if (($newUsers ?? 0) > 0 && $exists)
            <span class="ml-auto text-xs bg-primary/10 text-primary px-2 py-0.5 rounded-full">+{{ $newUsers }}</span>
        @endif
    </a>

    @if(in_array(auth()->user()->role, ['admin', 'moderator']))
    <!-- 🛡️ Модерация (Только Admin, Moderator) -->
    <p class="px-3 text-xs uppercase text-muted-foreground/60 mt-4 mb-1">Модерация</p>
    
    @php $route = 'admin.moderation.photos'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-image class="w-4 h-4" />
        Фотографии
        @if (($pendingPhotos ?? 0) > 0 && $exists)
            <span class="ml-auto text-xs bg-yellow-500/10 text-yellow-600 px-2 py-0.5 rounded-full">{{ $pendingPhotos }}</span>
        @endif
    </a>

    @php $route = 'admin.moderation.photo-comments'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-message-circle class="w-4 h-4" />
        Комментарии к фото
        @if (($pendingComments ?? 0) > 0 && $exists)
            <span class="ml-auto text-xs bg-yellow-500/10 text-yellow-600 px-2 py-0.5 rounded-full">{{ $pendingComments }}</span>
        @endif
    </a>

    
        @php $route = 'admin.moderation.diary.index'; $exists = Route::has($route); @endphp
        <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
            class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
            <x-lucide-book-open class="w-4 h-4" />
            Дневники
        </a>

     @php $route = 'admin.moderation.diary.comments'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-heart class="w-4 h-4" />
        Комментарии к дневникам
    </a>

    @php $route = 'admin.moderation.dating'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-heart class="w-4 h-4" />
        Знакомства
    </a>

    @php $route = 'admin.moderation.reports'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-flag class="w-4 h-4" />
        Жалобы
        @if (($pendingReports ?? 0) > 0 && $exists)
            <span class="ml-auto text-xs bg-destructive/10 text-destructive px-2 py-0.5 rounded-full">{{ $pendingReports }}</span>
        @endif
    </a>
    @endif

    <!-- 💬 Коммуникация (Доступно всем, но чаты/дневники/стоп-слова - только админ/модер) -->
    <p class="px-3 text-xs uppercase text-muted-foreground/60 mt-4 mb-1">Коммуникация</p>
    
    @if(auth()->user()->role === 'support')
        @php $route = 'admin.communication.support'; $exists = Route::has($route); @endphp
        <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
            class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.communication.support') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
            <x-lucide-headset class="w-4 h-4" />
            Мой чат поддержки
        </a>
    @endif

    @if(in_array(auth()->user()->role, ['admin', 'moderator','support']))
        @php $route = 'admin.communication.chats'; $exists = Route::has($route); @endphp
        <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
            class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.communication.chats') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
            <x-lucide-messages-square class="w-4 h-4" />
            Все чаты
        </a>

        @php $route = 'admin.communication.support'; $exists = Route::has($route); @endphp
        <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
            class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.communication.support') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
            <x-lucide-headset class="w-4 h-4" />
            Чат поддержки
        </a>

         @php $route = 'admin.communication.templates'; $exists = Route::has($route); @endphp
        <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
            class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.communication.templates') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
            <x-lucide-headset class="w-4 h-4" />
            Шаблоны поддержки
        </a>

        @php $route = 'admin.communication.stop-words.index'; $exists = Route::has($route); @endphp
        <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
            class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
            <x-lucide-shield-ban class="w-4 h-4" />
            Стоп-слова
        </a>
    @endif

    @if(in_array(auth()->user()->role, ['admin', 'moderator']))
    <!-- 🚨 Безопасность (Только Admin, Moderator) -->
    <p class="px-3 text-xs uppercase text-muted-foreground/60 mt-4 mb-1">Безопасность</p>
    
    @php $route = 'admin.security.fraud-alerts.index'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-siren class="w-4 h-4" />
        Антифрод
        {{-- @if (($highSeverityAlerts ?? 0) > 0 && $exists)
            <span class="ml-auto text-xs bg-destructive text-white px-2 py-0.5 rounded-full animate-pulse">{{ $highSeverityAlerts }}</span>
        @endif --}}
    </a>

    @php $route = 'admin.security.block-signals.index'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-user-x class="w-4 h-4" />
        Сигналы блокировки
    </a>
    @endif

    @if(auth()->user()->role === 'admin')
    <!-- 💳 Финансы (Только Admin) -->
    <p class="px-3 text-xs uppercase text-muted-foreground/60 mt-4 mb-1">Финансы</p>
    
    @php $route = 'admin.finances.transactions'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.finances.transactions') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-wallet class="w-4 h-4" />
        Транзакции
    </a>

    @php $route = 'admin.finances.subscriptions'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-crown class="w-4 h-4" />
        Тарифы VIP
    </a>

    @php $route = 'admin.finances.gifts'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.finances.gifts') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-gift class="w-4 h-4" />
        Подарки
    </a>

    <!-- ⚙️ Система (Только Admin) -->
    <p class="px-3 text-xs uppercase text-muted-foreground/60 mt-4 mb-1">Система</p>
    
    @php $route = 'admin.system.roles'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.system.roles') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-shield-check class="w-4 h-4" />
        Роли и Админы
    </a>
    
    @php $route = 'admin.system.broadcasts.index'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.system.broadcasts.index') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-megaphone class="w-4 h-4" />
        Рассылка уведомлений
    </a>

     @php $route = 'admin.system.journal-logs'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.system.journal-logs') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-scroll-text class="w-4 h-4" />
        Журнал действий
    </a>

      @php $route = 'admin.system.geo-locations.index'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-scroll-text class="w-4 h-4" />
        Блокировка по Geo IP
    </a>

    @php $route = 'admin.system.blog.index'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-file-text class="w-4 h-4" />
        Блог
    </a>

    @php $route = 'admin.system.pages.index'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.system.pages.index') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-file-text class="w-4 h-4" />
        Страницы
    </a>
    
    @php $route = 'admin.system.settings'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.system.settings') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-settings class="w-4 h-4" />
        Настройки
    </a>   

    @php $route = 'admin.system.laravel-logs'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.system.laravel-logs') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-terminal class="w-4 h-4" />
        Логи Системы
    </a>
    @endif

</nav>