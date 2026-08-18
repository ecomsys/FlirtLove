<?php

use App\Models\SubscriptionPlan;
use App\Models\AdminLog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.admin')] class extends Component 
{
    // Состояние модалки
    public bool $showPlanModal = false;
    public ?int $editingPlanId = null;

    // Поля формы
    public string $name = '';
    public string $slug = '';
    public string $currency = 'RUB';
    public float $price = 0;    
    public int $trial_days = 0;
    public bool $is_active = true;
    public int $sort_order = 0;
    public string $apple_product_id = '';
    public string $google_product_id = '';
    public array $features = [];

    // ФИКС: Добавили свойство поиска
    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function openPlanModal(?int $planId = null): void
    {
        $this->resetErrorBag();
        
        if ($planId) {
            $plan = SubscriptionPlan::find($planId);
            $this->editingPlanId = $plan->id;
            $this->name = $plan->name;
            $this->slug = $plan->slug;
            $this->currency = $plan->currency;
            $this->price = $plan->price;
            $this->duration_days = $plan->duration_days;
            $this->is_active = $plan->is_active;
            $this->sort_order = $plan->sort_order;
            $this->apple_product_id = $plan->apple_product_id ?? '';
            $this->google_product_id = $plan->google_product_id ?? '';
            
            $this->features = [];
            foreach (\App\Enums\PlanFeature::cases() as $feature) {
                $dbValue = $plan->features[$feature->value] ?? null;
                $this->features[$feature->value] = $feature->isBoolean() ? (bool) $dbValue : (int) $dbValue;
            }
        } else {
            $this->reset(['editingPlanId', 'name', 'slug', 'price', 'duration_days', 'is_active', 'sort_order', 'apple_product_id', 'google_product_id']);
            $this->is_active = true;
            $this->price = 0;
            $this->duration_days = 30;
            $this->currency = 'RUB';
            
            $this->features = [];
            foreach (\App\Enums\PlanFeature::cases() as $feature) {
                $this->features[$feature->value] = $feature->isBoolean() ? false : 0;
            }
        }

        $this->showPlanModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:subscription_plans,slug,' . $this->editingPlanId,
            'currency' => 'required|string|size:3',
            'price' => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'sort_order' => 'required|integer|min:0',
            'apple_product_id' => 'nullable|string|max:255',
            'google_product_id' => 'nullable|string|max:255',
            'features' => 'required|array',
        ]);

        $cleanFeatures = [];
        foreach ($this->features as $key => $value) {
            if (is_bool($value)) {
                $cleanFeatures[$key] = $value;
            } else {
                $cleanFeatures[$key] = max(0, (int) $value);
            }
        }

        $data = [
            'name' => $this->name,
            'slug' => $this->slug,
            'currency' => $this->currency,
            'price' => $this->price,
            'duration_days' => $this->duration_days,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'apple_product_id' => $this->apple_product_id ?: null,
            'google_product_id' => $this->google_product_id ?: null,
            'features' => $cleanFeatures,
        ];

        if ($this->editingPlanId) {
            $plan = SubscriptionPlan::find($this->editingPlanId);
            $before = $plan->only(array_keys($data));
            $plan->update($data);
            AdminLog::record('plan.update', $plan, auth()->user(), $before, $plan->fresh()->only(array_keys($data)));
            $this->dispatch('show-toast', type: 'success', message: 'Тариф обновлен');
        } else {
            $plan = SubscriptionPlan::create($data);
            AdminLog::record('plan.create', $plan, auth()->user(), null, $plan->only(array_keys($data)));
            $this->dispatch('show-toast', type: 'success', message: 'Тариф создан');
        }

        $this->showPlanModal = false;
    }

    public function toggleActive(int $planId): void
    {
        $plan = SubscriptionPlan::find($planId);
        if (!$plan) return;

        $before = ['is_active' => $plan->is_active];
        $plan->update(['is_active' => !$plan->is_active]);
        $after = ['is_active' => $plan->fresh()->is_active];

        AdminLog::record('plan.toggle_active', $plan, auth()->user(), $before, $after);
        
        $this->dispatch('show-toast', type: 'success', message: $after['is_active'] ? 'Тариф активирован' : 'Тариф скрыт');
    }

    // ФИКС: Добавили логику поиска
    public function with(): array
    {
        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
        $search = $this->search;

        $plans = SubscriptionPlan::ordered()
            ->when($this->search, function ($query) use ($search, $operator) {
                $query->where(function ($q) use ($search, $operator) {
                    $q->where('name', $operator, "%{$search}%")
                      ->orWhere('slug', $operator, "%{$search}%");
                    if (is_numeric($search)) {
                        $q->orWhere('id', (int)$search);
                    }
                });
            })
            ->get();

        return [
            'plans' => $plans,
        ];
    }
}; 
?>

