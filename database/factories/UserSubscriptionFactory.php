<?php

namespace Database\Factories;

use App\Models\UserSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserSubscriptionFactory extends Factory
{
    protected $model = UserSubscription::class;
    public function definition(): array
    {
        return [
            'starts_at' => now()->subDays(fake()->numberBetween(1, 20)),
            'ends_at' => now()->addDays(fake()->numberBetween(5, 30)),
            'is_auto_renew' => true,
            'status' => 'active',
            'provider_subscription_id' => fake()->unique()->uuid(),
        ];
    }
}