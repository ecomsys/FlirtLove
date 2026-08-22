<?php

use App\Actions\Admin\GeoIPLocationAction;
use App\Models\GeoIPLocation;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Session;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] class extends Component 
{
    use WithPagination;

    #[Session] 
    public string $search = '';
    #[Session] 
    public string $typeFilter = 'country'; // По умолчанию показываем только страны
    #[Session] 
    public int $perPage = 50;

    public function updatedSearch(): void { $this->resetPage(); }

    // НОВЫЙ МЕТОД: Переключение фильтра кнопками
    public function setTypeFilter(string $type): void 
    { 
        $this->typeFilter = $type; 
        $this->resetPage(); 
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->typeFilter = 'country';
        $this->resetPage();
    }

    #[Computed]
    public function locations()
    {
        $searchOperator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

        return GeoIPLocation::query()
            ->when($this->search, function ($q) use ($searchOperator) {
                $q->where('name', $searchOperator, "%{$this->search}%")
                  ->orWhere('iso_code', $searchOperator, "%{$this->search}%");
            })
            ->when($this->typeFilter !== 'all', fn($q) => $q->where('type', $this->typeFilter))
            ->orderBy('is_registration_blocked', 'desc') // Заблокированные сверху
            ->orderBy('name')
            ->paginate(min(max($this->perPage, 10), 200));
    }

    // НОВЫЙ МЕТОД: Счетчики для кнопок
    #[Computed]
    public function counts(): array
    {
        $stats = GeoIPLocation::query()
            ->selectRaw("COUNT(*) as total")
            ->selectRaw("SUM(CASE WHEN type = 'country' THEN 1 ELSE 0 END) as country")
            ->selectRaw("SUM(CASE WHEN type = 'region' THEN 1 ELSE 0 END) as region")
            ->first();

        return [
            'total' => $stats->total ?? 0,
            'country' => $stats->country ?? 0,
            'region' => $stats->region ?? 0,
        ];
    }

    public function toggleRegistration(int $id, GeoIPLocationAction $action): void
    {
        $loc = GeoIPLocation::find($id);
        if ($loc) {
            $action->toggleRegistration($loc);
            $this->dispatch('show-toast', type: 'success', message: 'Правило регистрации обновлено');
        }
    }

    public function toggleFeed(int $id, GeoIPLocationAction $action): void
    {
        $loc = GeoIPLocation::find($id);
        if ($loc) {
            $action->toggleFeed($loc);
            $this->dispatch('show-toast', type: 'success', message: 'Правило ленты обновлено');
        }
    }
}; 
?>

<div class="space-y-6 pb-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.dashboard') }}" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors">
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

            @if($search || $typeFilter !== 'country')
                <x-ui.button wire:key="geo-clear-btn" wire:click="clearFilters" variant="ghost" size="sm" class="text-muted-foreground ml-2">
                    <x-lucide-x class="w-4 h-4" /> Сбросить
                </x-ui.button>
            @endif
        </div>

        <div class="relative w-64">
            <x-ui.input wire:key="geo-search-input" wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск по имени или ISO коду..." class="pl-9" />
            <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
        </div>
    </div>

    <!-- ТАБЛИЦА -->
    <x-ui.table>
        <x-ui.table-header>
            <x-ui.table-row>
                <x-ui.table-head>Название</x-ui.table-head>
                <x-ui.table-head>Тип</x-ui.table-head>
                <x-ui.table-head>ISO</x-ui.table-head>
                <x-ui.table-head class="text-center">Регистрация</x-ui.table-head>
                <x-ui.table-head class="text-center">Лента (Feed)</x-ui.table-head>
            </x-ui.table-row>
        </x-ui.table-header>

        <x-ui.table-body>
            @forelse ($this->locations as $loc)
                <x-ui.table-row wire:key="geo-row-{{ $loc->id }}" class="{{ $loc->is_registration_blocked ? 'bg-destructive/5' : '' }}">
                   <x-ui.table-cell class="font-medium">
                        @php
                            // Если это страна, переводим через вендор-неймспейс: world::country.RU
                            // Важно: файл называется country.php (в единственном числе!)
                            if ($loc->type === 'country' && $loc->iso_code) {
                                $key = 'world::country.' . $loc->iso_code;
                                $translated = __($key);
                                $displayName = ($translated === $key) ? $loc->name : $translated;
                            } else {
                                $displayName = $loc->name;
                            }
                        @endphp
                        {{ $displayName }}
                    </x-ui.table-cell>
                    
                    <x-ui.table-cell>
                        <x-ui.badge variant="secondary" size="sm">{{ $loc->type }}</x-ui.badge>
                    </x-ui.table-cell>
                    
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
                    <x-ui.table-cell colspan="5" class="py-12 text-center text-muted-foreground">
                        <x-lucide-globe class="w-12 h-12 opacity-30 mx-auto mb-2" />
                        Локации не найдены.
                    </x-ui.table-cell>
                </x-ui.table-row>
            @endforelse
        </x-ui.table-body>
    </x-ui.table>

    <!-- Пагинация -->
    <div class="flex items-center justify-end flex-wrap gap-2">
        <div class="text-xs text-muted-foreground">
            Показано {{ $this->locations->firstItem() ?? 0 }} - {{ $this->locations->lastItem() ?? 0 }} из {{ $this->locations->total() }}
        </div>
        {{ $this->locations->links('partials.pagination') }}
    </div>
</div>