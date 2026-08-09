<?php

namespace Database\Factories;

use App\Models\UserGift;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserGiftFactory extends Factory
{
    protected $model = UserGift::class;
    public function definition(): array
    {
        return [
            'message' => fake()->boolean(50) ? fake()->sentence() : null,
            'is_private' => false,
            'is_read' => fake()->boolean(80),
        ];
    }
}