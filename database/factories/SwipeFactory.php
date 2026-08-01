<?php
namespace Database\Factories;

use App\Models\Swipe;
use Illuminate\Database\Eloquent\Factories\Factory;

class SwipeFactory extends Factory
{
    protected $model = Swipe::class;

    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['like', 'like', 'like', 'dislike', 'superlike']),
        ];
    }
}