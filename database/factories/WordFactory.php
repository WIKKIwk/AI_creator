<?php

namespace Database\Factories;

use App\Models\Word;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Word>
 */
class WordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'word' => fake()->word,
            'meaning' => fake()->sentence,
            'synonyms' => fake()->sentence,
            'antonyms' => fake()->sentence,
            'is_favorite' => fake()->boolean,
            'user_id' => User::query()->first()?->id,
        ];
    }
}
