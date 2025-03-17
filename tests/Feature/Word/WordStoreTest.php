<?php

namespace Tests\Feature\Word;

use Tests\TestCase;
use App\Models\Word;
use App\Models\Category;
use Laravel\Sanctum\Sanctum;

class WordStoreTest extends TestCase
{
    public function test_store_full(): void
    {
        /** @var Category $category */
        $category = $this->user->categories()->create(['name' => 'Test category']);

        Sanctum::actingAs($this->user);
        $response = $this->postJson(route('word.store'), $payload = $this->getPayload([
            'category_id' => $category->id,
        ]));
        $response->assertOk();
        $response->assertJson(['message' => 'success']);

        $this->checkDatabase($payload);
    }

    public function test_store_with_wrong_category_fail(): void
    {
        $anotherUser = $this->createUser();
        /** @var Category $category */
        $category = $anotherUser->categories()->create(['name' => 'Test category']);

        Sanctum::actingAs($this->user);
        $response = $this->postJson(route('word.store'), $this->getPayload([
            'category_id' => $category->id,
        ]));
        $response->assertJsonValidationErrorFor('category_id');
    }

    public function test_delete(): void
    {
        /** @var Word $word */
        $word = $this->user->words()->create(['word' => 'Test word']);

        Sanctum::actingAs($this->user);
        $response = $this->deleteJson(route('word.delete', ['id' => $word->id]));
        $response->assertOk();
        $response->assertJson(['message' => 'deleted']);

        $this->assertDatabaseMissing('words', ['id' => $word->id]);
    }

    public function test_delete_another_user_category(): void
    {
        $anotherUser = $this->createUser();
        /** @var Word $word */
        $word = $anotherUser->words()->create(['word' => 'Test word']);

        Sanctum::actingAs($this->user);
        $response = $this->deleteJson(route('word.delete', ['id' => $word->id]));
        $this->assertMessage('Word not found', $response);

        $this->assertDatabaseHas('words', ['id' => $word->id]);
    }

    protected function checkDatabase(array $payload): void
    {
        $this->assertDatabaseHas('words', [
            'word' => $payload['word'],
            'meaning' => $payload['meaning'],
            'synonyms' => $payload['synonyms'],
            'antonyms' => $payload['antonyms'],
            'is_favorite' => $payload['is_favorite'],
            'category_id' => $payload['category_id'],
        ]);
    }

    protected function getPayload(array $overrides = []): array
    {
        return array_merge([
            'word' => 'Test word',
            'meaning' => 'Test meaning',
            'synonyms' => 'Test synonyms',
            'antonyms' => 'Test antonyms',
            'is_favorite' => true
        ], $overrides);
    }
}
