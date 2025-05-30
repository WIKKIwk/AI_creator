<?php

namespace Tests;

use App\Models\Organization;
use App\Services\ProdOrderService;
use App\Services\InventoryService;
use App\Models\ProdOrder\ProdOrder;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\WorkStationService;
use App\Services\TransactionService;
use App\Services\SupplyOrderService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    use LazilyRefreshDatabase;
    use CreatesApplication;

    protected User $user;
    protected Organization $organization;
    protected Organization $organization2;
    protected Organization $organization3;
    protected Warehouse $warehouse;
    protected ProductCategory $productCategory;

    protected ProdOrderService $prodOrderService;
    protected WorkStationService $workStationService;
    protected TransactionService $transactionService;
    protected InventoryService $inventoryService;
    protected SupplyOrderService $supplyOrderService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prodOrderService = app(ProdOrderService::class);
        $this->workStationService = app(WorkStationService::class);
        $this->transactionService = app(TransactionService::class);
        $this->inventoryService = app(InventoryService::class);
        $this->supplyOrderService = app(supplyOrderService::class);

        $this->organization = Organization::factory()->create(['name' => 'test_organization', 'code' => 'ORG']);
        $this->organization2 = Organization::factory()->create(['name' => 'test_organization_2']);
        $this->organization3 = Organization::factory()->create(['name' => 'test_organization_3']);

        $this->productCategory = ProductCategory::factory()->create([
            'name' => 'test_category',
            'organization_id' => $this->organization->id
        ]);
        $this->user = User::factory()->create([
            'name' => 'test_user',
            'organization_id' => $this->organization->id
        ]);
        $this->warehouse = Warehouse::factory()->create([
            'name' => 'test_warehouse',
            'organization_id' => $this->organization->id
        ]);
    }

    protected function createProduct(array $data = []): Product
    {
        return Product::factory()->create(array_merge([
            'product_category_id' => $this->productCategory->id,
        ], $data));
    }

    protected function createProdOrder(array $data = []): ProdOrder
    {
        /** @var ProdOrder $prodOrder */
        $prodOrder = ProdOrder::query()->create(array_merge([
            'agent_id' => $this->agent->id,
            'warehouse_id' => $this->warehouse->id,
        ], $data));

        return $prodOrder;
    }

    protected function createUser(array $data = []): User
    {
        return User::factory()->create($data);
    }

    protected function assertMessage(string $message, TestResponse $response): void
    {
        $this->assertEquals($message, $response->json('message'));
    }
}
