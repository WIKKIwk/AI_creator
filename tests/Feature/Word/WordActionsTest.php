<?php

namespace Tests\Feature\Word;

use Tests\TestCase;
use App\Models\Word;
use App\Models\Category;
use App\Enums\LearnStatus;
use Laravel\Sanctum\Sanctum;

class WordActionsTest extends TestCase
{
    public function test_update_word(): void
    {
        $word = $this->user->words()->create([
            'word' => 'test',
            'meaning' => 'test',
            'synonyms' => 'test',
            'antonyms' => 'test',
            'is_favorite' => true,
        ]);

        Sanctum::actingAs($this->user);
        $response = $this->putJson(route('word.update', ['id' => $word->id]), $payload = [
            'word' => 'test2',
            'meaning' => 'test2',
            'synonyms' => 'test2',
            'antonyms' => 'test2',
            'is_favorite' => false,
        ]);

        $response->assertOk();
        $response->assertJson(['message' => 'success']);
        $this->assertDatabaseHas('words', [
            'id' => $word->id,
            ...$payload,
        ]);
    }

    public function test_update_another_user_word_fail(): void
    {
        $anotherUser = $this->createUser();
        $word = $anotherUser->words()->create(['word' => 'test']);

        Sanctum::actingAs($this->user);
        $response = $this->putJson(route('word.update', ['id' => $word->id]), ['word' => 'test2']);

        $response->assertStatus(400);
        $this->assertMessage('Word not found', $response);
    }

    public function test_move(): void
    {
        $category = Category::factory()->create();
        $anotherCategory = Category::factory()->create();

        /** @var Word $word */
        $word = $this->user->words()->create([
            'word' => 'test',
            'category_id' => $category->id,
        ]);

        Sanctum::actingAs($this->user);
        $response = $this->postJson(route('word.move', $word->id), [
            'category_id' => $anotherCategory->id
        ]);
        $response->assertOk();

        $this->assertDatabaseHas('words', [
            'id' => $word->id,
            'category_id' => $anotherCategory->id,
        ]);
    }

    public function test_progress(): void
    {
        /** @var Word $word */
        $word = $this->user->words()->create([
            'word' => 'test',
            'learn_status' => LearnStatus::Hard,
        ]);

        Sanctum::actingAs($this->user);
        $response = $this->postJson(route('word.progress', $word->id), [
            'learn_status' => LearnStatus::Normal->value
        ]);
        $response->assertOk();

        $this->assertDatabaseHas('words', [
            'id' => $word->id,
            'learn_status' => LearnStatus::Normal->value,
        ]);
    }
}
