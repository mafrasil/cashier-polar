<?php

namespace Mafrasil\CashierPolar\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Mafrasil\CashierPolar\Models\PolarCustomer;

class PolarCustomerFactory extends Factory
{
    protected $model = PolarCustomer::class;

    public function definition(): array
    {
        return [
            'polar_id' => $this->faker->uuid,
            'name' => $this->faker->name,
            'email' => $this->faker->email,
            'trial_ends_at' => $this->faker->optional()->dateTime,
        ];
    }
}
