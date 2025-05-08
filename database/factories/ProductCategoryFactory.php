<?php

namespace Database\Factories;

use App\Enums\MeasureUnit;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'measure_unit' => MeasureUnit::LITER,
        ];
    }
}
