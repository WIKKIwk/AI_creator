<?php

namespace Tests\Feature\Word;

use Tests\TestCase;
use App\Models\Word;
use App\Models\Category;
use App\Enums\LearnStatus;
use Laravel\Sanctum\Sanctum;

class WordListTest extends TestCase
{
    public function test_list(): void
    {
        Word::factory()->count(5)->create();

        Sanctum::actingAs($this->user);
        $response = $this->getJson(route('word.getWords'));
        $response->assertOk();
        $response->assertJson(['message' => 'success']);

        $this->assertCount(5, $response->json('data'));
    }

    public function test_dictionary_list(): void
    {
        Word::factory()->count(5)->create(['learn_status' => LearnStatus::Normal]);
        Word::factory()->count(7)->create(['learn_status' => LearnStatus::Learned]);

        Sanctum::actingAs($this->user);
        $response = $this->getJson(route('word.getDictionaryWords'));
        $response->assertOk();
        $response->assertJson(['message' => 'success']);

        $this->assertCount(7, $response->json('data'));
    }

    public function test_show(): void
    {
        $word = Word::factory()->create();

        Sanctum::actingAs($this->user);
        $response = $this->getJson(route('word.show', ['id' => $word->id]));
        $response->assertOk();
        $response->assertJson(['message' => 'success']);
        $response->assertJson(['data' => $word->toArray()]);
    }
}
