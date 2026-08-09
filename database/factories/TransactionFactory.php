<?php

namespace Database\Factories;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;
    public function definition(): array
    {
        return [
            'amount' => fake()->randomElement([999.00, 1999.00, 149.00]),
            'currency' => 'RUB',
            'type' => 'subscription',
            'status' => fake()->randomElement(['success', 'success', 'success', 'pending', 'failed']),
            'provider' => 'yookassa',
            'provider_transaction_id' => fake()->unique()->uuid(),
        ];
    }
}