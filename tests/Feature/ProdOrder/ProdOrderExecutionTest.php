<?php

namespace ProdOrder;

use Tests\TestCase;
use App\Enums\OrderStatus;
use App\Models\ProdOrder\ProdOrder;
use Tests\Feature\Traits\HasProdTemplate;

class ProdOrderExecutionTest extends TestCase
{
    use HasProdTemplate;

    protected ProdOrder $prodOrder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->user);

        $this->createProdTemplate();

        /** @var ProdOrder $prodOrder */
        $prodOrder = ProdOrder::factory()->create([
            'product_id' => $this->readyProduct->id,
            'quantity' => 3,
            'offer_price' => 100,
            'status' => OrderStatus::Pending
        ]);
        $this->prodOrder = $prodOrder;
    }

    public function test_create_execution(): void
    {
        //
    }
}
