<?php

use App\Actions\Admin\GeoIPLocationAction;
use App\Models\GeoIPLocation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';
    
    #[Url(as: 'type', except: 'country')]
    public string $typeFilter = 'country';
    
    public int $perPage = 50;

    public string $backUrl = '';

    public function mount(): void
    {
        abort_unless(in_array(auth()->user()?->role, [User::ROLE_ADMIN, User::ROLE_MODERATOR]), 403);

        $previousUrl = url()->previous();
        $this->backUrl = ($previousUrl && $previousUrl !== url()->current()) 
            ? $previousUrl 
            : route('admin.dashboard');

        // ФИКС: Если перешли по ссылке с ID (?q=5), находим тип локации и переключаем вкладку
        if (ctype_digit($this->search) && !empty($this->search)) {
            $loc = GeoIPLocation::find((int) $this->search);
            if ($loc) {
                $this->typeFilter = $loc->type; // Автопереключение на вкладку локации
            } else {
                $this->typeFilter = 'all'; // Если не найдено, показываем все
            }
        }
    }

    public function updatedSearch(): void 
    { 
        $this->resetPage(); 
        $this->clearComputedCache();

        // ФИКС: Если админ ввел ID в поиск, переключаем вкладку под найденную локацию
        if (ctype_digit($this->search) && !empty($this->search)) {
            $loc = GeoIPLocation::find((int) $this->search);
            if ($loc) {
                $this->typeFilter = $loc->type;
            } else {
                $this->typeFilter = 'all';
            }
        }
    }

    public function setTypeFilter(string $type): void 
    { 
        $this->typeFilter = $type; 
        $this->search = ''; // ФИКС: Очищаем поиск при ручной смене вкладки
        $this->resetPage(); 
        $this->clearComputedCache();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->typeFilter = 'country';
        $this->resetPage();
        $this->clearComputedCache();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
        $this->clearComputedCache();
    }

    #[Computed]
    public function locations()
    {
        $searchOperator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
        $isId = !empty($this->search) && ctype_digit($this->search);

        return GeoIPLocation::query()
            ->when($this->search, function ($q) use ($searchOperator, $isId) {
                $q->where(function ($sub) use ($searchOperator, $isId) {
                    $sub->where('name', $searchOperator, "%{$this->search}%")
                        ->orWhere('iso_code', $searchOperator, "%{$this->search}%");
                        
                    if ($isId) {
                        $sub->orWhere('id', (int) $this->search);
                    }
                });
            })
            ->when($this->typeFilter !== 'all', fn($q) => $q->where('type', $this->typeFilter))
            ->orderBy('is_registration_blocked', 'desc')
            ->orderBy('name')
            ->paginate(min(max($this->perPage, 10), 200));
    }

    #[Computed]
    public function counts(): array
    {
        return Cache::remember('admin_geo_counts', 60, function () {
            $stats = GeoIPLocation::query()
                ->selectRaw("COUNT(*) as total")
                ->selectRaw("SUM(CASE WHEN type = 'country' THEN 1 ELSE 0 END) as country")
                ->selectRaw("SUM(CASE WHEN type = 'region' THEN 1 ELSE 0 END) as region")
                ->first();

            return [
                'total' => (int) ($stats?->total ?? 0),
                'country' => (int) ($stats?->country ?? 0),
                'region' => (int) ($stats?->region ?? 0),
            ];
        });
    }

    public function toggleRegistration(int $id, GeoIPLocationAction $action): void
    {
        $loc = GeoIPLocation::find($id);
        if ($loc) {
            $action->toggleRegistration($loc, auth()->user());
            $this->dispatch('show-toast', type: 'success', message: 'Правило регистрации обновлено');
            $this->clearComputedCache();
        }
    }

    public function toggleFeed(int $id, GeoIPLocationAction $action): void
    {
        $loc = GeoIPLocation::find($id);
        if ($loc) {
            $action->toggleFeed($loc, auth()->user());
            $this->dispatch('show-toast', type: 'success', message: 'Правило ленты обновлено');
            $this->clearComputedCache();
        }
    }

    private function clearComputedCache(): void
    {
        unset($this->locations);
        unset($this->counts);
        Cache::forget('admin_geo_counts');
    }
}; 
?>
<div class="space-y-6 pb-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
             <div class="flex items-center gap-3">
            <a href="{{ $backUrl }}" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-semibold flex items-center gap-2">
                    <x-lucide-globe class="w-6 h-6" />
                    Geo IP локации
                </h1>
                <p class="text-sm text-muted-foreground mt-1">
                    Управление блокировками стран и регионов. Отсекает бот-фермы на этапе регистрации или скрывает в ленте.
                </p>
            </div>
        </div>
    </div>

    <!-- ФИЛЬТРЫ (КНОПКИ) -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-1.5 items-center">
            <x-ui.button wire:key="btn-all" wire:click="setTypeFilter('all')" variant="{{ $typeFilter === 'all' ? 'default' : 'secondary' }}" size="sm">
                Все <x-ui.badge size="xs">{{ $this->counts['total'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:key="btn-country" wire:click="setTypeFilter('country')" variant="{{ $typeFilter === 'country' ? 'default' : 'secondary' }}" size="sm">
                Страны <x-ui.badge size="xs" variant="success">{{ $this->counts['country'] }}</x-ui.badge>
            </x-ui.button>
            <x-ui.button wire:key="btn-region" wire:click="setTypeFilter('region')" variant="{{ $typeFilter === 'region' ? 'default' : 'secondary' }}" size="sm">
                Регионы СНГ <x-ui.badge size="xs" variant="warning">{{ $this->counts['region'] }}</x-ui.badge>
            </x-ui.button>

        </div>

        <div class="flex flex-wrap gap-1.5 items-center">
              @if($search || $typeFilter !== 'country')
                    <x-ui.button wire:key="geo-clear-btn" wire:click="clearFilters" variant="outline" size="sm" class="text-muted-foreground ml-2">
                        <x-lucide-x class="w-4 h-4" /> Сбросить
                    </x-ui.button>
                @endif
            <div class="relative w-64">
                <x-ui.input wire:key="geo-search-input" wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск по имени или ISO коду..." class="pl-9 pr-8" />
                <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                @if (!empty($search))
                    <button wire:click="clearSearch" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground z-10">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                @endif
            </div>          
        </div>
    </div>

    <!-- ТАБЛИЦА -->
    <x-ui.table>
        <x-ui.table-header>
            <x-ui.table-row>
                 <x-ui.table-head class="w-16">ID</x-ui.table-head> 
                <x-ui.table-head>Название</x-ui.table-head>
                <x-ui.table-head>Тип</x-ui.table-head>
                <x-ui.table-head>ISO</x-ui.table-head>
                <x-ui.table-head class="text-center">Регистрация</x-ui.table-head>
                <x-ui.table-head class="text-center">Лента (Feed)</x-ui.table-head>
            </x-ui.table-row>
        </x-ui.table-header>

        <x-ui.table-body>
            @forelse ($this->locations as $loc)
                @php 
                    // Определяем переменную подсветки в самом начале цикла!
                    $isHighlighted = ctype_digit($this->search) && $loc->id === (int)$this->search; 
                @endphp
                <x-ui.table-row wire:key="geo-row-{{ $loc->id }}" class="{{ $loc->is_registration_blocked ? 'bg-destructive/5' : '' }} {{ $isHighlighted ? 'bg-blue-500/10 ring-2 ring-blue-500/50' : '' }}">
                    
                    <!-- НОВАЯ КОЛОНКА ID -->
                    <x-ui.table-cell class="text-xs font-mono text-muted-foreground whitespace-nowrap align-top {{ $isHighlighted ? 'text-blue-500 font-bold' : '' }}">
                        <span class="pt-1 block">#{{ $loc->id }}</span>
                    </x-ui.table-cell>

                    <!-- Название -->                 
                    <x-ui.table-cell class="font-medium">
                        @php
                            // Если есть русский перевод - берем его. Иначе берем английский.
                            $displayName = $loc->name_ru ?: $loc->name;
                        @endphp
                        <span title="{{ $displayName }}">{{ $displayName }}</span>
                    </x-ui.table-cell>
                    
                    <!-- Тип -->
                    <x-ui.table-cell>
                        <x-ui.badge variant="secondary" size="sm">{{ $loc->type }}</x-ui.badge>
                    </x-ui.table-cell>
                    
                    <!-- ISO -->
                    <x-ui.table-cell class="text-xs font-mono text-muted-foreground uppercase">
                        {{ $loc->iso_code ?: '—' }}
                    </x-ui.table-cell>
                    
                    <!-- ТУМБЛЕР РЕГИСТРАЦИИ -->
                    <x-ui.table-cell class="text-center">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:click="toggleRegistration({{ $loc->id }})" {{ $loc->is_registration_blocked ? 'checked' : '' }} class="sr-only peer" />
                            <div class="w-11 h-6 bg-muted rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-destructive after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-border after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                        </label>
                    </x-ui.table-cell>
                    
                    <!-- ТУМБЛЕР ЛЕНТЫ -->
                    <x-ui.table-cell class="text-center">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:click="toggleFeed({{ $loc->id }})" {{ $loc->is_feed_blocked ? 'checked' : '' }} class="sr-only peer" />
                            <div class="w-11 h-6 bg-muted rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-yellow-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-border after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                        </label>
                    </x-ui.table-cell>
                </x-ui.table-row>
            @empty
                <x-ui.table-row wire:key="geo-empty-state">
                    <x-ui.table-cell colspan="6" class="py-12 text-center text-muted-foreground">
                        <x-lucide-globe class="w-12 h-12 opacity-30 mx-auto mb-2" />
                        Локации не найдены.
                    </x-ui.table-cell>
                </x-ui.table-row>
            @endforelse
        </x-ui.table-body>
    </x-ui.table>

    <x-loading-overlay fixed="true" wire:loading.delay wire:key="overlay-loading-page"/>

    <!-- Пагинация -->
    <div class="flex items-center justify-end flex-wrap gap-2">
        <div class="text-xs text-muted-foreground">
            Показано {{ $this->locations->firstItem() ?? 0 }} - {{ $this->locations->lastItem() ?? 0 }} из {{ $this->locations->total() }}
        </div>
        {{ $this->locations->links('partials.pagination') }}
    </div>
</div>