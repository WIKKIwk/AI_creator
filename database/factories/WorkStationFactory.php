<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\ProductCategory;
use App\Models\WorkStation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkStation>
 */
class WorkStationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'organization_id' => Organization::query()->first()->id,
        ];
    }
}
