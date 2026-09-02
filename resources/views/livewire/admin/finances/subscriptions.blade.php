<?php

use App\Actions\Admin\ManageSubscriptionPlansAction;
use App\Models\SubscriptionPlan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.admin')] class extends Component 
{
    #[Url(as: 'tab', except: 'premium')]
    public string $activeTab = 'premium';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public bool $showPlanModal = false;
    public ?int $editingPlanId = null;

    public string $name = '';
    public string $slug = '';
    public float $price = 0;    
    public ?float $old_price = null;
    public int $duration_days = 30;
    public bool $is_active = true;
    public int $sort_order = 0;
    public string $apple_product_id = '';
    public string $google_product_id = '';

    public string $backUrl = '';

    public function mount(): void
    {
        $previousUrl = url()->previous();
        $this->backUrl = ($previousUrl && $previousUrl !== url()->current()) 
            ? $previousUrl 
            : route('admin.dashboard');
    }

    public function clearSearch(): void
    {
        $this->search = '';
    }

    public array $premiumFeatures = [
        'Свободно пиши всем девушкам, которые понравились',
        'Посмотри, кто поставил тебе лайк и не против встретиться',
        'Увеличь шансы в 10 раз — напиши сразу нескольким девушкам',
        'Получи преимущество в общении с новыми и популярными девушками',
        'Просматривай анкеты в режиме "Невидимки"',
        'Отключи показ рекламы'
    ];

    public array $vipFeatures = [
        'Показ в Топе раз в три дня',
        'Фото показываются первыми в знакомствах',
        'Сообщения показываются выше других'
    ];

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->search = '';
    }

    public function openPlanModal(?int $planId = null): void
    {
        $this->resetErrorBag();
        
        if ($planId) {
            $plan = SubscriptionPlan::find($planId);
            $this->editingPlanId = $plan->id;
            $this->name = $plan->name;
            $this->slug = $plan->slug;
            $this->price = $plan->price;
            $this->old_price = $plan->old_price;
            $this->duration_days = $plan->duration_days;
            $this->is_active = $plan->is_active;
            $this->sort_order = $plan->sort_order;
            $this->apple_product_id = $plan->apple_product_id ?? '';
            $this->google_product_id = $plan->google_product_id ?? '';
        } else {
            $this->reset(['editingPlanId', 'name', 'slug', 'price', 'old_price', 'apple_product_id', 'google_product_id']);
            $this->is_active = true;
            $this->price = 0;
            $this->duration_days = 30;
            $this->sort_order = 0;
        }

        $this->showPlanModal = true;
    }

    public function save(ManageSubscriptionPlansAction $action): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:subscription_plans,slug,' . $this->editingPlanId,
            'price' => 'required|numeric|min:0',
            'old_price' => 'nullable|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'sort_order' => 'required|integer|min:0',
            'apple_product_id' => 'nullable|string|max:255',
            'google_product_id' => 'nullable|string|max:255',
        ]);

        if ($this->editingPlanId) {
            $plan = SubscriptionPlan::find($this->editingPlanId);
            $action->updatePlan($plan, $validated, auth()->user());
            $this->dispatch('show-toast', type: 'success', message: 'Тариф обновлен');
        } else {
            $validated['tier'] = $this->activeTab;
            $validated['is_active'] = $this->is_active;
            $action->createPlan($validated, auth()->user());
            $this->dispatch('show-toast', type: 'success', message: 'Тариф создан');
        }

        $this->showPlanModal = false;
    }

    public function toggleActive(int $planId, ManageSubscriptionPlansAction $action): void
    {
        $plan = SubscriptionPlan::find($planId);
        if (!$plan) return;

        $isActive = $action->toggleActive($plan, auth()->user());
        $this->dispatch('show-toast', type: 'success', message: $isActive ? 'Тариф активирован' : 'Тариф скрыт');
    }

    public function with(): array
    {
        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
        $search = $this->search;

        $plans = SubscriptionPlan::where('tier', $this->activeTab)
            ->ordered()
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
            'premiumCount' => SubscriptionPlan::where('tier', 'premium')->count(),
            'vipCount' => SubscriptionPlan::where('tier', 'vip')->count(),
            'currentFeatures' => $this->activeTab === 'premium' ? $this->premiumFeatures : $this->vipFeatures,
        ];
    }
}; 
?>

