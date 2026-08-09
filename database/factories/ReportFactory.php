<?php

namespace Database\Factories;

use App\Models\Report;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        return [
            'reason' => fake()->randomElement(['spam', 'scam', 'porn', 'insult', 'minor']),
            'description' => fake()->sentence(),
            'status' => fake()->randomElement(['pending', 'pending', 'resolved', 'rejected']),
        ];
    }
}