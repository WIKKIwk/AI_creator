<?php

namespace ProdOrder;

use App\Enums\OrderStatus;
use App\Enums\ProdOrderGroupType;
use App\Enums\ProdOrderStepProductStatus;
use App\Enums\ProdOrderStepStatus;
use App\Enums\SupplyOrderState;
use App\Models\ProdOrder\ProdOrder;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ProdOrderService;
use App\Services\TransactionService;
use App\Services\WorkStationService;
use Exception;
use Tests\Feature\Traits\HasProdOrder;
use Tests\Feature\Traits\HasProdTemplate;
use Tests\TestCase;

class ProdOrderCreateTest extends TestCase
{
    protected ProdOrder $prodOrder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->user);
    }

    public function test_create_by_form(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        $formData = [
            'type' => ProdOrderGroupType::ByOrder->value,
            'warehouse_id' => $this->warehouse->id,
            'agent_id' => $this->agent->id,
//            'deadline' => now()->addDays(7)->format('Y-m-d'),
            'products' => [
                [
                    'product_id' => $product1->id,
                    'quantity' => 10,
                    'offer_price' => 100,
                ],
                [
                    'product_id' => $product2->id,
                    'quantity' => 5,
                    'offer_price' => 50,
                ],
            ],
        ];

        $poGroup = $this->prodOrderService->createOrderByForm($formData);

        $this->assertDatabaseHas('prod_order_groups', [
            'id' => $poGroup->id,
            'type' => ProdOrderGroupType::ByOrder->value,
            'warehouse_id' => $this->warehouse->id,
            'agent_id' => $this->agent->id,
        ]);

        $this->assertDatabaseHas('prod_orders', [
            'number' => 'PO-' . $this->agent->partner->code . $product1->code . now()->format('dmy'),
            'group_id' => $poGroup->id,
            'status' => OrderStatus::Pending->value,
            'product_id' => $product1->id,
            'quantity' => 10,
            'offer_price' => 100,
        ]);
        $this->assertDatabaseHas('prod_orders', [
            'number' => 'PO-' . $this->agent->partner->code . $product2->code . now()->format('dmy'),
            'group_id' => $poGroup->id,
            'status' => OrderStatus::Pending->value,
            'product_id' => $product2->id,
            'quantity' => 5,
            'offer_price' => 50,
        ]);
    }
}