<div class="space-y-6 pb-6">
    <!-- Шапка -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-4">
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
            <div class="relative w-55">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" placeholder="Поиск по ID или названию..." class="pl-9 pr-8" />
                <x-lucide-search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                @if (!empty($search))
                    <button wire:click="clearSearch" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground z-10">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                @endif
            </div>
            <x-ui.button wire:click="openPlanModal()" variant="default" size="sm">
                <x-lucide-plus class="w-4 h-4" /> Добавить тариф
            </x-ui.button>
        </div>
    </div>

    <!-- ВКЛАДКИ (TABS) -->
    <div class="border-b border-border">
        <nav class="flex gap-x-4 flex-wrap">
            <button wire:click="setTab('premium')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'premium' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                Premium <span class="ml-1 text-xs text-muted-foreground">({{ $premiumCount }})</span>
            </button>
            <button wire:click="setTab('vip')" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'vip' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                VIP (Буст выдачи) <span class="ml-1 text-xs text-muted-foreground">({{ $vipCount }})</span>
            </button>
        </nav>
    </div>

    <!-- ПЛАШКА С ОПЦИЯМИ ТАРИФА (Неизменные фичи) -->
    <div class="bg-card border border-border rounded-lg p-4 shadow-sm">
        <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide mb-3 flex items-center gap-2">
            <x-lucide-list-checks class="w-4 h-4" />
            Что входит в тариф {{ $activeTab === 'premium' ? 'Premium' : 'VIP' }}
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-2">
            @foreach ($currentFeatures as $feat)
                <div class="flex items-start gap-2 text-sm text-foreground">
                    <x-lucide-check class="w-4 h-4 text-green-500 mt-0.5 shrink-0" />
                    <span>{{ $feat }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Список тарифов (Компактные карточки) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach ($plans as $plan)
            @php 
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
                    
                    <div class="flex items-end gap-2 mb-4">
                        <div class="text-3xl font-bold">
                            {{ number_format($plan->price, 0, ',', ' ') }} ₽
                        </div>
                        @if($plan->old_price)
                            <div class="text-lg text-muted-foreground line-through mb-1">
                                {{ number_format($plan->old_price, 0, ',', ' ') }} ₽
                            </div>
                        @endif
                        <span class="text-sm font-normal text-muted-foreground mb-1">/ {{ $plan->duration_days }} дн.</span>
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
                    <h2 class="text-lg font-semibold">
                        {{ $editingPlanId ? 'Редактировать тариф' : 'Новый тариф' }} 
                        <span class="text-xs text-muted-foreground uppercase ml-1">({{ $activeTab }})</span>
                    </h2>
                    <x-ui.button variant="ghost" size="icon-sm" wire:click="$set('showPlanModal', false)">
                        <x-lucide-x class="w-5 h-5" />
                    </x-ui.button>
                </div>

                <div class="p-6 space-y-4 overflow-y-auto little-scroll">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="flex flex-col gap-2">
                            <x-ui.label class="text-sm font-medium">Название</x-ui.label>
                            <x-ui.input wire:model="name" placeholder="Premium на 1 месяц" />
                            @error('name') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex flex-col gap-2">
                            <x-ui.label class="text-sm font-medium">Слаг (URL)</x-ui.label>
                            <x-ui.input wire:model="slug" placeholder="premium_1_month" />
                            @error('slug') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <div class="flex flex-col gap-2">
                            <x-ui.label class="text-sm font-medium">Цена (₽)</x-ui.label>
                            <x-ui.input wire:model="price" type="number" step="0.01" min="0" />
                            @error('price') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex flex-col gap-2">
                            <x-ui.label class="text-sm font-medium">Старая цена (₽)</x-ui.label>
                            <x-ui.input wire:model="old_price" type="number" step="0.01" min="0" placeholder="Опционально" />
                            @error('old_price') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
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
                            <x-ui.input wire:model="apple_product_id" placeholder="com.app.premium" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <x-ui.label class="text-sm font-medium">Google Product ID</x-ui.label>
                            <x-ui.input wire:model="google_product_id" placeholder="premium_1_month" />
                        </div>
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