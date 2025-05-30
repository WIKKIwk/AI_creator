<?php

namespace SupplyOrder;

use App\Enums\OrderStatus;
use App\Enums\ProdOrderStepStatus;
use App\Enums\RoleType;
use App\Enums\SupplyOrderState;
use App\Enums\SupplyOrderStatus;
use App\Enums\TaskAction;
use App\Models\ProdOrder\ProdOrder;
use App\Models\ProdOrder\ProdOrderStep;
use App\Models\ProdOrder\ProdOrderStepProduct;
use App\Models\Product;
use App\Models\SupplyOrder\SupplyOrder;
use App\Services\SupplyOrderService;
use Tests\Feature\Traits\HasProdOrder;
use Tests\Feature\Traits\HasProdTemplate;
use Tests\Feature\Traits\HasSupplyOrder;
use Tests\TestCase;

class SupplyOrderCompareTest extends TestCase
{
    use HasProdTemplate;
    use HasProdOrder;
    use HasSupplyOrder;

    protected Product $product1;
    protected Product $product2;
    protected ProdOrder $prodOrder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->user);

        $this->createProdTemplate();

        $this->product1 = $this->createProduct(['name' => 'Test Product']);
        $sfp1 = $this->createProduct(['name' => 'SFP 1']);
        $sfp2 = $this->createProduct(['name' => 'SFP 2']);

        $this->product2 = $this->createProduct(['name' => 'Test Product']);

        $this->prodOrder = ProdOrder::factory()->create([
            'product_id' => $this->product1->id,
            'status' => OrderStatus::Blocked,
            'quantity' => 10
        ]);

        /** @var ProdOrderStep $firstStep */
        /** @var ProdOrderStepProduct $firstStepMaterial */
        $firstStep = $this->prodOrder->steps()->create([
            'sequence' => 1,
            'status' => ProdOrderStepStatus::InProgress,
            'work_station_id' => $this->workStationFirst->id,
            'output_product_id' => $sfp1->id,
            'expected_quantity' => 5,
        ]);
        $firstStep->materials()->create([
            'product_id' => $this->product1->id,
            'required_quantity' => 8,
            'available_quantity' => 0,
        ]);

        /** @var ProdOrderStep $secondStep */
        /** @var ProdOrderStepProduct $secondStepMaterial */
        $secondStep = $this->prodOrder->steps()->create([
            'sequence' => 2,
            'status' => ProdOrderStepStatus::InProgress,
            'work_station_id' => $this->workStationSecond->id,
            'output_product_id' => $sfp2->id,
            'expected_quantity' => 5,
        ]);
        $secondStep->materials()->create([
            'product_id' => $this->product2->id,
            'required_quantity' => 3,
            'available_quantity' => 0,
        ]);
    }

    public function test_compare(): void
    {
        $supplyOrder = $this->createSupplyOrder(['prod_order_id' => $this->prodOrder->id]);
        $supplyOrder->products()->createMany([
            [
                'product_id' => $this->product1->id,
                'expected_quantity' => $stockQty1 = 10,
                'actual_quantity' => 0,
            ],
            [
                'product_id' => $this->product2->id,
                'expected_quantity' => $stockQty2 = 5,
                'actual_quantity' => 0,
            ]
        ]);

        $this->supplyOrderService->compareProducts($supplyOrder, [
            [
                'product_id' => $this->product1->id,
                'actual_quantity' => $stockQty1,
            ],
            [
                'product_id' => $this->product2->id,
                'actual_quantity' => $stockQty2,
            ]
        ]);

        $inventory1 = $this->getInventory($this->product1);
        $this->assertDatabaseHas('inventory_items', [
            'inventory_id' => $inventory1->id,
            'quantity' => $stockQty1 - 8,
        ]);

        $inventory2 = $this->getInventory($this->product2);
        $this->assertDatabaseHas('inventory_items', [
            'inventory_id' => $inventory2->id,
            'quantity' => 5,
        ]);
    }

    public function test_compare_not_proper(): void
    {
        $supplyOrder = $this->createSupplyOrder(['prod_order_id' => $this->prodOrder->id]);
        $supplyOrder->products()->createMany([
            [
                'product_id' => $this->product1->id,
                'expected_quantity' => $stockQty1 = 10,
                'actual_quantity' => 0,
            ],
            [
                'product_id' => $this->product2->id,
                'expected_quantity' => $stockQty2 = 5,
                'actual_quantity' => 0,
            ]
        ]);

        $this->supplyOrderService->compareProducts($supplyOrder, [
            [
                'product_id' => $this->product1->id,
                'actual_quantity' => $stockQty1 - 1,
            ],
            [
                'product_id' => $this->product2->id,
                'actual_quantity' => $stockQty2 - 1,
            ]
        ]);

        $this->assertDatabaseHas('supply_orders', [
            'id' => $supplyOrder->id,
            'state' => SupplyOrderState::Delivered,
            'status' => SupplyOrderStatus::AwaitingSupplierApproval->value
        ]);
        $this->assertDatabaseHas('tasks', [
            'from_user_id' => $this->user->id,
            'to_user_roles' => json_encode([RoleType::SUPPLY_MANAGER->value]),
            'related_type' => SupplyOrder::class,
            'related_id' => $supplyOrder->id,
            'action' => TaskAction::Check->value,
            'comment' => 'Supply order compared. There are some differences in quantities.',
        ]);

        $inventory1 = $this->getInventory($this->product1);
        $this->assertNull($inventory1);

        $inventory2 = $this->getInventory($this->product2);
        $this->assertNull($inventory2);
    }
}
