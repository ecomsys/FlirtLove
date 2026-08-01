<?php

use App\Models\SubscriptionPlan;
use App\Models\AdminLog;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.admin')] class extends Component 
{
    // Состояние модалки
    public bool $showPlanModal = false;
    public ?int $editingPlanId = null;

    // Поля формы
    public string $name = '';
    public string $slug = '';
    public float $price = 0;
    public int $duration_days = 30;
    public int $trial_days = 0;
    public bool $is_active = true;
    public int $sort_order = 0;

    public function openPlanModal(?int $planId = null): void
    {
        $this->resetErrorBag();
        
        if ($planId) {
            $plan = SubscriptionPlan::find($planId);
            $this->editingPlanId = $plan->id;
            $this->name = $plan->name;
            $this->slug = $plan->slug;
            $this->price = $plan->price;
            $this->duration_days = $plan->duration_days;
            $this->trial_days = $plan->trial_days;
            $this->is_active = $plan->is_active;
            $this->sort_order = $plan->sort_order;
        } else {
            $this->reset(['editingPlanId', 'name', 'slug', 'price', 'duration_days', 'trial_days', 'is_active', 'sort_order']);
            $this->is_active = true;
        }

        $this->showPlanModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:subscription_plans,slug,' . $this->editingPlanId,
            'price' => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'trial_days' => 'required|integer|min:0',
            'sort_order' => 'required|integer|min:0',
        ]);

        $data = [
            'name' => $this->name,
            'slug' => $this->slug,
            'price' => $this->price,
            'duration_days' => $this->duration_days,
            'trial_days' => $this->trial_days,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];

        if ($this->editingPlanId) {
            $plan = SubscriptionPlan::find($this->editingPlanId);
            $plan->update($data);
            AdminLog::record('plan.update', $plan, auth()->user());
            $this->dispatch('show-toast', type: 'success', message: 'Тариф обновлен');
        } else {
            $plan = SubscriptionPlan::create($data);
            AdminLog::record('plan.create', $plan, auth()->user());
            $this->dispatch('show-toast', type: 'success', message: 'Тариф создан');
        }

        $this->showPlanModal = false;
    }

    public function toggleActive(int $planId): void
    {
        $plan = SubscriptionPlan::find($planId);
        if (!$plan) return;

        $plan->update(['is_active' => !$plan->is_active]);
        AdminLog::record('plan.toggle_active', $plan, auth()->user());

        $message = $plan->is_active ? 'Тариф активирован' : 'Тариф скрыт';
        $this->dispatch('show-toast', type: 'success', message: $message);
        
    }

    public function with(): array
    {
        return [
            'plans' => SubscriptionPlan::ordered()->get(),
        ];
    }
}; 
?>

<div class="space-y-6">
    <!-- Заголовок -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <h1 class="text-2xl font-semibold">Тарифы VIP</h1>
        <x-ui.button wire:click="openPlanModal()">
            <x-lucide-plus class="w-4 h-4" /> Добавить тариф
        </x-ui.button>
    </div>

    <!-- Список тарифов -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach ($plans as $plan)
            <div wire:key="plan-{{ $plan->id }}" class="bg-card border {{ $plan->is_active ? 'border-border' : 'border-destructive/30 opacity-60' }} rounded-lg overflow-hidden flex flex-col">
                <div class="p-6 flex-1">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-lg font-semibold">{{ $plan->name }}</h3>
                        <x-ui.badge variant="{{ $plan->is_active ? 'success' : 'secondary' }}" size="sm">
                            {{ $plan->is_active ? 'Активен' : 'Скрыт' }}
                        </x-ui.badge>
                    </div>

                    <div class="text-3xl font-bold mb-4">
                        {{ $plan->price }} ₽
                        <span class="text-sm font-normal text-muted-foreground">/ {{ $plan->duration_days }} дн.</span>
                    </div>

                    <div class="space-y-2 text-sm text-muted-foreground">
                        <div class="flex items-center gap-2">
                            <x-lucide-clock class="w-4 h-4" />
                            <span>Длительность: {{ $plan->duration_days }} дней</span>
                        </div>
                        @if($plan->trial_days > 0)
                            <div class="flex items-center gap-2 text-emerald-500">
                                <x-lucide-gift class="w-4 h-4" />
                                <span>Пробный период: {{ $plan->trial_days }} дней</span>
                            </div>
                        @endif
                        <div class="flex items-center gap-2">
                            <x-lucide-tag class="w-4 h-4" />
                            <span>Слаг: {{ $plan->slug }}</span>
                        </div>
                    </div>

                    <!-- Apple/Google ID (для мобилок) -->
                    @if($plan->apple_product_id || $plan->google_product_id)
                        <div class="mt-4 pt-4 border-t border-border space-y-1">
                            @if($plan->apple_product_id) <p class="text-xs text-muted-foreground">Apple: <span class="font-mono">{{ $plan->apple_product_id }}</span></p> @endif
                            @if($plan->google_product_id) <p class="text-xs text-muted-foreground">Google: <span class="font-mono">{{ $plan->google_product_id }}</span></p> @endif
                        </div>
                    @endif
                </div>

                <div class="p-4 border-t border-border bg-muted/20 flex items-center justify-between">
                    <x-ui.button wire:click="toggleActive({{ $plan->id }})" variant="{{ $plan->is_active ? 'outline' : 'success' }}" size="sm">
                        {{ $plan->is_active ? 'Скрыть' : 'Активировать' }}
                    </x-ui.button>

                    <x-ui.button wire:click="openPlanModal({{ $plan->id }})" variant="ghost" size="sm">
                        <x-lucide-edit class="w-4 h-4" />
                    </x-ui.button>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Модалка создания/редактирования -->
    <div x-data="{ show: @entangle('showPlanModal') }" x-show="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" style="display: none;">
        <div class="bg-card border border-border rounded-lg p-6 w-full max-w-md shadow-xl">
            <h3 class="text-lg font-semibold mb-4">{{ $editingPlanId ? 'Редактировать тариф' : 'Новый тариф' }}</h3>
            
            <div class="space-y-3">
                <div>
                    <label class="text-sm font-medium">Название</label>
                    <x-ui.input wire:model="name" placeholder="Например: VIP на 1 месяц" />
                </div>

                <div>
                    <label class="text-sm font-medium">Слаг (URL)</label>
                    <x-ui.input wire:model="slug" placeholder="vip_1_month" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm font-medium">Цена (₽)</label>
                        <x-ui.input wire:model="price" type="number" step="0.01" />
                    </div>
                    <div>
                        <label class="text-sm font-medium">Длительность (дни)</label>
                        <x-ui.input wire:model="duration_days" type="number" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm font-medium">Пробный период (дни)</label>
                        <x-ui.input wire:model="trial_days" type="number" />
                    </div>
                    <div>
                        <label class="text-sm font-medium">Сортировка</label>
                        <x-ui.input wire:model="sort_order" type="number" />
                    </div>
                </div>

                <label class="flex items-center gap-2 mt-2">
                    <input type="checkbox" wire:model="is_active" class="rounded border-gray-300">
                    <span class="text-sm">Тариф активен</span>
                </label>
            </div>

            <div class="flex justify-end gap-2 mt-6">
                <x-ui.button wire:click="$set('showPlanModal', false)">Отмена</x-ui.button>
                <x-ui.button wire:click="save" variant="default">Сохранить</x-ui.button>
            </div>
        </div>
    </div>
</div>