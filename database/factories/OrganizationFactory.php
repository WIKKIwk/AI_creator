<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'code' => fake()->word(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->email(),
        ];
    }
}
