<?php

use App\Models\Photo;
use App\Models\Report;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Verification;
use App\Models\Swipe;
use App\Models\UserMatch;
use App\Models\Message;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB; // ФИКС 1: Добавлен DB
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin')] class extends Component 
{
    public function with(): array
    {
        $metrics = Cache::remember('admin_dashboard_metrics', 600, function () {
            return [
                'newUsersToday' => User::excludeStaff()->whereDate('created_at', today())->count(),
                'newUsersWeek'  => User::excludeStaff()->where('created_at', '>=', now()->subDays(7))->count(),
                
                'revenueToday'  => Transaction::success()->whereDate('created_at', today())->sum('amount'),
                'revenueMonth'  => Transaction::success()->where('created_at', '>=', now()->subDays(30))->sum('amount'),
                
                'moderationQueue' => Photo::pending()->count() + Verification::pending()->count() + Report::pending()->count(),
                'pendingPhotos'   => Photo::pending()->count(),
                'pendingVerifications' => Verification::pending()->count(),
                'pendingReports'  => Report::pending()->count(),

                'registrationData' => $this->getRegistrationData(30),
                'registrationCategories' => $this->getDateCategories(30),
                
                'revenueData' => $this->getRevenueData(30),
                'revenueCategories' => $this->getDateCategories(30),
                
                'activityData' => $this->getActivityData(),

                'genderStats' => $this->getGenderStats(),
                'ageStats' => $this->getAgeStats(),
                'topCities' => $this->getTopCities(),
            ];
        });

        $onlineUsers = User::excludeStaff()
            ->where('last_seen', '>=', now()->subMinutes(5))
            ->count();

        return array_merge($metrics, ['onlineUsers' => $onlineUsers]);
    }

    public function refresh(): void
    {
        Cache::forget('admin_dashboard_metrics');
        $this->dispatch('show-toast', type: 'success', message: 'Данные обновлены!');
    }

    public function clearCache(): void
    {
        try {
            \Artisan::call('cache:project-clear');
            Cache::forget('admin_dashboard_metrics');
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
            ->groupBy('date')
            ->orderBy('date') // Добавил сортировку для стабильности
            ->pluck('total', 'date')
            ->toArray();

        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $data[] = $stats[$date] ?? 0;
        }
        return $data;
    }

    private function getRevenueData(int $days): array
    {
        $stats = Transaction::success()
            ->selectRaw('DATE(created_at) as date, SUM(amount) as total')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->toArray();

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
        
        $swipes = Swipe::selectRaw('DATE(created_at) as date, count(*) as total')
            ->where('created_at', '>=', $period)->groupBy('date')->orderBy('date')->pluck('total', 'date')->toArray();

        $matches = UserMatch::selectRaw('DATE(created_at) as date, count(*) as total')
            ->where('created_at', '>=', $period)->groupBy('date')->orderBy('date')->pluck('total', 'date')->toArray();

        $messages = Message::selectRaw('DATE(created_at) as date, count(*) as total')
            ->where('created_at', '>=', $period)->groupBy('date')->orderBy('date')->pluck('total', 'date')->toArray();

        $swipesData = []; $matchesData = []; $messagesData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $swipesData[] = $swipes[$date] ?? 0;
            $matchesData[] = $matches[$date] ?? 0;
            $messagesData[] = $messages[$date] ?? 0;
        }

        return [
            'swipes' => $swipesData,
            'matches' => $matchesData,
            'messages' => $messagesData,
            'categories' => $this->getDateCategories(7)
        ];
    }

    private function getGenderStats(): array
    {
        return UserProfile::whereNotNull('gender')
            ->select('gender', DB::raw('count(*) as total'))
            ->groupBy('gender')
            ->pluck('total', 'gender')
            ->toArray();
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
                END as age_group,
                COUNT(*) as total
            ")
            ->groupBy('age_group')
            ->pluck('total', 'age_group')
            ->toArray();

        return [
            '18-24' => $stats['18-24'] ?? 0,
            '25-34' => $stats['25-34'] ?? 0,
            '35-44' => $stats['35-44'] ?? 0,
            '45-54' => $stats['45-54'] ?? 0,
            '55+' => $stats['55+'] ?? 0,
        ];
    }

    private function getTopCities(): array
    {
        return UserProfile::whereNotNull('city')
            ->select('city', DB::raw('count(*) as total'))
            ->groupBy('city')
            ->orderBy('total', 'desc')
            ->limit(5)
            ->pluck('total', 'city')
            ->toArray();
    }
}; 
?>

