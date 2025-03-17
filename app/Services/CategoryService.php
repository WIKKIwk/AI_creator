<?php

namespace App\Services;

use App\Models\User;

class CategoryService
{
    public function store(User $user, array $validated): void
    {
        $user->categories()->create($validated);
    }

    public function delete(User $user, int $id): void
    {
        $user->categories()->where('id', $id)->delete();
    }
}
