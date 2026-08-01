<?php

use App\Models\Photo;
use App\Models\PhotoComment;
use App\Models\Report;
use App\Models\User;
use App\Models\Verification;
use App\Models\FraudAlert;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route; // ФИКС 2: Добавлен Route
use Livewire\Volt\Component;

new class extends Component {
    public function with(): array
    {
        $stats = Cache::remember('admin_sidebar_stats', 300, function () {
            return [
                'newUsers' => User::excludeStaff()->whereDate('created_at', today())->count(),
                'pendingPhotos' => Photo::pending()->count(),
                'pendingVerifications' => Verification::pending()->count(),
                'pendingComments' => PhotoComment::pending()->count(),
                'pendingReports' => Report::pending()->count(),
                // ФИКС 3: Заменен несуществующий scopeHighSeverity на where
                'highSeverityAlerts' => FraudAlert::open()->where('severity', 'high')->count(),
            ];
        });

        return $stats;
    }
};
?>

<nav class="flex flex-col gap-1 flex-1">    
    
    <!-- 📊 Дашборд -->
    <p class="px-3 text-xs uppercase text-muted-foreground/60 mt-2 mb-1">Главное</p>
    @php $route = 'admin.dashboard'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }} {{ $exists ? '' : 'opacity-50 pointer-events-none' }}"
        @if(!$exists) title="В разработке" @endif>
        <x-lucide-layout-dashboard class="w-4 h-4" />
        Дашборд
    </a>

    <!-- 👥 Пользователи -->
    <p class="px-3 text-xs uppercase text-muted-foreground/60 mt-4 mb-1">Пользователи</p>
    @php $route = 'admin.users.index'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.users.*') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }} {{ $exists ? '' : 'opacity-50 pointer-events-none' }}"
        @if(!$exists) title="В разработке" @endif>
        <x-lucide-users class="w-4 h-4" />
        Все юзеры
        @if ($newUsers > 0 && $exists)
            <span class="ml-auto text-xs bg-primary/10 text-primary px-2 py-0.5 rounded-full">+{{ $newUsers }}</span>
        @endif
    </a>

    <!-- 🛡️ Модерация -->
    <p class="px-3 text-xs uppercase text-muted-foreground/60 mt-4 mb-1">Модерация</p>
    
    @php $route = 'admin.moderation.photos'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }} {{ $exists ? '' : 'opacity-50 pointer-events-none' }}"
        @if(!$exists) title="В разработке" @endif>
        <x-lucide-image class="w-4 h-4" />
        Фотографии
        @if ($pendingPhotos > 0 && $exists)
            <span class="ml-auto text-xs bg-yellow-500/10 text-yellow-600 px-2 py-0.5 rounded-full">{{ $pendingPhotos }}</span>
        @endif
    </a>

    @php $route = 'admin.moderation.verifications'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }} {{ $exists ? '' : 'opacity-50 pointer-events-none' }}"
        @if(!$exists) title="В разработке" @endif>
        <x-lucide-badge-check class="w-4 h-4" />
        Верификации
        @if ($pendingVerifications > 0 && $exists)
            <span class="ml-auto text-xs bg-blue-500/10 text-blue-600 px-2 py-0.5 rounded-full">{{ $pendingVerifications }}</span>
        @endif
    </a>

    @php $route = 'admin.moderation.comments'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }} {{ $exists ? '' : 'opacity-50 pointer-events-none' }}"
        @if(!$exists) title="В разработке" @endif>
        <x-lucide-message-circle class="w-4 h-4" />
        Комментарии
        @if ($pendingComments > 0 && $exists)
            <span class="ml-auto text-xs bg-yellow-500/10 text-yellow-600 px-2 py-0.5 rounded-full">{{ $pendingComments }}</span>
        @endif
    </a>

    @php $route = 'admin.moderation.reports'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }} {{ $exists ? '' : 'opacity-50 pointer-events-none' }}"
        @if(!$exists) title="В разработке" @endif>
        <x-lucide-flag class="w-4 h-4" />
        Жалобы
        @if ($pendingReports > 0 && $exists)
            <span class="ml-auto text-xs bg-destructive/10 text-destructive px-2 py-0.5 rounded-full">{{ $pendingReports }}</span>
        @endif
    </a>

    <!-- 💬 Коммуникация -->
    <p class="px-3 text-xs uppercase text-muted-foreground/60 mt-4 mb-1">Коммуникация</p>
    @php $route = 'admin.communication.chats'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.communication.chats.*') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }} {{ $exists ? '' : 'opacity-50 pointer-events-none' }}"
        @if(!$exists) title="В разработке" @endif>
        <x-lucide-messages-square class="w-4 h-4" />
        Чаты
    </a>

     @php $route = 'admin.communication.support'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.communication.support.*') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }} {{ $exists ? '' : 'opacity-50 pointer-events-none' }}"
        @if(!$exists) title="В разработке" @endif>
        <x-lucide-messages-square class="w-4 h-4" />
        Чат поддержки
    </a>

    
    @php $route = 'admin.communication.diaries'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.communication.diaries.*') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }} {{ $exists ? '' : 'opacity-50 pointer-events-none' }}"
        @if(!$exists) title="В разработке" @endif>
        <x-lucide-book-open class="w-4 h-4" />
        Дневники
    </a>

    @php $route = 'admin.communication.stop-words'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }} {{ $exists ? '' : 'opacity-50 pointer-events-none' }}"
        @if(!$exists) title="В разработке" @endif>
        <x-lucide-ban class="w-4 h-4" />
        Стоп-слова
    </a>

    <!-- 💳 Финансы -->
    <p class="px-3 text-xs uppercase text-muted-foreground/60 mt-4 mb-1">Финансы</p>
    @php $route = 'admin.finances.transactions'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.finances.transactions.*') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }} {{ $exists ? '' : 'opacity-50 pointer-events-none' }}"
        @if(!$exists) title="В разработке" @endif>
        <x-lucide-wallet class="w-4 h-4" />
        Транзакции
    </a>

    @php $route = 'admin.finances.plans'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.finances.plans.*') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }} {{ $exists ? '' : 'opacity-50 pointer-events-none' }}"
        @if(!$exists) title="В разработке" @endif>
        <x-lucide-crown class="w-4 h-4" />
        Тарифы VIP
    </a>

    @php $route = 'admin.finances.gifts'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.finances.gifts.*') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }} {{ $exists ? '' : 'opacity-50 pointer-events-none' }}"
        @if(!$exists) title="В разработке" @endif>
        <x-lucide-gift class="w-4 h-4" />
        Подарки
    </a>

    <!-- 🚨 Безопасность -->
    <p class="px-3 text-xs uppercase text-muted-foreground/60 mt-4 mb-1">Безопасность</p>
    @php $route = 'admin.security.fraud-alerts'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }} {{ $exists ? '' : 'opacity-50 pointer-events-none' }}"
        @if(!$exists) title="В разработке" @endif>
        <x-lucide-shield-alert class="w-4 h-4" />
        Антифрод
        @if ($highSeverityAlerts > 0 && $exists)
            <span class="ml-auto text-xs bg-destructive text-white px-2 py-0.5 rounded-full animate-pulse">{{ $highSeverityAlerts }}</span>
        @endif
    </a>

    @php $route = 'admin.security.blocks'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }} {{ $exists ? '' : 'opacity-50 pointer-events-none' }}"
        @if(!$exists) title="В разработке" @endif>
        <x-lucide-user-x class="w-4 h-4" />
        Блокировки
    </a>

    <!-- ⚙️ Система -->
    <p class="px-3 text-xs uppercase text-muted-foreground/60 mt-4 mb-1">Система</p>
    @php $route = 'admin.system.settings'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }} {{ $exists ? '' : 'opacity-50 pointer-events-none' }}"
        @if(!$exists) title="В разработке" @endif>
        <x-lucide-settings class="w-4 h-4" />
        Настройки
    </a>

    @php $route = 'admin.system.pages'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }} {{ $exists ? '' : 'opacity-50 pointer-events-none' }}"
        @if(!$exists) title="В разработке" @endif>
        <x-lucide-file-text class="w-4 h-4" />
        Страницы
    </a>

    @php $route = 'admin.system.broadcasts'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }} {{ $exists ? '' : 'opacity-50 pointer-events-none' }}"
        @if(!$exists) title="В разработке" @endif>
        <x-lucide-megaphone class="w-4 h-4" />
        Рассылки
    </a>

    @php $route = 'admin.system.admin-logs'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }} {{ $exists ? '' : 'opacity-50 pointer-events-none' }}"
        @if(!$exists) title="В разработке" @endif>
        <x-lucide-scroll-text class="w-4 h-4" />
        Логи Админа
    </a>

    @php $route = 'admin.system.laravel-logs'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }} {{ $exists ? '' : 'opacity-50 pointer-events-none' }}"
        @if(!$exists) title="В разработке" @endif>
        <x-lucide-terminal class="w-4 h-4" />
        Логи Системы
    </a>

</nav>