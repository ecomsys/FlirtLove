<?php
namespace Database\Factories;

use App\Models\Gift;
use Illuminate\Database\Eloquent\Factories\Factory;

class GiftFactory extends Factory
{
    protected $model = Gift::class;
    public function definition(): array
    {
        return [
            'name' => fake()->word() . ' подарок',
            'slug' => fake()->unique()->slug(),
            'image_url' => 'gifts/' . fake()->word() . '.png',
            'price' => fake()->randomElement([50, 100, 150, 300]),
            'category' => fake()->randomElement(['romantic', 'fun', 'male', 'female']),
            'is_active' => true,
        ];
    }
}
