<?php

use App\Models\User;
use App\Models\Photo;
use App\Models\Report;
use App\Models\Broadcast;
use App\Models\PhotoComment;

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache; 
use Illuminate\Support\Facades\Artisan;

new #[Layout('layouts.admin')] class extends Component 
{

    public function with(): array
    {
        // Правильно получаем метрики из кеша
        $metrics = Cache::remember('admin_dashboard_metrics', 600, function () {
            return [
                // ✅ Исключаем админов из статистики юзеров
                'totalUsers' => User::excludeAdmins()->count(),
                'newUsersToday' => User::excludeAdmins()->whereDate('created_at', today())->count(),
                'newUsersWeek' => User::excludeAdmins()->where('created_at', '>=', now()->subDays(7))->count(),
                
                // ✅ Исключаем фото, загруженные админами
                'photosTotal' => Photo::excludeAdmins()->count(),
                'photosPending' => Photo::excludeAdmins()->where('status', 'pending')->count(),
                'photosApproved' => Photo::excludeAdmins()->where('status', 'approved')->count(),
                
                // ✅ Исключаем жалобы, где замешаны админы
                'reportsPending' => Report::excludeAdmins()->where('status', 'pending')->count(),
                'reportsTotal' => Report::excludeAdmins()->count(),
                
                // Оповещения не трогаем (это сами рассылки, а не юзеры)
                'notificationsTotal' => Broadcast::count(),
                'notificationsScheduled' => Broadcast::where('status', 'scheduled')->count(),
                
                'genderStats' => $this->getGenderStats(),
                'ageStats' => $this->getAgeStats(),
                'topCities' => $this->getTopCities(),
                'registrationData' => $this->getRegistrationData(),
                'categories' => $this->getCategories(),
                
                // ✅ Исключаем комментарии админов
                'commentsTotal' => PhotoComment::excludeAdmins()->count(),
                'commentsPending' => PhotoComment::excludeAdmins()->where('status', 'pending')->count(),
                'commentsApproved' => PhotoComment::excludeAdmins()->where('status', 'approved')->count(),
                'commentsRejected' => PhotoComment::excludeAdmins()->where('status', 'rejected')->count(),
                'commentsSpam' => PhotoComment::excludeAdmins()->where('status', 'spam')->count(),
                'newCommentsToday' => PhotoComment::excludeAdmins()->whereDate('created_at', today())->count(),
                'commentsData' => $this->getCommentsData(),
                'commentCategories' => $this->getCommentCategories(),        
            ];
        });

        // ✅ Онлайн считаем отдельно (не кешируем). Соединяем с таблицей users, чтобы исключить админов
        $onlineUsers = DB::table('sessions')
            ->join('users', 'sessions.user_id', '=', 'users.id')
            ->where('users.is_admin', false)
            ->where('sessions.last_activity', '>=', now()->subMinutes(5)->timestamp)
            ->distinct('sessions.user_id')
            ->count('sessions.user_id');

        // Объединяем
        return array_merge($metrics, ['onlineUsers' => $onlineUsers]);
    }

    // Вспомогательные методы для кеша

    private function getGenderStats(): array
    {
        // ✅ Исключаем админов
        return User::excludeAdmins()->select('gender', DB::raw('count(*) as total'))
            ->whereNotNull('gender')
            ->groupBy('gender')
            ->pluck('total', 'gender')
            ->toArray();
    }

    private function getAgeStats(): array
    {
        // ✅ Исключаем админов
        $users = User::excludeAdmins()->whereNotNull('birth_date')->get();
        $stats = ['18-24' => 0, '25-34' => 0, '35-44' => 0, '45-54' => 0, '55+' => 0];

        foreach ($users as $user) {
            if (!$user->birth_date) continue;
            $age = $user->birth_date->age;
            if ($age >= 18 && $age <= 24) $stats['18-24']++;
            elseif ($age >= 25 && $age <= 34) $stats['25-34']++;
            elseif ($age >= 35 && $age <= 44) $stats['35-44']++;
            elseif ($age >= 45 && $age <= 54) $stats['45-54']++;
            elseif ($age >= 55) $stats['55+']++;
        }

        return $stats;
    }

    private function getTopCities(): array
    {
        // ✅ Исключаем админов
        return User::excludeAdmins()->whereNotNull('city')
            ->select('city', DB::raw('count(*) as total'))
            ->groupBy('city')
            ->orderBy('total', 'desc')
            ->limit(5)
            ->pluck('total', 'city')
            ->toArray();
    }

    private function getRegistrationData(): array
    {
        // ✅ Исключаем админов
        $stats = User::excludeAdmins()->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as total'))
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->toArray();

        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $data[] = $stats[$date] ?? 0;
        }
        return $data;
    }

    private function getCategories(): array
    {
        $categories = [];
        for ($i = 6; $i >= 0; $i--) {
            $categories[] = now()->subDays($i)->format('D');
        }
        return $categories;
    }

    public function refresh(): void
    {
        Cache::forget('admin_dashboard_metrics');
        $this->redirect(route('admin.dashboard'));
    }

    /**
     * Очистка кеша проекта
     */
    public function clearCache(): void
    {
        try {
            Artisan::call('cache:project-clear');
            $this->dispatch('show-toast', type: 'success', message: 'Кеш успешно очищен!');
        } catch (\Exception $e) {
            $this->dispatch('show-toast', type: 'error', message: 'Ошибка: ' . $e->getMessage());
        }
    }

    private function getCommentsData(): array
    {
        // ✅ Исключаем админов
        $stats = PhotoComment::excludeAdmins()->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as total'))
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->toArray();

        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $data[] = $stats[$date] ?? 0;
        }
        return $data;
    }

    private function getCommentCategories(): array
    {
        $categories = [];
        for ($i = 6; $i >= 0; $i--) {
            $categories[] = now()->subDays($i)->format('D');
        }
        return $categories;
    }
}; 
?>