<div class="space-y-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <h1 class="text-2xl font-semibold">Дашборд</h1>
        <div class="flex items-center gap-3">
            <div class="text-sm text-muted-foreground">
                {{ now()->format('d.m.Y H:i') }}
            </div>
            <x-ui.button wire:click="clearCache" wire:loading.attr="disabled" variant="outline" size="sm" class="gap-2">
                <span wire:loading.remove wire:target="clearCache"><x-lucide-rotate-ccw class="w-4 h-4" /></span>
                <span wire:loading wire:target="clearCache"><x-ui.spinner class="w-4 h-4" /></span>
                <span wire:loading.remove wire:target="clearCache">Очистить кеш</span>
                <span wire:loading wire:target="clearCache">Очистка...</span>
            </x-ui.button>
            <x-ui.button wire:click="refresh" wire:loading.attr="disabled" variant="outline" size="sm" class="gap-2">
                <span wire:loading.remove wire:target="refresh"><x-lucide-refresh-ccw class="w-4 h-4" /></span>
                <span wire:loading wire:target="refresh"><x-ui.spinner class="w-4 h-4" /></span>
                <span wire:loading.remove wire:target="refresh">Обновить</span>
                <span wire:loading wire:target="refresh">Обновление...</span>
            </x-ui.button>
        </div>
    </div>

    <!-- Виджеты -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-ui.card>
            <div class="flex flex-col h-full">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">Новые юзеры</p>
                    <x-lucide-users class="w-5 h-5 text-blue-500" />
                </div>
                <p class="text-2xl font-bold mt-1">+{{ $newUsersToday }}</p>
                <div class="flex items-center gap-2 mt-auto text-xs pt-2 flex-wrap">
                    <span class="text-muted-foreground">за 24 часа</span>
                    <span class="text-blue-500 ml-auto">+{{ $newUsersWeek }}</span>
                    <span class="text-muted-foreground">за 7 дней</span>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card>
            <div class="flex flex-col h-full">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">Онлайн прямо сейчас</p>
                    <x-lucide-circle class="w-5 h-5 text-green-500 fill-green-500" />
                </div>
                <p class="text-2xl font-bold mt-1">{{ $onlineUsers }}</p>
                <p class="text-xs text-muted-foreground mt-auto pt-2">были за последние 5 минут</p>
            </div>
        </x-ui.card>

        <x-ui.card>
            <div class="flex flex-col h-full">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">Выручка</p>
                    <x-lucide-credit-card class="w-5 h-5 text-emerald-500" />
                </div>
                <p class="text-2xl font-bold mt-1">{{ number_format($revenueToday, 0, '.', ' ') }} ₽</p>
                <div class="flex items-center gap-2 mt-auto text-xs pt-2 flex-wrap">
                    <span class="text-muted-foreground">за 24 часа</span>
                    <span class="text-emerald-500 ml-auto">{{ number_format($revenueMonth, 0, '.', ' ') }} ₽</span>
                    <span class="text-muted-foreground">за 30 дней</span>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card>
            <div class="flex flex-col h-full">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">Очередь модерации</p>
                    <x-lucide-shield class="w-5 h-5 text-orange-500" />
                </div>
                <p class="text-2xl font-bold mt-1">{{ $moderationQueue }}</p>
                <div class="flex items-center gap-2 mt-auto text-xs pt-2 flex-wrap">
                    <span class="text-yellow-500">{{ $pendingPhotos }} фото</span>
                    <span class="text-blue-500">{{ $pendingVerifications }} вериф.</span>
                    <span class="text-red-500">{{ $pendingReports }} жалоб</span>
                </div>
            </div>
        </x-ui.card>
    </div>

    <!-- Графики -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Регистрации за 30 дней -->
        <x-ui.card>
            <h3 class="font-semibold text-sm mb-3">Регистрации (30 дней)</h3>
            <x-ui.chart 
                type="line" 
                :config="['Регистрации' => ['label' => 'Регистрации', 'color' => 'var(--chart-1)']]" 
                :series="[['name' => 'Регистрации', 'data' => $registrationData]]" 
                :colors="['var(--chart-1)']" 
                :options="[
                    'xaxis' => ['categories' => $registrationCategories, 'labels' => ['show' => false]],
                    'stroke' => ['width' => 2, 'curve' => 'smooth'],
                    'fill' => ['type' => 'gradient', 'gradient' => ['shadeIntensity' => 1, 'opacityFrom' => 0.4, 'opacityTo' => 0.1]],
                    'yaxis' => ['show' => true],
                    'grid' => ['show' => false],
                    'legend' => ['show' => false],
                ]"
                class="aspect-auto h-[250px]" 
            />
        </x-ui.card>

        <!-- Выручка за 30 дней -->
        <x-ui.card>
            <h3 class="font-semibold text-sm mb-3">Выручка (30 дней)</h3>
            <x-ui.chart 
                type="area" 
                :config="['Выручка' => ['label' => 'Выручка', 'color' => 'var(--chart-2)']]" 
                :series="[['name' => 'Выручка', 'data' => $revenueData]]" 
                :colors="['var(--chart-2)']" 
                :options="[
                    'xaxis' => ['categories' => $revenueCategories, 'labels' => ['show' => false]],
                    'stroke' => ['width' => 2, 'curve' => 'smooth'],
                    'fill' => ['type' => 'gradient', 'gradient' => ['shadeIntensity' => 1, 'opacityFrom' => 0.4, 'opacityTo' => 0.1]],
                    'yaxis' => ['show' => true],
                    'grid' => ['show' => false],
                    'legend' => ['show' => false],
                    'tooltip' => ['y' => ['formatter' => 'function(val) { return val + " ₽"; }']],
                ]"
                class="aspect-auto h-[250px]" 
            />
        </x-ui.card>

        <!-- Активность -->
        <x-ui.card>
            <h3 class="font-semibold text-sm mb-3">Активность (7 дней)</h3>
            <x-ui.chart 
                type="line" 
                :config="[
                    'Свайпы' => ['label' => 'Свайпы', 'color' => 'var(--chart-1)'],
                    'Мэтчи' => ['label' => 'Мэтчи', 'color' => 'var(--chart-2)'],
                    'Сообщения' => ['label' => 'Сообщения', 'color' => 'var(--chart-3)'],
                ]" 
                :series="[
                    ['name' => 'Свайпы', 'data' => $activityData['swipes']],
                    ['name' => 'Мэтчи', 'data' => $activityData['matches']],
                    ['name' => 'Сообщения', 'data' => $activityData['messages']],
                ]" 
                :colors="['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)']" 
                :options="[
                    'xaxis' => ['categories' => $activityData['categories'], 'labels' => ['show' => true]],
                    'stroke' => ['width' => 2, 'curve' => 'smooth'],
                    'yaxis' => ['show' => true],
                    'grid' => ['show' => false],
                    'legend' => ['show' => true, 'position' => 'bottom'],
                ]"
                class="aspect-auto h-[250px]" 
            />
        </x-ui.card>
    </div>

    <!-- Аналитика 2-го эшелона -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Пол -->
        <x-ui.card>
            <h3 class="font-semibold text-sm mb-3">Соотношение по полу</h3>
            @php
                $total = array_sum($genderStats);
                $malePercent = $total > 0 ? round((($genderStats['male'] ?? 0) / $total) * 100) : 0;
                $femalePercent = $total > 0 ? round((($genderStats['female'] ?? 0) / $total) * 100) : 0;
            @endphp
            <div class="flex items-center gap-4">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                        <x-lucide-mars class="w-4 h-4 text-blue-500" />
                        <span class="text-sm">Мужчины</span>
                        <span class="text-sm font-medium ml-auto">{{ $malePercent }}%</span>
                    </div>
                    <div class="w-full bg-blue-500/20 rounded-full h-2">
                        <div class="bg-blue-500 rounded-full h-2" style="width: {{ $malePercent }}%"></div>
                    </div>
                    <div class="flex items-center gap-2 mt-2 mb-1">
                        <x-lucide-venus class="w-4 h-4 text-pink-500" />
                        <span class="text-sm">Женщины</span>
                        <span class="text-sm font-medium ml-auto">{{ $femalePercent }}%</span>
                    </div>
                    <div class="w-full bg-pink-500/20 rounded-full h-2">
                        <div class="bg-pink-500 rounded-full h-2" style="width: {{ $femalePercent }}%"></div>
                    </div>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold">{{ $total }}</p>
                    <p class="text-xs text-muted-foreground">всего</p>
                </div>
            </div>
        </x-ui.card>

        <!-- Возраст -->
        <x-ui.card>
            <h3 class="font-semibold text-sm mb-3">Возрастное распределение</h3>
            @php
                $ageLabels = array_keys($ageStats);
                $ageData = array_values($ageStats);
                $ageColors = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)'];
            @endphp
            <x-ui.chart type="bar" 
                :config="[
                    '18-24' => ['label' => '18-24', 'color' => 'var(--chart-1)'],
                    '25-34' => ['label' => '25-34', 'color' => 'var(--chart-2)'],
                    '35-44' => ['label' => '35-44', 'color' => 'var(--chart-3)'],
                    '45-54' => ['label' => '45-54', 'color' => 'var(--chart-4)'],
                    '55+'   => ['label' => '55+', 'color' => 'var(--chart-5)'],
                ]" 
                :series="[['name' => 'Возраст', 'data' => $ageData]]" 
                :colors="$ageColors" 
                :options="[
                    'xaxis' => ['categories' => $ageLabels, 'labels' => ['show' => true], 'axisBorder' => ['show' => false], 'axisTicks' => ['show' => false]],
                    'plotOptions' => ['bar' => ['borderRadius' => 5, 'columnWidth' => '50%', 'distributed' => true]],
                    'yaxis' => ['show' => true],
                    'grid' => ['show' => false],
                    'legend' => ['show' => false],
                ]"
                class="aspect-auto h-[250px]" 
            />
        </x-ui.card>

        <!-- Города -->
        <x-ui.card>
            <h3 class="font-semibold text-sm mb-3">Топ городов</h3>
            @php
                $cityLabels = array_keys($topCities);
                $cityData = array_values($topCities);
                $cityColors = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)'];
                $cityConfig = [];
                foreach ($cityLabels as $index => $label) {
                    $cityConfig[$label] = ['label' => $label, 'color' => $cityColors[$index]];
                }
            @endphp
            <x-ui.chart type="bar" 
                :config="$cityConfig" 
                :series="[['name' => 'Пользователи', 'data' => $cityData]]" 
                :colors="$cityColors" 
                :options="[
                    'xaxis' => ['categories' => $cityLabels, 'labels' => ['show' => false], 'axisBorder' => ['show' => false], 'axisTicks' => ['show' => false]],
                    'plotOptions' => ['bar' => ['horizontal' => true, 'borderRadius' => 5, 'distributed' => true]],
                    'yaxis' => ['show' => true],
                    'grid' => ['show' => false],
                    'legend' => ['show' => false],
                ]"
                class="aspect-auto h-[250px]" 
            />
        </x-ui.card>
    </div>
</div>