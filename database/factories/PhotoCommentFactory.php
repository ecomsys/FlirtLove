<?php
namespace Database\Factories;

use App\Models\PhotoComment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PhotoCommentFactory extends Factory
{
    protected $model = PhotoComment::class;

    public function definition(): array
    {
        return [
            'content' => fake()->realTextBetween(20, 100),
            'status' => fake()->randomElement(['pending', 'approved', 'approved', 'approved']),
        ];
    }
}