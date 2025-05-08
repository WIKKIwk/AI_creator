<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Agent;
use App\Models\ProdOrder;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProdOrder>
 */
class ProdOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agent_id' => Agent::query()->first()->id,
            'warehouse_id' => Warehouse::query()->first()->id,
            'product_id' => Product::query()->first()->id,
            'quantity' => 1,
            'offer_price' => 10,
            'status' => OrderStatus::Pending,
        ];
    }
}
