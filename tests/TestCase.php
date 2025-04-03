<?php

namespace Tests;

use App\Models\Organization;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::query()->create(['name' => 'test_organization']);
        $this->productCategory = ProductCategory::factory()->create(['name' => 'test_category']);

        $this->user = User::factory()->create(['name' => 'test_user']);
        $this->warehouse = Warehouse::factory()->create(['name' => 'test_warehouse']);
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
