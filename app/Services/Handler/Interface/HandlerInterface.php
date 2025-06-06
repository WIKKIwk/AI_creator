<?php

namespace App\Services\Handler\Interface;

use App\Models\User;

interface HandlerInterface
{
    public function handle(User $user, array $update): void;

    public function validateUser(User $user): bool;
    public function handleStart(): void;
    public function handleInlineQuery(array $inlineQuery): void;
    public function handleHelp(): void;
    public function handleCbQuery(string $cbData): void;
    public function handleText(string $text): void;
}
