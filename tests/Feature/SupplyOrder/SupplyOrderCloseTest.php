<?php

namespace SupplyOrder;

use App\Enums\OrderStatus;
use App\Enums\ProdOrderStepStatus;
use App\Models\ProdOrder\ProdOrder;
use App\Models\ProdOrder\ProdOrderStep;
use App\Models\ProdOrder\ProdOrderStepProduct;
use App\Services\SupplyOrderService;
use Exception;
use Tests\Feature\Traits\HasProdOrder;
use Tests\Feature\Traits\HasProdTemplate;
use Tests\Feature\Traits\HasSupplyOrder;
use Tests\TestCase;

class SupplyOrderCloseTest extends TestCase
{
    use HasProdTemplate;
    use HasProdOrder;
    use HasSupplyOrder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->user);

        $this->createProdTemplate();
    }

    public function test_validate_already_closed(): void
    {
        $supplyOrder = $this->createSupplyOrder(['closed_at' => now()]);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Supply order is already closed');
        $this->supplyOrderService->closeOrder($supplyOrder);
    }

    public function test_validate_no_supplier(): void
    {
        $supplyOrder = $this->createSupplyOrder(['supplier_organization_id' => null]);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Supplier is not set');
        $this->supplyOrderService->closeOrder($supplyOrder);
    }

    public function test_validate_no_products(): void
    {
        $supplyOrder = $this->createSupplyOrder();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No products in supply order');
        $this->supplyOrderService->closeOrder($supplyOrder);
    }

    public function test_validate_zero_actual_qty(): void
    {
        $supplyOrder = $this->createSupplyOrder();

        $product = $this->createProduct(['name' => 'Test Product']);
        $supplyOrder->products()->create([
            'product_id' => $product->id,
            'expected_quantity' => 10,
            'actual_quantity' => 0, // Zero actual quantity
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Product $product->name has 0 actual quantity");
        $this->supplyOrderService->closeOrder($supplyOrder);
    }

    public function test_close_order(): void
    {
        $product1 = $this->createProduct(['name' => 'Test Product']);
        $product2 = $this->createProduct(['name' => 'Test Product']);

        $supplyOrder = $this->createSupplyOrder();
        $supplyOrder->products()->createMany([
            [
                'product_id' => $product1->id,
                'expected_quantity' => 10,
                'actual_quantity' => 10,
            ],
            [
                'product_id' => $product2->id,
                'expected_quantity' => 5,
                'actual_quantity' => 5,
            ]
        ]);

        $this->supplyOrderService->closeOrder($supplyOrder);

        $inventory1 = $this->getInventory($product1);
        $this->assertDatabaseHas('inventory_items', [
            'inventory_id' => $inventory1->id,
            'quantity' => 10,
        ]);

        $inventory2 = $this->getInventory($product2);
        $this->assertDatabaseHas('inventory_items', [
            'inventory_id' => $inventory2->id,
            'quantity' => 5,
        ]);
    }

    public function test_close_order_with_prod_order(): void
    {
        $product1 = $this->createProduct(['name' => 'Test Product']);
        $sfp1 = $this->createProduct(['name' => 'SFP 1']);
        $sfp2 = $this->createProduct(['name' => 'SFP 2']);

        $product2 = $this->createProduct(['name' => 'Test Product']);

        $prodOrder = ProdOrder::factory()->create([
            'product_id' => $product1->id,
            'status' => OrderStatus::Blocked,
            'quantity' => 10
        ]);

        /** @var ProdOrderStep $firstStep */
        /** @var ProdOrderStepProduct $firstStepMaterial */
        $firstStep = $prodOrder->steps()->create([
            'sequence' => 1,
            'status' => ProdOrderStepStatus::InProgress,
            'work_station_id' => $this->workStationFirst->id,
            'output_product_id' => $sfp1->id,
            'expected_quantity' => 5,
        ]);
        $firstStepMaterial = $firstStep->materials()->create([
            'product_id' => $product1->id,
            'required_quantity' => $reqQty1 = 8,
            'available_quantity' => 0,
        ]);

        /** @var ProdOrderStep $secondStep */
        /** @var ProdOrderStepProduct $secondStepMaterial */
        $secondStep = $prodOrder->steps()->create([
            'sequence' => 2,
            'status' => ProdOrderStepStatus::InProgress,
            'work_station_id' => $this->workStationSecond->id,
            'output_product_id' => $sfp2->id,
            'expected_quantity' => 5,
        ]);
        $secondStepMaterial = $secondStep->materials()->create([
            'product_id' => $product2->id,
            'required_quantity' => $reqQty2 = 3,
            'available_quantity' => 0,
        ]);

        $supplyOrder = $this->createSupplyOrder(['prod_order_id' => $prodOrder->id]);
        $supplyOrder->products()->createMany([
            [
                'product_id' => $product1->id,
                'expected_quantity' => $stockQty1 = 10,
                'actual_quantity' => $stockQty1,
            ],
            [
                'product_id' => $product2->id,
                'expected_quantity' => $stockQty2 = 5,
                'actual_quantity' => $stockQty2,
            ]
        ]);

        $this->supplyOrderService->closeOrder($supplyOrder);

        $this->assertDatabaseHas('prod_order_step_products', [
            'id' => $firstStepMaterial->id,
            'available_quantity' => $reqQty1,
        ]);
        $this->assertDatabaseHas('prod_order_step_products', [
            'id' => $secondStepMaterial->id,
            'available_quantity' => 0,
        ]);

        $inventory1 = $this->getInventory($product1);
        $this->assertDatabaseHas('inventory_items', [
            'inventory_id' => $inventory1->id,
            'quantity' => $stockQty1 - $reqQty1,
        ]);

        $inventory2 = $this->getInventory($product2);
        $this->assertDatabaseHas('inventory_items', [
            'inventory_id' => $inventory2->id,
            'quantity' => $stockQty2,
        ]);
    }
}
