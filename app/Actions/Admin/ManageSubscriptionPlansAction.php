<?php

namespace App\Actions\Admin;

use App\Models\AdminLog;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class ManageSubscriptionPlansAction
{
    public function createPlan(array $data, User $admin): SubscriptionPlan
    {
        $data['currency'] = 'RUB';
        $data['old_price'] = !empty($data['old_price']) ? $data['old_price'] : null;
        $data['apple_product_id'] = $data['apple_product_id'] ?: null;
        $data['google_product_id'] = $data['google_product_id'] ?: null;

        $plan = SubscriptionPlan::create($data);
        
        $after = [
            'status' => 'created', 
            'context' => [
                'plan_id' => $plan->id,
                'name' => $plan->name,
                'tier' => $plan->tier,
                'price' => $plan->price,
                'duration_days' => $plan->duration_days,
                'admin_id' => $admin->id
            ]
        ];

        AdminLog::record('plan.create', $plan, $admin, null, $after);
        $this->clearCache();
        
        return $plan;
    }

    public function updatePlan(SubscriptionPlan $plan, array $data, User $admin): SubscriptionPlan
    {
        $data['old_price'] = !empty($data['old_price']) ? $data['old_price'] : null;
        $data['apple_product_id'] = $data['apple_product_id'] ?: null;
        $data['google_product_id'] = $data['google_product_id'] ?: null;

        $before = [
            'name' => $plan->getOriginal('name'), 
            'price' => $plan->getOriginal('price'), 
            'old_price' => $plan->getOriginal('old_price'), 
            'duration_days' => $plan->getOriginal('duration_days')
        ];
        
        $plan->update($data);
        
        $after = [
            'name' => $plan->name, 
            'price' => $plan->price, 
            'old_price' => $plan->old_price, 
            'duration_days' => $plan->duration_days,
            'context' => [
                'plan_id' => $plan->id,
                'name' => $plan->name,
                'admin_id' => $admin->id
            ]
        ];

        AdminLog::record('plan.update', $plan, $admin, $before, $after);
        $this->clearCache();

        return $plan;
    }

    public function toggleActive(SubscriptionPlan $plan, User $admin): bool
    {
        $before = ['is_active' => $plan->getOriginal('is_active')];
        
        $plan->update(['is_active' => !$plan->is_active]);
        
        $after = [
            'is_active' => $plan->is_active, 
            'context' => [
                'plan_id' => $plan->id,
                'name' => $plan->name,
                'admin_id' => $admin->id
            ]
        ];

        AdminLog::record('plan.toggle_active', $plan, $admin, $before, $after);
        $this->clearCache();

        return $plan->is_active;
    }

    private function clearCache(): void
    {
        Cache::forget('admin_plan_counts');
    }
}