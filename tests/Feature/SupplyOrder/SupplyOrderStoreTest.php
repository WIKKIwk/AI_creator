<?php

namespace Tests\Feature\SupplyOrder;

use App\Enums\PartnerType;
use App\Enums\SupplyOrderState;
use App\Models\OrganizationPartner;
use App\Models\ProdOrder\ProdOrder;
use App\Models\ProductCategory;
use App\Models\SupplyOrder\SupplyOrder;
use Tests\TestCase;

class SupplyOrderStoreTest extends TestCase
{
    protected OrganizationPartner $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->user);
    }

    public function test_number_generation(): void
    {
        $cat = ProductCategory::factory()->create([
            'name' => 'Test Category',
            'code' => 'TEST-CAT',
            'organization_id' => $this->organization->id,
        ]);

        $supplyOrder = SupplyOrder::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'product_category_id' => $cat->id,
            'created_by' => $this->user->id,
        ]);

        $supplierCode = $this->supplier->partner->code;

        $this->assertEquals("SO-{$supplierCode}TEST-CAT" . now()->format('dmy'), $supplyOrder->number);
    }

    public function test_store_by_form(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        $formData = [
            'warehouse_id' => $this->warehouse->id,
            'product_category_id' => $this->productCategory->id,
            'supplier_id' => $this->supplier->id,
            'products' => [
                [
                    'product_id' => $product1->id,
                    'expected_quantity' => 10,
                    'price' => 100,
                ],
                [
                    'product_id' => $product2->id,
                    'expected_quantity' => 5,
                    'price' => 50,
                ],
            ],
        ];

        $supplyOrder = $this->supplyOrderService->createOrderByForm($formData);

        $this->assertDatabaseHas('supply_orders', [
            'id' => $supplyOrder->id,
            'number' => 'SO-' . $this->supplier->partner->code . $this->productCategory->code . now()->format('dmy'),
            'warehouse_id' => $this->warehouse->id,
            'product_category_id' => $this->productCategory->id,
            'supplier_id' => $this->supplier->id,
        ]);

        $this->assertDatabaseHas('supply_order_products', [
            'supply_order_id' => $supplyOrder->id,
            'product_id' => $product1->id,
            'expected_quantity' => 10,
            'price' => 100,
        ]);
        $this->assertDatabaseHas('supply_order_products', [
            'supply_order_id' => $supplyOrder->id,
            'product_id' => $product2->id,
            'expected_quantity' => 5,
            'price' => 50,
        ]);
    }

    public function test_store_for_prod_order(): void
    {
        $cat1 = ProductCategory::factory()->create([
            'name' => 'Category 1',
            'organization_id' => $this->organization->id
        ]);
        $product1 = $this->createProduct(['name' => 'Product 1', 'product_category_id' => $cat1->id]);
        $product2 = $this->createProduct(['name' => 'Product 2', 'product_category_id' => $cat1->id]);

        $cat2 = ProductCategory::factory()->create([
            'name' => 'Category 2',
            'organization_id' => $this->organization->id
        ]);
        $product3 = $this->createProduct(['name' => 'Product 3', 'product_category_id' => $cat2->id]);
        $product4 = $this->createProduct(['name' => 'Product 4', 'product_category_id' => $cat2->id]);

        $insufficientAssetsByCat = [
            $cat1->id => [
                $product1->id => ['quantity' => $prod1Qty = 5],
                $product2->id => ['quantity' => $prod2Qty = 4]
            ],
            $cat2->id => [
                $product3->id => ['quantity' => $prod3Qty = 3],
                $product4->id => ['quantity' => $prod4Qty = 2]
            ],
        ];

        $prodOrder = ProdOrder::factory()->create([
            'product_id' => $product1->id,
            'quantity' => 3,
            'offer_price' => 100,

            // Confirmed
            'confirmed_at' => now(),
            'confirmed_by' => $this->user->id,
        ]);

        $supplyOrders = $this->supplyOrderService->storeForProdOrder($prodOrder, $insufficientAssetsByCat);

        $supplyOrderFirst = $supplyOrders->first();
        $this->assertDatabaseHas('supply_orders', [
            'id' => $supplyOrderFirst->id,
            'prod_order_id' => $prodOrder->id,
            'warehouse_id' => $prodOrder->getWarehouseId(),
            'product_category_id' => $cat1->id,
            'state' => SupplyOrderState::Created,
            'status' => null,
            'created_by' => $this->user->id,
        ]);
        $this->assertDatabaseHas('supply_order_steps', [
            'supply_order_id' => $supplyOrderFirst->id,
            'state' => SupplyOrderState::Created->value,
            'status' => null,
        ]);
        $this->assertDatabaseHas('supply_order_products', [
            'supply_order_id' => $supplyOrderFirst->id,
            'product_id' => $product1->id,
            'expected_quantity' => $prod1Qty,
            'actual_quantity' => 0,
        ]);
        $this->assertDatabaseHas('supply_order_products', [
            'supply_order_id' => $supplyOrderFirst->id,
            'product_id' => $product2->id,
            'expected_quantity' => $prod2Qty,
            'actual_quantity' => 0,
        ]);

        $supplyOrderSecond = $supplyOrders->last();
        $this->assertDatabaseHas('supply_orders', [
            'id' => $supplyOrderSecond->id,
            'prod_order_id' => $prodOrder->id,
            'warehouse_id' => $prodOrder->getWarehouseId(),
            'product_category_id' => $cat2->id,
            'state' => SupplyOrderState::Created,
            'status' => null,
            'created_by' => $this->user->id,
        ]);
        $this->assertDatabaseHas('supply_order_steps', [
            'supply_order_id' => $supplyOrderSecond->id,
            'state' => SupplyOrderState::Created->value,
            'status' => null,
        ]);
        $this->assertDatabaseHas('supply_order_products', [
            'supply_order_id' => $supplyOrderSecond->id,
            'product_id' => $product3->id,
            'expected_quantity' => $prod3Qty,
            'actual_quantity' => 0,
        ]);
        $this->assertDatabaseHas('supply_order_products', [
            'supply_order_id' => $supplyOrderSecond->id,
            'product_id' => $product4->id,
            'expected_quantity' => $prod4Qty,
            'actual_quantity' => 0,
        ]);
    }

    public function test_store_for_prod_order_existed(): void
    {
        $cat1 = ProductCategory::factory()->create([
            'name' => 'Category 1',
            'organization_id' => $this->organization->id
        ]);
        $product1 = $this->createProduct(['name' => 'Product 1', 'product_category_id' => $cat1->id]);
        $product2 = $this->createProduct(['name' => 'Product 2', 'product_category_id' => $cat1->id]);

        $cat2 = ProductCategory::factory()->create([
            'name' => 'Category 2',
            'organization_id' => $this->organization->id
        ]);
        $product3 = $this->createProduct(['name' => 'Product 3', 'product_category_id' => $cat2->id]);
        $product4 = $this->createProduct(['name' => 'Product 4', 'product_category_id' => $cat2->id]);

        $prodOrder = ProdOrder::factory()->create([
            'product_id' => $product1->id,
            'quantity' => 3,
            'offer_price' => 100,

            // Confirmed
            'confirmed_at' => now(),
            'confirmed_by' => $this->user->id,
        ]);

        $supplyOrderExisted = SupplyOrder::factory()->create([
            'prod_order_id' => $prodOrder->id,
            'warehouse_id' => $this->warehouse->id,
            'product_category_id' => $cat1->id,
            'created_by' => $this->user->id,
            'state' => SupplyOrderState::Created,
        ]);

        $insufficientAssetsByCat = [
            $cat1->id => [
                $product1->id => ['quantity' => $prod1Qty = 5],
                $product2->id => ['quantity' => $prod2Qty = 4]
            ],
            $cat2->id => [
                $product3->id => ['quantity' => $prod3Qty = 3],
                $product4->id => ['quantity' => $prod4Qty = 2]
            ],
        ];

        $supplyOrders = $this->supplyOrderService->storeForProdOrder($prodOrder, $insufficientAssetsByCat);
        $this->assertCount(1, $supplyOrders);

        $supplyOrderNew = $supplyOrders->first();
        $this->assertDatabaseHas('supply_orders', [
            'id' => $supplyOrderNew->id,
            'prod_order_id' => $prodOrder->id,
            'warehouse_id' => $prodOrder->getWarehouseId(),
            'product_category_id' => $cat2->id,
            'state' => SupplyOrderState::Created,
            'status' => null,
            'created_by' => $this->user->id,
        ]);
        $this->assertDatabaseHas('supply_order_steps', [
            'supply_order_id' => $supplyOrderNew->id,
            'state' => SupplyOrderState::Created->value,
            'status' => null,
        ]);
        $this->assertDatabaseHas('supply_order_products', [
            'supply_order_id' => $supplyOrderNew->id,
            'product_id' => $product3->id,
            'expected_quantity' => $prod3Qty,
            'actual_quantity' => 0,
        ]);
        $this->assertDatabaseHas('supply_order_products', [
            'supply_order_id' => $supplyOrderNew->id,
            'product_id' => $product4->id,
            'expected_quantity' => $prod4Qty,
            'actual_quantity' => 0,
        ]);
    }
}
