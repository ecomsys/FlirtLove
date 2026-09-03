<?php

use App\Models\AdminLog;
use App\Models\Chat;
use App\Models\Photo;
use App\Models\Report;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\PhotoComment;
use App\Models\DiaryComment;
use App\Models\Diary;
use App\Models\Swipe;
use App\Models\UserMatch;
use App\Models\Message;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin')] class extends Component 
{
    public function with(): array
    {
        $role = auth()->user()->role;
        $data = [];

        // 1. БАЗОВЫЕ ЖИВЫЕ ДАННЫЕ (Для всех ролей) - Кэшируем на 1 минуту для скорости!
        $liveData = Cache::remember('admin_dashboard_live_v2', 60, function () {
            return [
                'onlineUsers' => User::excludeStaff()->where('last_seen', '>=', now()->subMinutes(5))->count(),
                'newUsersToday' => User::excludeStaff()->whereDate('created_at', today())->count(),
                'newUsersWeek' => User::excludeStaff()->where('created_at', '>=', now()->subDays(7))->count(),
                'newUsersMonth' => User::excludeStaff()->where('created_at', '>=', now()->subDays(30))->count(),
                
                'pendingPhotos' => Photo::where('status', 'pending')->count(),
                'pendingPhotoComments' => PhotoComment::where('status', 'pending')->count(),
                'pendingDiaries' => Diary::where('status', 'pending')->count(),
                'pendingDiaryComments' => DiaryComment::where('status', 'pending')->count(),
                'pendingReports' => Report::where('status', 'pending')->count(),
                
                'unreadTickets' => Chat::where('type', 'support')
                    ->whereHas('participants', fn($q) => $q->where('unread_count', '>', 0))
                    ->count(),
            ];
        });
        
        $data = array_merge($data, $liveData);
        $data['moderationQueue'] = $data['pendingPhotos'] + $data['pendingPhotoComments'] + $data['pendingDiaries'] + $data['pendingDiaryComments'] + $data['pendingReports'];

        // Ленты (Последние юзеры)
        $data['recentUsers'] = User::excludeStaff()->with('photos')->select('id', 'name', 'created_at', 'last_seen')->latest()->limit(8)->get();

        // Лента аудита (Для Админа и Саппорта)
        if (in_array($role, ['admin', 'support'])) {
            $data['recentLogs'] = AdminLog::with('admin:id,name,last_seen')->latest()->limit(8)->get();
        }

        // 2. ДАННЫЕ ДЛЯ ГРАФИКОВ (Для Админа и Модератора)
        if (in_array($role, ['admin', 'moderator'])) {
            $data['activityData'] = $this->getActivityData();
        }

        // 3. ТЯЖЕЛЫЕ МЕТРИКИ И ДЕМОГРАФИЯ (ТОЛЬКО ДЛЯ АДМИНА)
        if ($role === 'admin') {
            $metrics = Cache::remember('admin_dashboard_metrics_v8', 600, function () {
                $revenueData = $this->getRevenueData(30);
                $registrationData = $this->getRegistrationData(30);
                
                return [
                    'revenueToday'  => Transaction::where('status', 'success')->whereDate('created_at', today())->sum('amount'),
                    'revenueMonth'  => Transaction::where('status', 'success')->where('created_at', '>=', now()->subDays(30))->sum('amount'),
                    
                    'revenue30DaysTotal' => array_sum($revenueData),
                    'registrations30DaysTotal' => array_sum($registrationData),
                    
                    'registrationData' => $registrationData,
                    'registrationCategories' => $this->getDateCategories(30),
                    'revenueData' => $revenueData,
                    'revenueCategories' => $this->getDateCategories(30),
                    'genderStats' => $this->getGenderStats(),
                    'ageStats' => $this->getAgeStats(),
                    'topCities' => $this->getTopCities(),
                ];
            });
            $data = array_merge($data, $metrics);
        }

        return $data;
    }

     // Добавили свойство для принудительной перерисовки графиков
    public string $chartKey = 'init';

        public function refresh(): void
    {
        if (auth()->user()->role === 'admin') {
            Cache::forget('admin_dashboard_metrics_v8');
        }
        
        // Сбрасываем минутный кэш живых данных при ручном обновлении
        Cache::forget('admin_dashboard_live_v2');
        
        $this->chartKey = uniqid(); 
        $this->dispatch('show-toast', type: 'success', message: 'Данные обновлены!');
    }

    public function clearCache(): void
    {
        try {
            \Artisan::call('cache:clear');
            Cache::forget('admin_dashboard_metrics_v8');
            Cache::forget('admin_dashboard_live_v2');
            $this->chartKey = uniqid(); 
            $this->dispatch('show-toast', type: 'success', message: 'Кеш успешно очищен!');
        } catch (\Exception $e) {
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка: ' . $e->getMessage());
        }
    }

    private function getDateCategories(int $days): array
    {
        $categories = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $categories[] = now()->subDays($i)->format('d.m');
        }
        return $categories;
    }

    private function getRegistrationData(int $days): array
    {
        $stats = User::excludeStaff()
            ->selectRaw('DATE(created_at) as date, count(*) as total')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')->orderBy('date')->pluck('total', 'date')->toArray();

        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $data[] = $stats[$date] ?? 0;
        }
        return $data;
    }

    private function getRevenueData(int $days): array
    {
        $stats = Transaction::where('status', 'success')
            ->selectRaw('DATE(created_at) as date, SUM(amount) as total')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')->orderBy('date')->pluck('total', 'date')->toArray();

        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $data[] = round($stats[$date] ?? 0, 2);
        }
        return $data;
    }

    private function getActivityData(): array
    {
        $period = now()->subDays(7);
        $swipes = Swipe::selectRaw('DATE(created_at) as date, count(*) as total')->where('created_at', '>=', $period)->groupBy('date')->orderBy('date')->pluck('total', 'date')->toArray();
        $matches = UserMatch::selectRaw('DATE(created_at) as date, count(*) as total')->where('created_at', '>=', $period)->groupBy('date')->orderBy('date')->pluck('total', 'date')->toArray();
        $messages = Message::selectRaw('DATE(created_at) as date, count(*) as total')->where('created_at', '>=', $period)->groupBy('date')->orderBy('date')->pluck('total', 'date')->toArray();

        $swipesData = []; $matchesData = []; $messagesData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $swipesData[] = $swipes[$date] ?? 0;
            $matchesData[] = $matches[$date] ?? 0;
            $messagesData[] = $messages[$date] ?? 0;
        }

        return [
            'swipes' => $swipesData, 'matches' => $matchesData, 'messages' => $messagesData,
            'categories' => $this->getDateCategories(7)
        ];
    }

    private function getGenderStats(): array
    {
        return UserProfile::whereNotNull('gender')->select('gender', DB::raw('count(*) as total'))->groupBy('gender')->pluck('total', 'gender')->toArray();
    }

    private function getAgeStats(): array
    {
        $stats = UserProfile::whereNotNull('birth_date')
            ->selectRaw("
                CASE 
                    WHEN extract(year from age(birth_date)) BETWEEN 18 AND 24 THEN '18-24'
                    WHEN extract(year from age(birth_date)) BETWEEN 25 AND 34 THEN '25-34'
                    WHEN extract(year from age(birth_date)) BETWEEN 35 AND 44 THEN '35-44'
                    WHEN extract(year from age(birth_date)) BETWEEN 45 AND 54 THEN '45-54'
                    WHEN extract(year from age(birth_date)) >= 55 THEN '55+'
                END as age_group, COUNT(*) as total
            ")->groupBy('age_group')->pluck('total', 'age_group')->toArray();

        return [
            '18-24' => $stats['18-24'] ?? 0, '25-34' => $stats['25-34'] ?? 0,
            '35-44' => $stats['35-44'] ?? 0, '45-54' => $stats['45-54'] ?? 0, '55+' => $stats['55+'] ?? 0,
        ];
    }

    private function getTopCities(): array
    {
        return UserProfile::whereNotNull('city')->select('city', DB::raw('count(*) as total'))->groupBy('city')->orderBy('total', 'desc')->limit(5)->pluck('total', 'city')->toArray();
    }
}; 
?>

