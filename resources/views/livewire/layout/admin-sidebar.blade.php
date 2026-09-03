<?php

use App\Models\Photo;
use App\Models\PhotoComment;
use App\Models\Report;
use App\Models\User;
use App\Models\Diary;
use App\Models\DiaryComment;
use App\Models\FraudAlert;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Component;

new class extends Component {
    public function with(): array
    {
        $stats = [];
        
        // Считаем статистику только если юзер имеет право её видеть
        if (in_array(auth()->user()->role, ['admin', 'moderator'])) {
            $stats = Cache::remember('admin_sidebar_stats', 300, function () {
                return [
                    'pendingPhotos'        => Photo::pending()->count(),
                    'pendingPhotoComments' => PhotoComment::pending()->count(),
                    'pendingDiaries'       => Diary::pending()->count(),
                    'pendingDiaryComments' => DiaryComment::where('status', 'pending')->count(),
                    'pendingReports'       => Report::pending()->count(),
                    'openFraudAlerts'      => FraudAlert::open()->count(),
                ];
            });
        }

        return $stats;
    }
};
?>
<nav class="flex flex-col gap-1 flex-1 pb-8 leading-[0.9]">    
    
    <!-- 📊 Дашборд -->
    <p class="px-3 text-xs uppercase text-muted-foreground/60 mt-2 mb-1">Главное</p>
    @php $route = 'admin.dashboard'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-layout-dashboard class="w-4 h-4" />
        Дашборд
    </a>

    @php $route = 'admin.users.index'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.users.*') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-users class="w-4 h-4" />
        Все юзеры
    </a>

     @if(auth()->user()->role === 'admin')
        <!-- 💳 Финансы -->
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

        <!-- ⚙️ Система -->
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

        @php $route = 'admin.system.admin-logs'; $exists = Route::has($route); @endphp
        <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
            class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
            <x-lucide-scroll-text class="w-4 h-4" />
            Журнал админов
        </a>

        @php $route = 'admin.system.geo-ip-locations.index'; $exists = Route::has($route); @endphp
        <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
            class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
            <x-lucide-scroll-text class="w-4 h-4" />
            Geo IP локации
        </a>

        @php $route = 'admin.media.index'; $exists = Route::has($route); @endphp
        <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
            class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
            <x-lucide-image class="w-4 h-4" />
            Медиа
        </a>

        @php $route = 'admin.system.blog.index'; $exists = Route::has($route); @endphp
        <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}"
            class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
            <x-lucide-file-text class="w-4 h-4" />
            Блог
        </a>

        @php $route = 'admin.system.pages.index'; $exists = Route::has($route); @endphp
        <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}"
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

    <!-- 💬 Коммуникация -->
    <p class="px-3 text-xs uppercase text-muted-foreground/60 mt-4 mb-1">Коммуникация</p>
    
    @php $route = 'admin.communication.support'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.communication.support') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-headset class="w-4 h-4" />
        Чат поддержки
    </a>

    @php $route = 'admin.communication.templates'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.communication.templates') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-files class="w-4 h-4" />
        Шаблоны поддержки
    </a>

    @if(in_array(auth()->user()->role, ['admin', 'moderator']))
        @php $route = 'admin.communication.chats'; $exists = Route::has($route); @endphp
        <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
            class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin.communication.chats') ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
            <x-lucide-messages-square class="w-4 h-4" />
            Все чаты
        </a>

        @php $route = 'admin.communication.stop-words.index'; $exists = Route::has($route); @endphp
        <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
            class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
            <x-lucide-shield-ban class="w-4 h-4" />
            Стоп-слова
        </a>
    @endif

    @if(in_array(auth()->user()->role, ['admin', 'moderator']))
    <!-- 🛡️ Модерация -->
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
        Комм. к фото
        @if (($pendingPhotoComments ?? 0) > 0 && $exists)
            <span class="ml-auto text-xs bg-yellow-500/10 text-yellow-600 px-2 py-0.5 rounded-full">{{ $pendingPhotoComments }}</span>
        @endif
    </a>

    @php $route = 'admin.moderation.diary.index'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-book-open class="w-4 h-4" />
        Дневники
        @if (($pendingDiaries ?? 0) > 0 && $exists)
            <span class="ml-auto text-xs bg-yellow-500/10 text-yellow-600 px-2 py-0.5 rounded-full">{{ $pendingDiaries }}</span>
        @endif
    </a>

    @php $route = 'admin.moderation.diary.comments'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-heart class="w-4 h-4" />
        Комм. к дневникам
        @if (($pendingDiaryComments ?? 0) > 0 && $exists)
            <span class="ml-auto text-xs bg-yellow-500/10 text-yellow-600 px-2 py-0.5 rounded-full">{{ $pendingDiaryComments }}</span>
        @endif
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

    <!-- 🚨 Безопасность -->
    <p class="px-3 text-xs uppercase text-muted-foreground/60 mt-4 mb-1">Безопасность</p>
    
    @php $route = 'admin.security.fraud-alerts.index'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-siren class="w-4 h-4" />
        Антифрод
        @if (($openFraudAlerts ?? 0) > 0 && $exists)
            <span class="ml-auto text-xs bg-destructive text-white px-2 py-0.5 rounded-full animate-pulse">{{ $openFraudAlerts }}</span>
        @endif
    </a>

    @php $route = 'admin.security.block-signals.index'; $exists = Route::has($route); @endphp
    <a href="{{ $exists ? route($route) : '#' }}" {{ $exists ? 'wire:navigate' : '' }}
        class="flex items-start gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($route) ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground' }}">
        <x-lucide-user-x class="w-4 h-4" />
        Сигналы блокировки
    </a>
    @endif

   

</nav>