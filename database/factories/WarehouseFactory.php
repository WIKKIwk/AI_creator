<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'organization_id' => Organization::query()->first()->id,
        ];
    }
}
