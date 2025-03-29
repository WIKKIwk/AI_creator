<?php

namespace App\Services\Handler;

use App\Models\User;

interface HandlerInterface
{
    public function handle(User $user, array $update): void;

    public function validateUser(User $user): void;
    public function handleStart(): void;
    public function handleHelp(): void;
    public function handleCbQuery(string $cbData): void;
    public function handleText(string $text): void;
}