<div class="space-y-6">
    @php $role = auth()->user()->role; @endphp

    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Дашборд</h1>
            <p class="text-sm text-muted-foreground">Сводка данных проекта в реальном времени</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="text-sm text-muted-foreground hidden sm:block">{{ now()->format('d.m.Y H:i') }}</div>
            
            @if($role === 'admin')
                <x-ui.button wire:click="clearCache" wire:loading.attr="disabled" variant="outline" size="sm" class="gap-2">
                    <span wire:loading.remove wire:target="clearCache"><x-lucide-trash-2 class="w-4 h-4" /></span>
                    <span wire:loading wire:target="clearCache"><x-ui.spinner class="w-4 h-4" /></span>
                    <span class="hidden sm:inline">Очистить кеш</span>
                </x-ui.button>
            @endif
            
            <x-ui.button wire:click="refresh" wire:loading.attr="disabled" variant="default" size="sm" class="gap-2">
                <span wire:loading.remove wire:target="refresh"><x-lucide-refresh-ccw class="w-4 h-4" /></span>
                <span wire:loading wire:target="refresh"><x-ui.spinner class="w-4 h-4" /></span>
                <span class="hidden sm:inline">Обновить</span>
            </x-ui.button>
        </div>
    </div>

    <!-- ВИДЖЕТЫ (KPI) -->        
        @if($role === 'admin')
            <!-- 1. Новые юзеры -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

            <x-ui.card wire:key="kpi-admin-1" class="p-4">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-muted-foreground font-medium">Новые юзеры (24ч)</p>
                        <p class="text-3xl font-bold mt-1 text-blue-500">+{{ $newUsersToday }}</p>
                    </div>
                    <div class="p-2 rounded-lg bg-blue-500/10"><x-lucide-users class="w-5 h-5 text-blue-500" /></div>
                </div>
                <div class="flex items-center justify-between gap-2 mt-3 text-xs">
                    <span class="text-muted-foreground">Неделя: <span class="text-foreground font-medium">{{ $newUsersWeek }}</span></span>
                    <span class="text-muted-foreground">Месяц: <span class="text-foreground font-medium">{{ $newUsersMonth }}</span></span>
                </div>
            </x-ui.card>

            <!-- 2. Модерация Фото -->
            <x-ui.card wire:key="kpi-admin-2" class="p-4">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-muted-foreground font-medium">Модерация: Фото</p>
                        <p class="text-3xl font-bold mt-1 text-yellow-500">{{ $pendingPhotos + $pendingPhotoComments }}</p>
                    </div>
                    <div class="p-2 rounded-lg bg-yellow-500/10"><x-lucide-image class="w-5 h-5 text-yellow-500" /></div>
                </div>
                <div class="flex items-center gap-2 mt-3 text-xs">
                    <span class="text-muted-foreground">{{ $pendingPhotos }} фото / {{ $pendingPhotoComments }} комм.</span>
                </div>
            </x-ui.card>

            <!-- 3. Модерация Дневников -->
            <x-ui.card wire:key="kpi-admin-3" class="p-4">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-muted-foreground font-medium">Модерация: Дневники</p>
                        <p class="text-3xl font-bold mt-1 text-indigo-500">{{ $pendingDiaries + $pendingDiaryComments }}</p>
                    </div>
                    <div class="p-2 rounded-lg bg-indigo-500/10"><x-lucide-book-open class="w-5 h-5 text-indigo-500" /></div>
                </div>
                <div class="flex items-center gap-2 mt-3 text-xs">
                    <span class="text-muted-foreground">{{ $pendingDiaries }} постов / {{ $pendingDiaryComments }} комм.</span>
                </div>
            </x-ui.card>

            <!-- 4. Жалобы -->
            <x-ui.card wire:key="kpi-admin-4" class="p-4">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-muted-foreground font-medium">Жалобы (Ожидают)</p>
                        <p class="text-3xl font-bold mt-1 text-red-500">{{ $pendingReports }}</p>
                    </div>
                    <div class="p-2 rounded-lg bg-red-500/10"><x-lucide-flag class="w-5 h-5 text-red-500" /></div>
                </div>
                <div class="flex items-center gap-2 mt-3 text-xs">
                    <span class="text-muted-foreground">Ожидают обработки</span>
                </div>
            </x-ui.card>

            <!-- 5. Тикеты поддержки -->
            <x-ui.card wire:key="kpi-admin-5" class="p-4">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-muted-foreground font-medium">Тикеты поддержки</p>
                        <p class="text-3xl font-bold mt-1 text-purple-500">{{ $unreadTickets }}</p>
                    </div>
                    <div class="p-2 rounded-lg bg-purple-500/10"><x-lucide-life-buoy class="w-5 h-5 text-purple-500" /></div>
                </div>
                <div class="flex items-center gap-2 mt-3 text-xs">
                    <span class="text-muted-foreground">Непрочитанных сообщений</span>
                </div>
            </x-ui.card>

            <!-- 6. Онлайн сейчас -->
            <x-ui.card wire:key="kpi-admin-6" class="p-4">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-muted-foreground font-medium">Онлайн сейчас</p>
                        <p class="text-3xl font-bold mt-1 text-green-500">{{ $onlineUsers }}</p>
                    </div>
                    <div class="p-2 rounded-lg bg-green-500/10">
                        <div class="w-5 h-5 relative flex items-center justify-center">
                            <span class="animate-ping absolute inline-flex h-3 w-3 rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between gap-2 mt-3 text-xs">
                    <span class="text-muted-foreground">Неделя: <span class="text-foreground font-medium">+{{ $newUsersWeek }}</span></span>
                    <span class="text-muted-foreground">Месяц: <span class="text-foreground font-medium">+{{ $newUsersMonth }}</span></span>
                </div>
            </x-ui.card>
