<?php
namespace Database\Factories;

use App\Models\UserMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserMatchFactory extends Factory
{
    protected $model = UserMatch::class;

    public function definition(): array
    {
        return [
            'status' => 'active',
        ];
    }
}