<div class="space-y-6 pb-6">
    <!-- Шапка с кнопкой "Назад" -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-4">
            @php
                $previousUrl = url()->previous();
                $backUrl = ($previousUrl && $previousUrl !== url()->current()) 
                    ? $previousUrl 
                    : route('admin.dashboard');
            @endphp
            <a href="{{ $backUrl }}" wire:navigate class="p-2 rounded-md hover:bg-accent text-muted-foreground hover:text-foreground transition-colors">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-semibold flex items-center gap-2">
                    <x-lucide-credit-card class="w-6 h-6" />
                    Тарифы и подписки
                </h1>
                <p class="text-sm text-muted-foreground">Управление планами монетизации</p>
            </div>
        </div>
        
        <div class="flex items-center gap-2">
            {{-- ФИКС: Добавили строку поиска --}}
            <div class="relative w-48">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск по ID или названию..." class="pl-9 pr-8" />
                <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            </div>
            <x-ui.button wire:click="openPlanModal()" variant="default" size="sm">
                <x-lucide-plus class="w-4 h-4" /> Добавить тариф
            </x-ui.button>
        </div>
    </div>

    <!-- Список тарифов -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach ($plans as $plan)
            @php 
                // ФИКС: Подсветка искомого тарифа
                $isHighlighted = is_numeric($this->search) && $plan->id == (int)$this->search;
            @endphp
            <div wire:key="plan-{{ $plan->id }}" 
                 class="bg-card border {{ $isHighlighted ? 'border-primary ring-2 ring-primary/50' : ($plan->is_active ? 'border-border' : 'border-destructive/30 opacity-70') }} rounded-xl overflow-hidden flex flex-col shadow-sm transition-all duration-200 transform {{ $isHighlighted ? 'scale-105 z-10' : '' }}">
                <div class="p-6 flex-1">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <h3 class="text-lg font-semibold">{{ $plan->name }}</h3>
                            <span class="text-xs text-muted-foreground font-mono rounded-sm bg-muted py-0.5 px-1">#{{ $plan->id }}</span>
                        </div>
                        <x-ui.badge variant="{{ $plan->is_active ? 'success' : 'secondary' }}" size="sm">
                            {{ $plan->is_active ? 'Активен' : 'Скрыт' }}
                        </x-ui.badge>
                    </div>
                    <div class="text-3xl font-bold mb-4">
                        {{ number_format($plan->price, 0, ',', ' ') }} {{ $plan->currency }}
                        <span class="text-sm font-normal text-muted-foreground">/ {{ $plan->duration_days }} дн.</span>
                    </div>

                    <div class="space-y-2 text-sm text-muted-foreground">
                        <div class="flex items-center gap-2">
                            <x-lucide-clock class="w-4 h-4" />
                            <span>Длительность: {{ $plan->duration_days }} дней</span>
                        </div>                        
                        <div class="flex items-center gap-2">
                            <x-lucide-tag class="w-4 h-4" />
                            <span class="font-mono text-xs">{{ $plan->slug }}</span>
                        </div>
                    </div>

                    <!-- Фичи (JSON) -->
                    @if(!empty($plan->features))
                        <div class="mt-4 pt-4 border-t border-border space-y-2">
                            @foreach($plan->features as $key => $value)
                                @php 
                                    $featureEnum = \App\Enums\PlanFeature::tryFrom($key);
                                    $isAvailable = !($value === false || $value === 0);
                                @endphp
                                @if($featureEnum)
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="flex items-center gap-1.5 {{ $isAvailable ? 'text-foreground' : 'text-muted-foreground/50 line-through' }}">
                                            <x-dynamic-component component="lucide-{{ $featureEnum->icon() }}" class="w-3.5 h-3.5" />
                                            {{ $featureEnum->label() }}
                                        </span>
                                        <span class="font-medium {{ $isAvailable ? 'text-green-500' : 'text-muted-foreground/50' }}">
                                            {{ $featureEnum->formatValue($value) }}
                                        </span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif

                    <!-- Apple/Google ID (для мобилок) -->
                    @if($plan->apple_product_id || $plan->google_product_id)
                        <div class="mt-4 pt-4 border-t border-border space-y-1">
                            @if($plan->apple_product_id) <p class="text-xs text-muted-foreground">Apple ID: <span class="font-mono">{{ $plan->apple_product_id }}</span></p> @endif
                            @if($plan->google_product_id) <p class="text-xs text-muted-foreground">Google ID: <span class="font-mono">{{ $plan->google_product_id }}</span></p> @endif
                        </div>
                    @endif
                </div>

                <div class="p-4 border-t border-border bg-muted/20 flex items-center justify-between">
                    <x-ui.button wire:click="toggleActive({{ $plan->id }})" variant="{{ $plan->is_active ? 'outline' : 'success' }}" size="sm" wire:loading.attr="disabled" wire:target="toggleActive({{ $plan->id }})">
                        {{ $plan->is_active ? 'Скрыть' : 'Активировать' }}
                    </x-ui.button>

                    <x-ui.button wire:click="openPlanModal({{ $plan->id }})" variant="ghost" size="icon-sm" title="Редактировать">
                        <x-lucide-pencil class="w-4 h-4" />
                    </x-ui.button>
                </div>
            </div>
        @endforeach
    </div>

    <!-- МОДАЛКА СОЗДАНИЯ/РЕДАКТИРОВАНИЯ -->
    @if($showPlanModal)
        <div wire:key="plan-modal-{{ $editingPlanId ?? 'new' }}"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
             wire:click="$set('showPlanModal', false)">
             
            <div class="relative bg-card border border-border rounded-lg shadow-2xl max-w-2xl w-full mx-4 overflow-hidden flex flex-col max-h-[90vh] sm:max-h-[85vh]" wire:click.stop>
                 
                <div class="flex items-center justify-between p-4 border-b border-border shrink-0">
                    <h2 class="text-lg font-semibold">{{ $editingPlanId ? 'Редактировать тариф' : 'Новый тариф' }}</h2>
                    <x-ui.button variant="ghost" size="icon-sm" wire:click="$set('showPlanModal', false)">
                        <x-lucide-x class="w-5 h-5" />
                    </x-ui.button>
                </div>

                <div class="p-6 space-y-4 overflow-y-auto little-scroll">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="flex flex-col gap-2">
                            <x-ui.label class="text-sm font-medium">Название</x-ui.label>
                            <x-ui.input wire:model="name" placeholder="VIP на 1 месяц" />
                            @error('name') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex flex-col gap-2">
                            <x-ui.label class="text-sm font-medium">Слаг (URL)</x-ui.label>
                            <x-ui.input wire:model="slug" placeholder="vip_1_month" />
                            @error('slug') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <div class="flex flex-col gap-2">
                            <x-ui.label class="text-sm font-medium">Цена</x-ui.label>
                            <x-ui.input wire:model="price" type="number" step="0.01" min="0" />
                            @error('price') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex flex-col gap-2">
                            <x-ui.label class="text-sm font-medium">Валюта</x-ui.label>
                            <x-ui.input wire:model="currency" placeholder="RUB" maxlength="3"/>
                            @error('currency') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex flex-col gap-2">
                            <x-ui.label class="text-sm font-medium">Срок (дни)</x-ui.label>
                            <x-ui.input wire:model="duration_days" type="number" min="0"/>
                            @error('duration_days') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                        </div>                 
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="flex flex-col gap-2">
                            <x-ui.label class="text-sm font-medium">Сортировка</x-ui.label>
                            <x-ui.input wire:model="sort_order" type="number" min="0"/>
                            @error('sort_order') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex flex-col gap-2">
                            <x-ui.label class="text-sm font-medium">Apple Product ID</x-ui.label>
                            <x-ui.input wire:model="apple_product_id" placeholder="com.app.vip" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <x-ui.label class="text-sm font-medium">Google Product ID</x-ui.label>
                            <x-ui.input wire:model="google_product_id" placeholder="vip_1_month" />
                        </div>
                    </div>

                    <!-- Конструктор фич -->
                    <div class="flex flex-col gap-2">
                        <x-ui.label class="text-sm font-medium">Фичи тарифа</x-ui.label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 border border-border rounded-md bg-muted/10">
                            @foreach(\App\Enums\PlanFeature::cases() as $feature)
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm flex items-center gap-1.5">
                                        <x-dynamic-component component="lucide-{{ $feature->icon() }}" class="w-3.5 h-3.5 text-muted-foreground" />
                                        {{ $feature->label() }}
                                    </span>
                                    
                                    @if($feature->isBoolean())
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" wire:model="features.{{ $feature->value }}" class="sr-only peer" />
                                            <div class="w-9 h-5 bg-muted rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-primary after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:border-primary"></div>
                                        </label>
                                    @else
                                        <input type="number" min="0" wire:model="features.{{ $feature->value }}" class="flex h-8 w-20 rounded-md border border-input bg-background px-2 py-1 text-sm text-center shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" placeholder="0" />
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <p class="text-[10px] text-muted-foreground">Укажите 999 для безлимитного лимита.</p>
                    </div>
                </div>

                <!-- Футер -->
                <div class="flex items-center justify-between p-4 border-t border-border bg-muted/20 shrink-0">
                    <div class="flex items-center gap-3">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="is_active" class="sr-only peer" />
                            <div class="w-11 h-6 bg-muted rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-primary after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:border-primary"></div>
                        </label>
                        <span class="text-sm font-medium">Тариф в продаже</span>
                    </div>
                    
                    <div class="flex gap-2">
                        <x-ui.button wire:click="$set('showPlanModal', false)" variant="outline" size="sm">Отмена</x-ui.button>
                        <x-ui.button wire:click="save" variant="default" size="sm" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save">Сохранить</span>
                            <span wire:loading wire:target="save" class="flex items-center gap-2">
                                <x-lucide-loader-2 class="w-4 h-4 animate-spin inline" /> Сохранение...
                            </span>
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>