</div>
        @elseif($role === 'moderator')
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- 1. Фото и комменты -->
            <x-ui.card wire:key="kpi-mod-1" class="p-4">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-muted-foreground font-medium">Фото на проверке</p>
                        <p class="text-3xl font-bold mt-1 text-yellow-500">{{ $pendingPhotos + $pendingPhotoComments }}</p>
                    </div>
                    <div class="p-2 rounded-lg bg-yellow-500/10"><x-lucide-image class="w-5 h-5 text-yellow-500" /></div>
                </div>
                <div class="flex items-center gap-2 mt-3 text-xs">
                    <span class="text-muted-foreground">{{ $pendingPhotos }} фото / {{ $pendingPhotoComments }} комм.</span>
                </div>
            </x-ui.card>

            <!-- 2. Дневники и комменты -->
            <x-ui.card wire:key="kpi-mod-2" class="p-4">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-muted-foreground font-medium">Дневники на проверке</p>
                        <p class="text-3xl font-bold mt-1 text-blue-500">{{ $pendingDiaries + $pendingDiaryComments }}</p>
                    </div>
                    <div class="p-2 rounded-lg bg-blue-500/10"><x-lucide-book-open class="w-5 h-5 text-blue-500" /></div>
                </div>
                <div class="flex items-center gap-2 mt-3 text-xs">
                    <span class="text-muted-foreground">{{ $pendingDiaries }} постов / {{ $pendingDiaryComments }} комм.</span>
                </div>
            </x-ui.card>

            <!-- 3. Жалобы -->
            <x-ui.card wire:key="kpi-mod-3" class="p-4">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-muted-foreground font-medium">Жалобы (Ожидают)</p>
                        <p class="text-3xl font-bold mt-1 text-red-500">{{ $pendingReports }}</p>
                    </div>
                    <div class="p-2 rounded-lg bg-red-500/10"><x-lucide-flag class="w-5 h-5 text-red-500" /></div>
                </div>
                <div class="flex items-center gap-2 mt-3 text-xs">
                    <span class="text-muted-foreground">Ожидают обработки</span>
                </div>
            </x-ui.card>

            <!-- 4. Онлайн -->
            <x-ui.card wire:key="kpi-mod-4" class="p-4">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-muted-foreground font-medium">Онлайн сейчас</p>
                        <p class="text-3xl font-bold mt-1">{{ $onlineUsers }}</p>
                    </div>
                    <div class="p-2 rounded-lg bg-green-500/10"><x-lucide-users class="w-5 h-5 text-green-500" /></div>
                </div>
                <div class="flex items-center gap-2 mt-3 text-xs">
                    <span class="text-muted-foreground">+{{ $newUsersToday }} новых за 24ч</span>
                </div>
            </x-ui.card>
            </div>
        @elseif($role === 'support')
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- 1. Тикеты -->
            <x-ui.card wire:key="kpi-sup-1" class="p-4">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-muted-foreground font-medium">Непрочитанные тикеты</p>
                        <p class="text-3xl font-bold mt-1 text-red-500">{{ $unreadTickets }}</p>
                    </div>
                    <div class="p-2 rounded-lg bg-red-500/10"><x-lucide-life-buoy class="w-5 h-5 text-red-500" /></div>
                </div>
                <div class="flex items-center gap-2 mt-3 text-xs">
                    <span class="text-muted-foreground">Требуют ответа</span>
                </div>
            </x-ui.card>

            <!-- 2. Жалобы -->
            <x-ui.card wire:key="kpi-sup-2" class="p-4">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-muted-foreground font-medium">Жалобы (Ожидают)</p>
                        <p class="text-3xl font-bold mt-1 text-orange-500">{{ $pendingReports }}</p>
                    </div>
                    <div class="p-2 rounded-lg bg-orange-500/10"><x-lucide-flag class="w-5 h-5 text-orange-500" /></div>
                </div>
                <div class="flex items-center gap-2 mt-3 text-xs">
                    <span class="text-muted-foreground">Ожидают обработки</span>
                </div>
            </x-ui.card>

            <!-- 3. Новые юзеры -->
            <x-ui.card wire:key="kpi-sup-3" class="p-4">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-muted-foreground font-medium">Новые юзеры (24ч)</p>
                        <p class="text-3xl font-bold mt-1 text-blue-500">{{ $newUsersToday }}</p>
                    </div>
                    <div class="p-2 rounded-lg bg-blue-500/10"><x-lucide-user-plus class="w-5 h-5 text-blue-500" /></div>
                </div>
                <div class="flex items-center gap-2 mt-3 text-xs">
                    <span class="text-muted-foreground">За неделю: {{ $newUsersWeek }}</span>
                </div>
            </x-ui.card>

            <!-- 4. Онлайн -->
            <x-ui.card wire:key="kpi-sup-4" class="p-4">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-muted-foreground font-medium">Онлайн сейчас</p>
                        <p class="text-3xl font-bold mt-1 text-green-500">{{ $onlineUsers }}</p>
                    </div>
                    <div class="p-2 rounded-lg bg-green-500/10">
                        <div class="w-5 h-5 relative flex items-center justify-center">
                            <span class="animate-ping absolute inline-flex h-3 w-3 rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2 mt-3 text-xs">
                    <span class="text-muted-foreground">За неделю: +{{ $newUsersWeek }}</span>
                </div>
            </x-ui.card>
            </div>
        @endif

    

    @if($role === 'admin')
        <!-- ГЛАВНЫЕ МЕТРИКИ (Выручка и Регистрации) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- Выручка за 30 дней -->
            <x-ui.card wire:key="chart-revenue" variant="sectioned">
                <x-ui.card-header>
                    <div class="flex items-center justify-between w-full">
                        <x-ui.card-title>Выручка (30 дней)</x-ui.card-title>
                        <x-ui.badge variant="success">{{ number_format($revenue30DaysTotal, 0, '.', ' ') }} ₽</x-ui.badge>
                    </div>
                    <x-ui.card-description>Сумма успешных транзакций по дням</x-ui.card-description>
                </x-ui.card-header>
                <x-ui.card-content>
                    <x-ui.chart 
                        wire:key="chart-revenue-{{ $chartKey }}"
                        type="area" 
                        :config="['Выручка' => ['label' => 'Выручка', 'color' => 'var(--chart-2)']]" 
                        :series="[['name' => 'Выручка', 'data' => $revenueData]]" 
                        :colors="['var(--chart-2)']" 
                        :options="[
                            'xaxis' => ['categories' => $revenueCategories, 'labels' => ['show' => false]],
                            'fill' => ['type' => 'gradient', 'gradient' => ['shadeIntensity' => 1, 'opacityFrom' => 0.4, 'opacityTo' => 0.1, 'stops' => [0, 100]]],
                            'stroke' => ['width' => 2, 'curve' => 'smooth'],
                            'yaxis' => ['show' => true],
                            'grid' => ['show' => true, 'borderColor' => 'var(--border)', 'strokeDashArray' => 2],
                            'legend' => ['show' => false], 
                            'tooltip' => ['theme' => 'dark', 'x' => ['show' => true]]
                        ]" 
                        class="aspect-auto h-[250px]" 
                    />
                </x-ui.card-content>
            </x-ui.card>

            <!-- Регистрации за 30 дней -->
            <x-ui.card wire:key="chart-registrations" variant="sectioned">
                <x-ui.card-header>
                    <div class="flex items-center justify-between w-full">
                        <x-ui.card-title>Регистрации (30 дней)</x-ui.card-title>
                        <x-ui.badge variant="default">{{ $registrations30DaysTotal }} шт.</x-ui.badge>
                    </div>
                    <x-ui.card-description>Динамика притока новых пользователей</x-ui.card-description>
                </x-ui.card-header>
                <x-ui.card-content>
                    <x-ui.chart 
                        wire:key="chart-registrations-{{ $chartKey }}" 
                        type="area" 
                        :config="['Регистрации' => ['label' => 'Регистрации', 'color' => 'var(--chart-1)']]" 
                        :series="[['name' => 'Регистрации', 'data' => $registrationData]]" 
                        :colors="['var(--chart-1)']" 
                        :options="[
                            'xaxis' => ['categories' => $registrationCategories, 'labels' => ['show' => false]],
                            'fill' => ['type' => 'gradient', 'gradient' => ['shadeIntensity' => 1, 'opacityFrom' => 0.4, 'opacityTo' => 0.1, 'stops' => [0, 100]]],
                            'stroke' => ['width' => 2, 'curve' => 'smooth'],
                            'yaxis' => ['show' => true],
                            'grid' => ['show' => true, 'borderColor' => 'var(--border)', 'strokeDashArray' => 2],
                            'legend' => ['show' => false], 
                            'tooltip' => ['theme' => 'dark', 'x' => ['show' => true]]
                        ]" 
                        class="aspect-auto h-[250px]" 
                    />
                </x-ui.card-content>
            </x-ui.card>
        </div>
    @endif

    @if(in_array($role, ['admin', 'moderator']))
        <!-- АКТИВНОСТЬ ПЛАТФОРМЫ (Полная ширина) -->
        <x-ui.card wire:key="chart-activity" variant="sectioned">
            <x-ui.card-header>
                <x-ui.card-title>Активность платформы (7 дней)</x-ui.card-title>
                <x-ui.card-description>Свайпы, мэтчи и отправленные сообщения</x-ui.card-description>
            </x-ui.card-header>
            <x-ui.card-content>
                <x-ui.chart 
                    wire:key="chart-activity-{{ $chartKey }}"
                    type="line" 
                    :config="[
                        'Свайпы' => ['label' => 'Свайпы', 'color' => 'var(--chart-1)'], 
                        'Мэтчи' => ['label' => 'Мэтчи', 'color' => 'var(--chart-2)'], 
                        'Сообщения' => ['label' => 'Сообщения', 'color' => 'var(--chart-3)']
                    ]" 
                    :series="[
                        ['name' => 'Свайпы', 'data' => $activityData['swipes']], 
                        ['name' => 'Мэтчи', 'data' => $activityData['matches']], 
                        ['name' => 'Сообщения', 'data' => $activityData['messages']]
                    ]" 
                    :colors="['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)']" 
                    :options="[
                        'xaxis' => ['categories' => $activityData['categories'], 'labels' => ['show' => true]],
                        'stroke' => ['width' => 2, 'curve' => 'smooth'],
                        'yaxis' => ['show' => true],
                        'grid' => ['show' => true, 'borderColor' => 'var(--border)', 'strokeDashArray' => 2],
                        'legend' => ['show' => true, 'position' => 'top', 'horizontalAlign' => 'right'], 
                        'tooltip' => ['theme' => 'dark', 'x' => ['show' => true]]
                    ]" 
                    class="aspect-auto h-[250px]" 
                />
            </x-ui.card-content>
        </x-ui.card>
    @endif

    @if($role === 'admin')
        <!-- ДЕМОГРАФИЯ -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <x-ui.card wire:key="demo-gender" class="p-4">
                <h3 class="font-semibold text-sm mb-4">Соотношение по полу</h3>
                @php
                    $total = array_sum($genderStats);
                    $malePercent = $total > 0 ? round((($genderStats['male'] ?? 0) / $total) * 100) : 0;
                    $femalePercent = $total > 0 ? round((($genderStats['female'] ?? 0) / $total) * 100) : 0;
                @endphp
                <div class="space-y-4">
                    <div>
                        <div class="flex items-center gap-2 mb-1.5">
                            <x-lucide-mars class="w-4 h-4 text-blue-500" /><span class="text-sm font-medium">Мужчины</span>
                            <span class="text-sm text-muted-foreground ml-auto">{{ $malePercent }}%</span>
                        </div>
                        <div class="w-full bg-muted rounded-full h-2.5 overflow-hidden">
                            <div class="bg-blue-500 h-2.5 rounded-full transition-all duration-500" style="width: {{ $malePercent }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 mb-1.5">
                            <x-lucide-venus class="w-4 h-4 text-pink-500" /><span class="text-sm font-medium">Женщины</span>
                            <span class="text-sm text-muted-foreground ml-auto">{{ $femalePercent }}%</span>
                        </div>
                        <div class="w-full bg-muted rounded-full h-2.5 overflow-hidden">
                            <div class="bg-pink-500 h-2.5 rounded-full transition-all duration-500" style="width: {{ $femalePercent }}%"></div>
                        </div>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card wire:key="demo-age" class="p-4">
                <h3 class="font-semibold text-sm mb-4">Возрастное распределение</h3>
                @php
                    $ageLabels = array_keys($ageStats); $ageData = array_values($ageStats);
                    $ageColors = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)'];
                @endphp
                <x-ui.chart type="bar" 
                    wire:key="demo-age-{{ $chartKey }}"
                    :config="['18-24' => ['label' => '18-24', 'color' => 'var(--chart-1)'], '25-34' => ['label' => '25-34', 'color' => 'var(--chart-2)'], '35-44' => ['label' => '35-44', 'color' => 'var(--chart-3)'], '45-54' => ['label' => '45-54', 'color' => 'var(--chart-4)'], '55+' => ['label' => '55+', 'color' => 'var(--chart-5)']]" 
                    :series="[['name' => 'Возраст', 'data' => $ageData]]" :colors="$ageColors" 
                    :options="[
                        'xaxis' => ['categories' => $ageLabels, 'labels' => ['show' => true], 'axisBorder' => ['show' => false], 'axisTicks' => ['show' => false]],
                        'plotOptions' => ['bar' => ['borderRadius' => 5, 'columnWidth' => '60%', 'distributed' => true]],
                        'yaxis' => ['show' => false], 'grid' => ['show' => false], 'legend' => ['show' => false], 'tooltip' => ['theme' => 'dark']
                    ]" 
                    class="aspect-auto h-[250px]" 
                />
            </x-ui.card>

            <x-ui.card wire:key="demo-cities" class="p-4">
                <h3 class="font-semibold text-sm mb-4">Топ городов</h3>
                @php
                    $cityLabels = array_keys($topCities); $cityData = array_values($topCities);
                    $cityColors = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)']; $cityConfig = [];
                    foreach ($cityLabels as $index => $label) { $cityConfig[$label] = ['label' => $label, 'color' => $cityColors[$index]]; }
                @endphp
                <x-ui.chart type="bar" 
                    wire:key="demo-cities-{{ $chartKey }}"
                    :config="$cityConfig" :series="[['name' => 'Пользователи', 'data' => $cityData]]" :colors="$cityColors" 
                    :options="[
                        'xaxis' => ['categories' => $cityLabels, 'labels' => ['show' => false], 'axisBorder' => ['show' => false], 'axisTicks' => ['show' => false]],
                        'plotOptions' => ['bar' => ['horizontal' => true, 'borderRadius' => 5, 'distributed' => true, 'barHeight' => '70%']],
                        'yaxis' => ['show' => true, 'labels' => ['style' => ['fontSize' => '11px']]], 'grid' => ['show' => false], 'legend' => ['show' => false], 'tooltip' => ['theme' => 'dark']
                    ]" 
                    class="aspect-auto h-[250px]" 
                />
            </x-ui.card>
        </div>
    @endif

    <!-- НИЖНИЙ РЯД: ЛЕНТЫ -->
    <div @class([
        'grid grid-cols-1 gap-6',
        'lg:grid-cols-2' => in_array($role, ['admin', 'support'])
    ])>
        
        @if(in_array($role, ['admin', 'support']))
            <!-- Лента аудита -->
            <x-ui.card wire:key="feed-audit" class="p-4 h-full">
                <h3 class="font-semibold text-sm mb-4 flex items-center gap-2"><x-lucide-history class="w-4 h-4" /> Лента аудита</h3>
                <div class="space-y-2 max-h-[360px] overflow-y-auto little-scroll pr-2">
                    @forelse($recentLogs ?? [] as $logItem)
                        <div wire:key="log-item-{{ $logItem->id }}" class="flex items-start gap-3">
                            <div class="shrink-0">
                                @if($logItem->admin)
                                    <x-avatar src="{{ $logItem->admin->avatar_url }}" name="{{ $logItem->admin->name }}" userId="{{ $logItem->admin->id }}" showStatus="true" :isOnline="$logItem->admin->is_online" />
                                @else
                                    <div class="w-8 h-8 rounded-full bg-muted flex items-center justify-center">
                                        <x-lucide-cpu class="w-4 h-4 text-muted-foreground" />
                                    </div>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0 flex flex-col">
                                <p class="text-xs text-muted-foreground">
                                    <span class="font-medium text-foreground">{{ $logItem->admin?->name ?? 'Система' }}</span>
                                    выполнил(а)                                    
                                </p>
                                <a href="{{ route('admin.system.admin-logs', ['q' => $logItem->id]) }}" wire:navigate class="text-[10px] font-mono text-blue-500 hover:underline">
                                    {{ $logItem->action }}
                                </a>
                                <p class="text-[10px] text-muted-foreground/70">{{ $logItem->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-muted-foreground text-center py-4">Действий пока нет</p>
                    @endforelse
                </div>
                <a href="{{ route('admin.system.admin-logs') }}" wire:navigate class="block text-center text-xs text-primary hover:underline mt-4 pt-3 border-t border-border">Весь журнал</a>
            </x-ui.card>
        @endif

        <!-- Новые юзеры -->
        <x-ui.card wire:key="feed-users" class="p-4 h-full">
            <h3 class="font-semibold text-sm mb-4 flex items-center gap-2"><x-lucide-user-plus class="w-4 h-4" /> Новые регистрации</h3>
            <div class="space-y-3 max-h-[360px] overflow-y-auto little-scroll pr-2">
                @forelse($recentUsers as $user)
                    <a href="{{ route('admin.users.show', $user->id) }}" wire:key="user-{{ $user->id }}" wire:navigate class="flex items-center gap-3 group">
                        <x-avatar src="{{ $user->avatar_url }}" name="{{ $user->name }}" userId="{{ $user->id }}" showStatus="true" :isOnline="$user->is_online" />
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium group-hover:text-primary truncate">{{ $user->name }}</p>
                            <p class="text-[10px] text-muted-foreground">{{ $user->created_at->diffForHumans() }}</p>
                        </div>
                        <x-lucide-chevron-right class="w-4 h-4 text-muted-foreground group-hover:text-primary" />
                    </a>
                @empty
                    <p class="text-xs text-muted-foreground text-center py-4">Нет новых юзеров</p>
                @endforelse
            </div>
            <a href="{{ route('admin.users.index') }}" wire:navigate class="block text-center text-xs text-primary hover:underline mt-4 pt-3 border-t border-border">Все пользователи</a>
        </x-ui.card>
    </div>
</div>