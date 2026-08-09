<?php

namespace Database\Factories;

use App\Models\Photo;
use Illuminate\Database\Eloquent\Factories\Factory;

class PhotoFactory extends Factory
{
    protected $model = Photo::class;

    public function definition(): array
    {
        // Для админки нам не нужны реальные файлы, только пути
        $userId = fake()->numberBetween(1, 50); // Примерно
        $hash = substr(md5($userId), 0, 3);
        $fileId = fake()->uuid();

        return [
            'path_original' => "photos/profile/{$hash}/{$userId}/orig_{$fileId}.webp",
            'path_large' => "photos/profile/{$hash}/{$userId}/large_{$fileId}.webp",
            'path_medium' => "photos/profile/{$hash}/{$userId}/medium_{$fileId}.webp",
            'path_thumb' => "photos/profile/{$hash}/{$userId}/thumb_{$fileId}.webp",
            'status' => fake()->randomElement(['pending', 'approved', 'rejected']),
            'is_primary' => false,
            'type' => 'profile',
            'position' => 0,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'pending']);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'approved']);
    }
}