<div class="space-y-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <h1 class="text-2xl font-semibold">Панель управления</h1>
        <div class="flex items-center gap-3">
            <div class="text-sm text-muted-foreground">
                {{ now()->format('d.m.Y H:i') }}
            </div>
               <x-ui.button 
                    wire:click="clearCache" 
                    wire:loading.attr="disabled"
                    variant="outline" 
                    size="sm"
                    class="gap-2"
                >
                    <span wire:loading.remove wire:target="clearCache">
                        <x-lucide-rotate-ccw class="w-4 h-4" />
                    </span>
                    <span wire:loading wire:target="clearCache">
                        <x-ui.spinner class="w-4 h-4" />
                    </span>
                    <span wire:loading.remove wire:target="clearCache">Очистить кеш</span>
                    <span wire:loading wire:target="clearCache">Очистка...</span>
                </x-ui.button>
           <x-ui.button 
                wire:click="refresh" 
                wire:loading.attr="disabled"
                variant="outline" 
                size="sm"
                class="gap-2"
            >
                <span wire:loading.remove wire:target="refresh">
                    <x-lucide-refresh-ccw class="w-4 h-4" />
                </span>
                <span wire:loading wire:target="refresh">
                    <x-ui.spinner class="w-4 h-4" />
                </span>
                <span wire:loading.remove wire:target="refresh">Обновить</span>
                <span wire:loading wire:target="refresh">Обновление...</span>
            </x-ui.button>
        </div>
    </div>

    <!-- Основные метрики -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <!-- Всего юзеров -->
        <x-ui.card>
            <div class="flex flex-col h-full">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">Всего юзеров</p>
                    <x-lucide-users class="w-5 h-5 text-blue-500" />
                </div>
                <p class="text-2xl font-bold mt-1">{{ $totalUsers }}</p>
                <div class="flex items-center gap-2 mt-auto text-xs pt-2 flex-wrap">
                    <span class="text-green-500">+{{ $newUsersToday }}</span>
                    <span class="text-muted-foreground">сегодня</span>
                    <span class="text-blue-500">+{{ $newUsersWeek }}</span>
                    <span class="text-muted-foreground">за неделю</span>
                </div>
            </div>
        </x-ui.card>

        <!-- Онлайн -->
        <x-ui.card>
            <div class="flex flex-col h-full">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">Онлайн</p>
                    <x-lucide-circle class="w-5 h-5 text-green-500 fill-green-500" />
                </div>
                <p class="text-2xl font-bold mt-1">{{ $onlineUsers }}</p>
                <p class="text-xs text-muted-foreground mt-auto pt-2">за последние 5 минут</p>
            </div>
        </x-ui.card>

        <!-- Фото -->
        <x-ui.card>
            <div class="flex flex-col h-full">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">Фото</p>
                    <x-lucide-image class="w-5 h-5 text-purple-500" />
                </div>
                <p class="text-2xl font-bold mt-1">{{ $photosTotal }}</p>
                <div class="flex items-center gap-2 mt-auto text-xs pt-2 flex-wrap">
                    <span class="text-yellow-500">{{ $photosPending }}</span>
                    <span class="text-muted-foreground">на модерации</span>
                    <span class="text-green-500">{{ $photosApproved }}</span>
                    <span class="text-muted-foreground">одобрено</span>
                </div>
            </div>
        </x-ui.card>

        <!-- Комментарии -->
        <x-ui.card>
            <div class="flex flex-col h-full">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">Комментарии</p>
                    <x-lucide-message-circle class="w-5 h-5 text-teal-500" />
                </div>
                <p class="text-2xl font-bold mt-1">{{ $commentsTotal }}</p>
                <div class="flex flex-wrap items-center gap-2 mt-auto text-xs pt-2">
                    <span class="text-yellow-500">{{ $commentsPending }}</span>
                    <span class="text-muted-foreground">на модерации</span>
                    <span class="text-green-500">{{ $commentsApproved }}</span>
                    <span class="text-muted-foreground">одобрено</span>
                    <span class="text-red-500">{{ $commentsSpam }}</span>
                    <span class="text-muted-foreground">спам</span>
                </div>
                <div class="text-xs text-muted-foreground pt-1">
                    +{{ $newCommentsToday }} сегодня
                </div>
            </div>
        </x-ui.card>

        <!-- Жалобы -->
        <x-ui.card>
            <div class="flex flex-col h-full">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">Жалобы</p>
                    <x-lucide-flag class="w-5 h-5 text-red-500" />
                </div>
                <p class="text-2xl font-bold mt-1">{{ $reportsTotal }}</p>
                <div class="flex items-center gap-2 mt-auto text-xs pt-2">
                    <span class="text-red-500">{{ $reportsPending }}</span>
                    <span class="text-muted-foreground">ожидают</span>
                </div>
            </div>
        </x-ui.card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Регистрации за неделю -->
        <x-ui.card>
            <h3 class="font-semibold text-sm mb-3">Регистрации за неделю</h3>
            @php
                $regColors = [
                    'var(--chart-1)',
                    'var(--chart-2)',
                    'var(--chart-3)',
                    'var(--chart-4)',
                    'var(--chart-5)',
                    'var(--chart-6)',
                    'var(--chart-7)',
                ];
            @endphp
            <x-ui.chart type="bar" :config="['registrations' => ['label' => 'Регистрации', 'color' => 'var(--chart-1)']]" :series="[['name' => 'Регистрации', 'data' => $registrationData]]" :colors="$regColors" :options="[
                'xaxis' => [
                    'categories' => $categories,
                    'labels' => ['show' => true],
                    'axisBorder' => ['show' => false],
                    'axisTicks' => ['show' => false],
                ],
                'plotOptions' => [
                    'bar' => [
                        'borderRadius' => 5,
                        'columnWidth' => '60%',
                        'distributed' => true,
                    ],
                ],
                'yaxis' => ['show' => true],
                'grid' => ['show' => false],
                'legend' => ['show' => false],
            ]"
                class="aspect-auto h-[250px]" />
        </x-ui.card>

        <!-- Статистика по полу -->
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
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        <!-- Комментирование фотографий (area график) -->
        <x-ui.card>
            <h3 class="font-semibold text-sm mb-3">Комментирование фотографий</h3>
            <x-ui.chart
                type="area"
                :config="['comments' => ['label' => 'Комментарии', 'color' => 'var(--chart-1)']]"
                :series="[['name' => 'Комментарии', 'data' => $commentsData]]"
                :colors="['var(--chart-1)']"
                :options="[
                    'xaxis' => ['categories' => $commentCategories],
                    'fill' => [
                        'type' => 'gradient',
                        'gradient' => [
                            'shadeIntensity' => 1,
                            'opacityFrom' => 0.4,
                            'opacityTo' => 0.1,
                            'stops' => [0, 100]
                        ]
                    ],
                    'stroke' => ['width' => 2, 'curve' => 'smooth'],
                    'yaxis' => ['show' => false],
                    'tooltip' => ['x' => ['show' => true]],
                ]"
                class="aspect-auto h-[250px]"
            />
        </x-ui.card>

        <!-- Статусы комментариев (круговая диаграмма) -->
        <x-ui.card>
            <h3 class="font-semibold text-sm mb-3">Статусы комментариев к фоткам</h3>
            @php
                $statusLabels = ['Одобрены', 'На модерации', 'Отклонены', 'Спам'];
                $statusData = [$commentsApproved, $commentsPending, $commentsRejected, $commentsSpam];
                $statusColors = ['var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)'];
                $hasStatusData = array_sum($statusData) > 0;
            @endphp
            @if($hasStatusData)
                <x-ui.chart
                    type="pie"
                    :series="$statusData"
                    :labels="$statusLabels"
                    :colors="$statusColors"
                    :options="['legend' => ['show' => false], 'stroke' => ['width' => 0], 'tooltip' => ['enabled' => true], 'dataLabels' => ['enabled' => true]]"
                    class="mx-auto aspect-square max-h-[250px] px-0"
                />
            @else
                <div class="flex items-center justify-center h-[250px] text-muted-foreground">
                    <div class="text-center">
                        <x-lucide-message-circle class="w-12 h-12 mx-auto mb-2 opacity-30" />
                        <p>Нет комментариев</p>
                    </div>
                </div>
            @endif
        </x-ui.card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Возрастное распределение -->
        <x-ui.card>
            <h3 class="font-semibold text-sm mb-3">Возрастное распределение</h3>
            @php
                $ageLabels = array_keys($ageStats);
                $ageData = array_values($ageStats);
                $hasAgeData = !empty($ageData) && array_sum($ageData) > 0;
                $ageColors = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)'];
            @endphp

            @if($hasAgeData)
                <x-ui.chart type="bar" :config="[
                    '18-24' => ['label' => '18-24', 'color' => 'var(--chart-1)'],
                    '25-34' => ['label' => '25-34', 'color' => 'var(--chart-2)'],
                    '35-44' => ['label' => '35-44', 'color' => 'var(--chart-3)'],
                    '45-54' => ['label' => '45-54', 'color' => 'var(--chart-4)'],
                    '55+' => ['label' => '55+', 'color' => 'var(--chart-5)'],
                ]" :series="[['name' => 'Возраст', 'data' => $ageData]]" :colors="$ageColors" :options="[
                    'xaxis' => [
                        'categories' => $ageLabels,
                        'labels' => ['show' => true],
                        'axisBorder' => ['show' => false],
                        'axisTicks' => ['show' => false],
                    ],
                    'plotOptions' => [
                        'bar' => [
                            'borderRadius' => 5,
                            'columnWidth' => '50%',
                            'distributed' => true,
                        ],
                    ],
                    'yaxis' => ['show' => true],
                    'grid' => ['show' => false],
                    'legend' => ['show' => false],
                ]"
                    class="aspect-auto h-[250px]" />
            @else
                <div class="flex items-center justify-center h-[250px] text-muted-foreground">
                    <div class="text-center">
                        <x-lucide-users class="w-12 h-12 mx-auto mb-2 opacity-30" />
                        <p>Нет данных о возрасте</p>
                        <p class="text-xs">Пользователи не указали дату рождения</p>
                    </div>
                </div>
            @endif
        </x-ui.card>

        <!-- Топ городов -->
        <x-ui.card>
            <h3 class="font-semibold text-sm mb-3">Топ городов по количеству пользователей</h3>
            @if(!empty($topCities) && count($topCities) > 0)
                @php
                    $cityLabels = array_keys($topCities);
                    $cityData = array_values($topCities);
                    $cityColors = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)'];
                    $cityConfig = [];
                    foreach ($cityLabels as $index => $label) {
                        $cityConfig[$label] = ['label' => $label, 'color' => $cityColors[$index]];
                    }
                @endphp
                <x-ui.chart type="bar" :config="$cityConfig" :series="[['name' => 'Пользователи', 'data' => $cityData]]" :colors="$cityColors" :options="[
                    'xaxis' => [
                        'categories' => $cityLabels,
                        'labels' => ['show' => false],
                        'axisBorder' => ['show' => false],
                        'axisTicks' => ['show' => false],
                    ],
                    'plotOptions' => [
                        'bar' => [
                            'horizontal' => true,
                            'borderRadius' => 5,
                            'distributed' => true,
                        ],
                    ],
                    'yaxis' => ['show' => true],
                    'grid' => ['show' => false],
                    'legend' => ['show' => false],
                ]"
                    class="aspect-auto h-[250px]" />
                @if (count($topCities) > 5)
                    <p class="text-xs text-muted-foreground mt-3 text-center">
                        + еще {{ count($topCities) - 5 }} городов
                    </p>
                @endif
            @else
                <div class="flex items-center justify-center h-[250px] text-muted-foreground">
                    <div class="text-center">
                        <x-lucide-map-pin class="w-12 h-12 mx-auto mb-2 opacity-30" />
                        <p>Нет данных о городах</p>
                        <p class="text-xs">Пользователи не указали город</p>
                    </div>
                </div>
            @endif
        </x-ui.card>
    </div>
</div>
