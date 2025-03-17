<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Category;
use Laravel\Sanctum\Sanctum;

class CategoryTest extends TestCase
{
    public function test_store(): void
    {
        Sanctum::actingAs($this->user);
        $response = $this->postJson(route('category.store'), $payload = $this->getPayload());
        $response->assertOk();
        $response->assertJson(['message' => 'success']);

        $this->checkDatabase($payload);
    }

    public function test_store_with_parent(): void
    {
        /** @var Category $parent */
        $parent = $this->user->categories()->create(['name' => 'Parent category']);

        Sanctum::actingAs($this->user);
        $response = $this->postJson(
            route('category.store'),
            $payload = array_merge(
                $this->getPayload(),
                ['parent_id' => $parent->id]
            )
        );
        $response->assertOk();
        $response->assertJson(['message' => 'success']);

        $this->checkDatabase($payload);
    }

    public function test_store_with_wrong_parent(): void
    {
        $anotherUser = $this->createUser();
        /** @var Category $wrongParent */
        $wrongParent = $anotherUser->categories()->create(['name' => 'Parent category']);

        Sanctum::actingAs($this->user);
        $response = $this->postJson(
            route('category.store'),
            $payload = array_merge($this->getPayload(), [
                'parent_id' => $wrongParent->id
            ])
        );
        $response->assertJsonValidationErrorFor('parent_id');

        $this->assertDatabaseMissing('categories', ['name' => $payload['name']]);
    }

    public function test_delete(): void
    {
        /** @var Category $category */
        $category = $this->user->categories()->create(['name' => 'Test category']);

        Sanctum::actingAs($this->user);
        $response = $this->deleteJson(route('category.delete', ['id' => $category->id]));
        $response->assertOk();
        $response->assertJson(['message' => 'deleted']);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_delete_another_user_category(): void
    {
        $anotherUser = $this->createUser();
        /** @var Category $category */
        $category = $anotherUser->categories()->create(['name' => 'Test category']);

        Sanctum::actingAs($this->user);
        $response = $this->deleteJson(route('category.delete', ['id' => $category->id]));
        $response->assertStatus(400);
        $this->assertMessage('Category not found', $response);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    protected function getPayload(): array
    {
        return [
            'name' => 'Test category',
            'description' => 'Test description',
            'is_favorite' => true,
            'parent_id' => null,
        ];
    }

    protected function checkDatabase(array $payload): void
    {
        $this->assertDatabaseHas('categories', [
            'name' => $payload['name'],
            'description' => $payload['description'],
            'is_favorite' => $payload['is_favorite'],
            'parent_id' => $payload['parent_id'],
        ]);
    }
}
