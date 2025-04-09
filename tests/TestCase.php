<?php

namespace Tests;

use App\Models\Agent;
use App\Models\Organization;
use App\Models\ProdOrder;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Testing\TestResponse;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use LazilyRefreshDatabase;
    use CreatesApplication;

    protected User $user;
    protected Organization $organization;
    protected Warehouse $warehouse;
    protected ProductCategory $productCategory;
    protected Agent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = Agent::query()->create(['name' => 'test_agent']);
        $this->organization = Organization::query()->create(['name' => 'test_organization']);
        $this->productCategory = ProductCategory::factory()->create(['name' => 'test_category']);

        $this->user = User::factory()->create(['name' => 'test_user']);
        $this->warehouse = Warehouse::factory()->create(['name' => 'test_warehouse']);
    }

    protected function createProduct(array $data = []): Product
    {
        return Product::factory()->create(array_merge([
            'category_id' => $this->productCategory->id,
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
