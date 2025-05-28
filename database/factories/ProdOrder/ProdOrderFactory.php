<?php

namespace Database\Factories\ProdOrder;

use App\Enums\OrderStatus;
use App\Models\ProdOrder\ProdOrder;
use App\Models\ProdOrder\ProdOrderGroup;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProdOrder>
 */
class ProdOrderFactory extends Factory
{
    public function definition(): array
    {
        $group = ProdOrderGroup::factory()->create();

        return [
            'group_id' => $group->id,
            'product_id' => Product::factory()->create()->id,
            'quantity' => 1,
            'offer_price' => 10,
            'status' => OrderStatus::Pending,
        ];
    }
}
