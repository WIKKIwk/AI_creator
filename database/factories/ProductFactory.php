<?php

namespace Database\Factories;

use App\Enums\MeasureUnit;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => ProductType::RawMaterial,
            'name' => $this->faker->word(),
            'price' => $this->faker->randomFloat(2, 1, 100),
            'measure_unit' => array_rand(MeasureUnit::cases(), 1),
            'product_category_id' => ProductCategory::query()->first()->id,
        ];
    }
}
