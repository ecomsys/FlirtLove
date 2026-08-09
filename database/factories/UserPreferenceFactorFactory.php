<?php

namespace Database\Factories;

use App\Models\UserPreference;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserPreferenceFactory extends Factory
{
    protected $model = UserPreference::class;

    public function definition(): array
    {
        return [
            'locale' => 'ru',
            'theme' => 'light',
            'preferred_age_min' => 18,
            'preferred_age_max' => fake()->numberBetween(30, 60),
            'preferred_gender' => fake()->randomElement(['any', 'male', 'female']),
            'preferred_distance_km' => fake()->numberBetween(10, 100),
            'push_enabled' => true,
            'email_enabled' => true,
            'superlikes_remaining' => 5,
            'credits' => fake()->numberBetween(0, 100),
        ];
    }
}