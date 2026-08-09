<?php
namespace Database\Factories;

use App\Models\FraudAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

class FraudAlertFactory extends Factory
{
    protected $model = FraudAlert::class;
    public function definition(): array
    {
        return [
            'trigger_type' => fake()->randomElement(['same_device', 'mass_messaging', 'links_in_chat', 'prostitute']),
            'severity' => fake()->randomElement(['low', 'medium', 'high']),
            'meta' => ['ip' => fake()->ipv4(), 'reason' => 'Test fraud trigger'],
            'status' => 'open',
        ];
    }
}