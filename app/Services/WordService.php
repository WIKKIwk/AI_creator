<?php

namespace App\Services;

use App\Models\User;
use App\Models\Word;

class WordService
{
    public function store(User $user, $validated): void
    {
        $user->words()->create($validated);
    }

    public function update(Word $word, $validated): void
    {
        $word->update($validated);
    }

    public function move(Word $word, int $categoryId): void
    {
        $word->update(['category_id' => $categoryId]);
    }

    public function progress(Word $word, $learnStatus): void
    {
        $word->update(['learn_status' => $learnStatus]);
    }
}
