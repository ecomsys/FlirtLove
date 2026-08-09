<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        return [
            'name' => 'VIP на 1 месяц',
            'slug' => 'vip-1-month',
            'price' => 999.00,
            'currency' => 'RUB',
            'duration_days' => 30,
            'trial_days' => 0,
            'features' => [
                'invisible' => true,
                'likes_per_day' => 100,
                'superlikes_per_day' => 5,
                'hide_ads' => true
            ],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}